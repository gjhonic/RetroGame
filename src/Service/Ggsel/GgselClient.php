<?php

namespace App\Service\Ggsel;

use App\Service\Ggsel\Exceptions\GgselApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Клиент для ggsel.net — публичного API нет, поэтому это HTML-скрапинг
 * страницы товара по прямому URL /catalog/{slug}. Разметка снята с
 * реального ответа сайта (страница /catalog/resident-evil-2), парсинг
 * рассчитан именно на неё:
 *
 * - страница товара встраивает JSON-LD <script type="application/ld+json">
 *   с {"@type":"Product","offers":{"@type":"AggregateOffer","offerCount":N}} —
 *   это единственный стабильный сигнал "товар есть и не пуст", в отличие
 *   от минифицированных CSS-классов/структуры Next.js, которые меняются
 *   от сборки к сборке;
 * - канонический URL берётся из <link rel="canonical">.
 *
 * Точной разметки страницы "не найдено" на момент написания нет, поэтому
 * признак "не найдено" — любой не-200 ответ ИЛИ 200 без Product JSON-LD
 * (сайт может рендерить свой шаблон 404 с тем же кодом ответа — тогда
 * просто не будет искомого JSON-LD). Если окажется, что ggsel всегда
 * отдаёт настоящий 404, это никак не ломает текущую логику.
 *
 * Точный слаг ggsel предсказать по названию игры нельзя (например, у
 * Half-Life 2 слаг совпадает с транслитерацией названия — "half-life-2",
 * а у Garry's Mod товар лежит на "garrys-mod-keys", с суффиксом категории)
 * — поэтому GameImportService перебирает несколько слагов-кандидатов
 * (базовый + известные суффиксы), вызывая findBySlug() для каждого по
 * очереди, пока не найдёт непустую страницу. Поиска через /search/...
 * сознательно нет — его разметка неизвестна и небезопасно на неё
 * полагаться (риск зацепить не результат поиска, а статичную ссылку из
 * шапки/меню сайта); если ни один слаг-кандидат не сработал, игра просто
 * остаётся непроверенной до следующего круга обхода.
 *
 * Сайт отдаёт 401/403 без браузерных заголовков (проверено вручную) —
 * поэтому запросы идут с реалистичным User-Agent/Accept-Language. Если
 * ggsel усилит защиту от ботов (например, JS-челлендж на уровне CDN),
 * запросы могут перестать проходить вовсе — это не должно ронять команду
 * импорта, поэтому сетевые ошибки одной игры не пробрасываются наверх
 * командой (см. GameImportService::checkGame()), а требуют либо смены
 * подхода (прокси/headless-браузер), либо ручной проверки логов крона.
 */
class GgselClient
{
    /** Публичная — переиспользуется GameImportService для сообщения о сетевой ошибке (см. GgselSlugAttempt). */
    public const CATALOG_URL = 'https://ggsel.net/catalog/%s';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Пробует найти товар по прямому URL /catalog/{slug} (слаг строится
     * транслитерацией названия игры, см. GameImportService::checkGame()).
     * Возвращает не просто найдено/не найдено, а причину — какой URL
     * запрашивали и что там оказалось (HTTP-статус, отсутствие данных о
     * товаре или пустая категория), чтобы это можно было залогировать
     * (см. ImportGgselGamesCommand) и понять, почему игра не нашлась.
     *
     * @throws GgselApiException
     */
    public function findBySlug(string $slug): GgselSlugAttempt
    {
        $url = sprintf(self::CATALOG_URL, $slug);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return new GgselSlugAttempt($url, null, sprintf('HTTP %d', $statusCode));
            }

            $html = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw new GgselApiException(
                sprintf('Ошибка запроса к ggsel.net (%s): %s', $url, $e->getMessage()),
                previous: $e,
            );
        }

        return $this->parseProductPage($url, $html);
    }

    private function parseProductPage(string $url, string $html): GgselSlugAttempt
    {
        $product = $this->extractProductJsonLd($html);
        if ($product === null) {
            return new GgselSlugAttempt($url, null, 'HTTP 200, но на странице нет данных о товаре (Product JSON-LD)');
        }

        $offerCount = (int) ($product['offers']['offerCount'] ?? 0);
        if ($offerCount < 1) {
            return new GgselSlugAttempt($url, null, 'HTTP 200, но товар не продаётся (offerCount = 0)');
        }

        $productUrl = $this->extractCanonicalUrl($html) ?? (string) ($product['offers']['url'] ?? '');
        if ($productUrl === '') {
            return new GgselSlugAttempt($url, null, 'HTTP 200, товар есть, но не удалось определить ссылку на него');
        }

        return new GgselSlugAttempt($url, new GgselProduct($productUrl), 'найдено');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractProductJsonLd(string $html): ?array
    {
        if (preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is', $html, $matches) === 0) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['@type'] ?? null) === 'Product') {
                return $data;
            }
        }

        return null;
    }

    private function extractCanonicalUrl(string $html): ?string
    {
        return preg_match('#<link[^>]*rel="canonical"[^>]*href="([^"]+)"#i', $html, $m) === 1 ? $m[1] : null;
    }
}
