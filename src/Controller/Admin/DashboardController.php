<?php

// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use App\Repository\GameShopPriceHistoryRepository;
use App\Repository\GameShopRepository;
use App\Repository\GameRepository;
use App\Repository\ShopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(
        Request $request,
        GameShopRepository $gameShopRepository,
        GameShopPriceHistoryRepository $priceHistoryRepository,
        GameRepository $gameRepository,
        ShopRepository $shopRepository
    ): Response {
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        if ($dateFrom) {
            $dateFromObj = \DateTime::createFromFormat('Y-m-d', $dateFrom);
            if ($dateFromObj == false) {
                $dateFromObj = (new \DateTime('-13 days'))->setTime(0, 0, 0);
            }
        } else {
            $dateFromObj = (new \DateTime('-13 days'))->setTime(0, 0, 0);
        }
        if ($dateTo) {
            $dateToObj = \DateTime::createFromFormat('Y-m-d', $dateTo);
            if ($dateToObj == false) {
                $dateToObj = (new \DateTime())->setTime(23, 59, 59);
            }
        } else {
            $dateToObj = (new \DateTime())->setTime(23, 59, 59);
        }

        $importStats = $gameShopRepository->getImportStatsByDayAndShop($dateFromObj, $dateToObj);
        $importPriceStats = $priceHistoryRepository->getImportStatsByDay($dateFromObj, $dateToObj);
        $totalGamesByShop = $gameShopRepository->getTotalGamesByShop();

        // Статистическая информация
        $totalGames = $gameRepository->getTotalGamesCount();
        $totalPrices = $priceHistoryRepository->getTotalPricesCount();
        $shopsWithGameCount = $shopRepository->getShopsWithGameCount();

        return $this->render('admin/dashboard/index.html.twig', [
            'importStats' => $importStats,
            'importPriceStats' => $importPriceStats,
            'totalGamesByShop' => $totalGamesByShop,
            'totalGames' => $totalGames,
            'totalPrices' => $totalPrices,
            'shopsWithGameCount' => $shopsWithGameCount,
            'dateFrom' => $dateFromObj->format('Y-m-d'),
            'dateTo' => $dateToObj->format('Y-m-d'),
        ]);
    }
}
