<?php

namespace App\Service\Plati;

use App\Service\Plati\Exceptions\PlatiApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Клиент публичного Web API Digiseller (api.digiseller.ru) — движка,
 * на котором работает plati.market. В отличие от ggsel.net (изначальной
 * цели этого модуля) запросы сюда не требуют браузерного контекста и не
 * упираются в антибот-защиту (Qrator) — обычный HTTP-запрос без токена
 * проходит и отдаёт готовый JSON, поэтому HTML-скрапинг не нужен вовсе.
 *
 * `owner=1` — это и есть plati.market (числовой идентификатор витрины в
 * общей системе Digiseller, подтверждено вручную: только он отдаёт
 * непустые результаты, остальные проверенные id/строковые значения —
 * пустой список или ошибка "Платформа не найдена"). Параметр `host`
 * заявлен в API, но на практике не влияет на выдачу — не используется.
 *
 * Поиск полнотекстовый и не различает "правильную" карточку игры среди
 * десятков объявлений разных продавцов — сопоставление с конкретной
 * игрой (нормализация названия, отбор совпадений, выбор самого
 * продаваемого объявления) делает GameImportService, а не клиент.
 */
class PlatiClient
{
    private const SEARCH_URL = 'https://api.digiseller.ru/api/products/search2';

    /** Числовой идентификатор витрины plati.market в системе Digiseller. */
    private const OWNER_ID = 1;

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    /**
     * Ищет товары по тексту запроса (как правило — название игры).
     * Возвращает пустой массив, если ничего не найдено — это не ошибка.
     *
     * @return array<int, PlatiSearchItem>
     *
     * @throws PlatiApiException
     */
    public function search(string $query, int $limit): array
    {
        try {
            $response = $this->httpClient->request('GET', self::SEARCH_URL, [
                'query' => [
                    'query' => $query,
                    'pagesize' => $limit,
                    'pagenum' => 1,
                    'owner' => self::OWNER_ID,
                    'lang' => 'ru',
                ],
            ]);

            /** @var array{items?: array{item?: array<int, array<string, mixed>>}} $data */
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new PlatiApiException(
                sprintf('Ошибка запроса к api.digiseller.ru (query="%s"): %s', $query, $e->getMessage()),
                previous: $e,
            );
        }

        $rawItems = $data['items']['item'] ?? [];

        return array_map($this->mapItem(...), $rawItems);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function mapItem(array $raw): PlatiSearchItem
    {
        return new PlatiSearchItem(
            id: (string) ($raw['id'] ?? ''),
            name: (string) ($raw['name'] ?? ''),
            nameEng: (string) ($raw['name_eng'] ?? ''),
            url: (string) ($raw['url'] ?? ''),
            cntSell: (int) ($raw['cnt_sell'] ?? 0),
            sellerName: (string) ($raw['seller_name'] ?? ''),
            priceRur: $this->parsePriceRur($raw['price_rur'] ?? null),
        );
    }

    /**
     * price_rur в ответе Digiseller — строка с целым числом рублей (без
     * дробной части, в отличие от price_usd/price_eur, где дробная часть
     * через запятую) — is_numeric() отсекает нечисловые/пустые значения.
     */
    private function parsePriceRur(mixed $raw): ?int
    {
        if (!is_string($raw) && !is_int($raw)) {
            return null;
        }

        if (!is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }
}
