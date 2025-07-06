<?php

namespace App\Controller\Admin;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\SteamApp;
use App\Repository\SteamAppRepository;
use App\Service\SteamAppReimportService;
use App\Service\SteamGameDataProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin/steam-app')]
class SteamAppController extends AbstractController
{
    #[Route('/', name: 'admin_steam_app_index', methods: ['GET'])]
    public function index(SteamAppRepository $steamAppRepository, Request $request): Response
    {
        $search = $request->query->get('q');
        $name = $request->query->get('name');
        $type = $request->query->get('type');
        $page = (int) max(1, (int) $request->query->get('page', '1'));
        $limit = 20;

        $sort = $request->query->get('sort', 'createdAt');
        $direction = strtolower($request->query->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $steamApps = $steamAppRepository->findSteamAppsByFilters($search, $name, $type, $page, $limit, $sort, $direction);
        $total = $steamAppRepository->countByFilters($search, $name, $type);
        $totalPages = (int) ceil($total / $limit);

        return $this->render('admin/steam_app/index.html.twig', [
            'steam_apps' => $steamApps,
            'search' => $search,
            'name' => $name,
            'selectedType' => $type,
            'sort' => $sort,
            'direction' => $direction,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/import', name: 'admin_steam_app_import', methods: ['GET'])]
    public function importForm(): Response
    {
        return $this->render('admin/steam_app/import.html.twig');
    }

    #[Route('/import', name: 'admin_steam_app_import_process', methods: ['POST'])]
    public function importProcess(
        Request $request,
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        SteamGameDataProcessor $gameDataProcessor
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('import_steam_app', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Недействительный CSRF токен',
            ], Response::HTTP_BAD_REQUEST);
        }

        $appId = (int) $request->request->get('app_id');

        if ($appId <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Неверный ID приложения Steam',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Проверяем, не существует ли уже такая запись
        $existingSteamApp = $entityManager->getRepository(SteamApp::class)->findOneBy(['app_id' => $appId]);
        if ($existingSteamApp) {
            return $this->json([
                'success' => false,
                'message' => 'Приложение с таким ID уже существует в базе данных',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Получаем данные из Steam API
            $detailsResponse = $httpClient->request('GET', 'https://store.steampowered.com/api/appdetails', [
                'query' => [
                    'appids' => $appId,
                    'l' => 'russian',
                ],
            ]);
            $detailsData = $detailsResponse->toArray();
        } catch (TransportExceptionInterface $e) {
            return $this->json([
                'success' => false,
                'message' => 'HTTP-ошибка при получении данных: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Ошибка при обработке ответа API: ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Проверяем, что данные получены успешно
        if (!isset($detailsData[$appId]) || !isset($detailsData[$appId]['success']) || !$detailsData[$appId]['success']) {
            return $this->json([
                'success' => false,
                'message' => 'Приложение не найдено или недоступно в Steam API',
            ], Response::HTTP_BAD_REQUEST);
        }

        $appData = $detailsData[$appId]['data'] ?? [];
        $gameName = $appData['name'] ?? 'Unknown';
        $type = $appData['type'] ?? 'unknown';

        // Создаем новую запись SteamApp
        $steamApp = new SteamApp();
        $steamApp->setAppId($appId);
        $steamApp->setName($gameName);
        $steamApp->setType($type);
        $steamApp->setRawData((string) json_encode($detailsData, JSON_UNESCAPED_UNICODE));

        $entityManager->persist($steamApp);
        $entityManager->flush();

        // Используем SteamGameDataProcessor для обработки данных игры
        $existingGameNames = [];
        $existingGameShopIds = [];

        $gameProcessed = $gameDataProcessor->processGameData(
            $detailsData,
            null, // output не нужен для веб-интерфейса
            $existingGameNames,
            $existingGameShopIds
        );

        // Проверяем, создались ли записи в базе данных
        $gameCreated = null;
        $gameShopCreated = null;

        if ($gameProcessed) {
            // Ищем созданную игру
            $gameCreated = $entityManager->getRepository(Game::class)->findOneBy(['name' => $gameName]);

            // Ищем созданный GameShop
            if ($gameCreated) {
                $gameShopCreated = $entityManager->getRepository(GameShop::class)->findOneBy([
                    'game' => $gameCreated,
                    'link_game_id' => $appId
                ]);
            }
        }

        // Добавляем отладочную информацию
        $debugInfo = [
            'app_id' => $appId,
            'name' => $gameName,
            'type' => $type,
            'is_game' => $gameDataProcessor->isGame($appData),
            'has_description' => !empty($appData['short_description']),
            'has_genres' => !empty($appData['genres']),
            'has_price' => !empty($appData['price_overview']),
            'game_processed' => $gameProcessed,
            'game_created' => $gameCreated ? $gameCreated->getId() : null,
            'game_shop_created' => $gameShopCreated ? $gameShopCreated->getId() : null,
        ];

        $message = $gameProcessed
            ? "Приложение успешно импортировано. Игра также добавлена в базу данных."
            : "Приложение импортировано, но игра не была добавлена в базу данных (возможно, это не игра).";

        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'app_id' => $appId,
                'name' => $gameName,
                'type' => $type,
                'game_processed' => $gameProcessed,
                'steam_app_id' => $steamApp->getId(),
                'debug' => $debugInfo,
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_steam_app_show', methods: ['GET'])]
    public function show(SteamApp $steamApp): Response
    {
        return $this->render('admin/steam_app/show.html.twig', [
            'steam_app' => $steamApp,
        ]);
    }

    #[Route('/{id}/reimport', name: 'admin_steam_app_reimport', methods: ['POST'])]
    public function reimport(SteamApp $steamApp, SteamAppReimportService $reimportService, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('reimport' . $steamApp->getId(), (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Недействительный CSRF токен',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $reimportService->reimportSteamApp((int)$steamApp->getAppId());

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }
}
