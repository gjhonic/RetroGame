<?php

namespace App\Service;

use App\Entity\SteamApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SteamAppReimportService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly SteamGameDataProcessor $gameDataProcessor,
    ) {
    }

    /**
     * Повторно импортирует Steam приложение по app_id.
     *
     * @param int $appId ID приложения Steam
     * @return array<string, mixed>
     */
    public function reimportSteamApp(int $appId): array
    {
        try {
            // Получаем подробную информацию о приложении
            $detailsResponse = $this->httpClient->request('GET', 'https://store.steampowered.com/api/appdetails', [
                'query' => [
                    'appids' => $appId,
                    'l' => 'russian',
                ],
            ]);
            $detailsData = $detailsResponse->toArray();
        } catch (TransportExceptionInterface $e) {
            return [
                'success' => false,
                'message' => 'HTTP-ошибка при получении данных: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Ошибка при обработке ответа API: ' . $e->getMessage(),
            ];
        }

        // Проверяем, что данные получены успешно
        if (!isset($detailsData[$appId]) || !isset($detailsData[$appId]['success']) || !$detailsData[$appId]['success']) {
            return [
                'success' => false,
                'message' => 'Приложение не найдено или недоступно в Steam API',
            ];
        }

        $appData = $detailsData[$appId]['data'] ?? [];
        $gameName = $appData['name'] ?? 'Unknown';
        $type = $appData['type'] ?? 'unknown';

        // Находим существующую запись SteamApp
        $steamApp = $this->entityManager->getRepository(SteamApp::class)->findOneBy(['app_id' => $appId]);

        if (!$steamApp) {
            return [
                'success' => false,
                'message' => 'Запись SteamApp с таким app_id не найдена в базе данных',
            ];
        }

        // Обновляем данные SteamApp
        $steamApp->setName($gameName);
        $steamApp->setType($type);
        $steamApp->setRawData((string) json_encode($detailsData, JSON_UNESCAPED_UNICODE));

        $this->entityManager->persist($steamApp);
        $this->entityManager->flush();

        // Используем существующий SteamGameDataProcessor для обработки данных игры
        $existingGameNames = [];
        $existingGameShopIds = [];

        $gameProcessed = $this->gameDataProcessor->processGameData(
            $detailsData,
            null, // output не нужен для веб-интерфейса
            $existingGameNames,
            $existingGameShopIds
        );

        $message = $gameProcessed
            ? "Приложение успешно переимпортировано. Игра также добавлена в базу данных."
            : "Приложение переимпортировано, но игра не была добавлена в базу данных (возможно, это не игра или уже существует).";

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'name' => $gameName,
                'type' => $type,
                'game_processed' => $gameProcessed,
            ],
        ];
    }
}
