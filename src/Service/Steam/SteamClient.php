<?php

namespace App\Service\Steam;

use App\Service\Steam\Exceptions\SteamApiException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Клиент для Steam Web API.
 *
 * Список приложений (IStoreService/GetAppList) требует бесплатный ключ:
 * https://steamcommunity.com/dev/apikey. Детали конкретного приложения
 * (Store API appdetails) ключа не требуют, но это недокументированный
 * эндпоинт без официального SLA — запросы идут по одному appid за раз,
 * с паузой между ними (см. --delay-ms у команды импорта).
 */
class SteamClient
{
    private const APP_LIST_URL = 'https://api.steampowered.com/IStoreService/GetAppList/v1/';
    private const APP_DETAILS_URL = 'https://store.steampowered.com/api/appdetails';

    /** Принимает HTTP-клиент и ключ Steam Web API из окружения. */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'STEAM_API_KEY')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * Порция каталога игр Steam (без DLC/софта/саундтреков — это уже
     * отфильтровано на стороне Steam через include_games/include_dlc).
     *
     * Постраничность — курсорная (last_appid из предыдущего ответа),
     * а не offset, так исторически устроен сам Steam Web API.
     *
     * @return array{apps: array<int, array{appid: int, name: string}>, hasMore: bool, lastAppId: int}
     */
    public function fetchGameAppList(int $maxResults, int $lastAppId = 0): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException(
                'Не задан STEAM_API_KEY. Получить бесплатно: https://steamcommunity.com/dev/apikey '
                .                  'и прописать в .env.local.',
            );
        }

        $response = $this->httpClient->request('GET', self::APP_LIST_URL, [
            'query' => [
                'key' => $this->apiKey,
                'include_games' => 1,
                'include_dlc' => 0,
                'include_software' => 0,
                'include_videos' => 0,
                'include_hardware' => 0,
                'max_results' => $maxResults,
                'last_appid' => $lastAppId,
            ],
        ]);

        /** @var array{response: array{apps?: array<int, array{appid: int, name: string}>, have_more_results?: bool, last_appid?: int}} $data */
        $data = $response->toArray();

        return [
            'apps' => $data['response']['apps'] ?? [],
            'hasMore' => $data['response']['have_more_results'] ?? false,
            'lastAppId' =>  $data['response']['last_appid'] ?? $lastAppId,
        ];
    }

    /**
     * Детали одного приложения. Сначала запрашивается без указания региона
     * (Steam определяет его по IP сервера); если данных нет — это может
     * быть как реальное снятие с продажи, так и регион-лок для IP сервера,
     * поэтому делается повторный запрос с cc=us, прежде чем считать игру
     * недоступной. Если запрос вообще не удался (сеть, таймаут, 5xx и т.д.),
     * бросает SteamApiException — это повод пометить попытку неудачной
     * и повторить позже, а не молча считать игру недоступной.
     *
     * @return array<string, mixed>|null
     *
     * @throws SteamApiException
     */
    public function fetchAppDetails(int $appId, string $language = 'russian'): ?array
    {
        $gameData = $this->requestAppDetails($appId, $language, null);

        return $gameData ?? $this->requestAppDetails($appId, $language, 'us');
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws SteamApiException
     */
    private function requestAppDetails(int $appId, string $language, ?string $countryCode): ?array
    {
        try {
            $query = [
                'appids' => $appId,
                'l' => $language,
            ];
            if ($countryCode !== null) {
                $query['cc'] = $countryCode;
            }

            $response = $this->httpClient->request('GET', self::APP_DETAILS_URL, ['query' => $query]);

            // JSON-ключи вида "730" декодируются PHP в array как int-ключи.
            /** @var array<int, mixed> $data */
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new SteamApiException(
                sprintf('Ошибка запроса к Steam appdetails (appid %d): %s', $appId, $e->getMessage()),
                previous: $e,
            );
        }

        /** @var array<string, mixed>|null $entry */
        $entry = $data[$appId] ?? null;

        if (!is_array($entry) || ($entry['success'] ?? false) !== true || !isset($entry['data'])) {
            return null;
        }

        /** @var array<string, mixed> $gameData */
        $gameData = $entry['data'];

        return $gameData;
    }
}
