<?php

// src/Controller/Admin/DashboardController.php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\GameShopRepository;
use App\Repository\GameShopPriceHistoryRepository;

class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(Request $request, GameShopRepository $gameShopRepository, GameShopPriceHistoryRepository $priceHistoryRepository): Response
    {
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        if ($dateFrom) {
            $dateFromObj = \DateTime::createFromFormat('Y-m-d', $dateFrom);
        } else {
            $dateFromObj = (new \DateTime('-13 days'))->setTime(0,0,0); // 14 дней включая сегодня
        }
        if ($dateTo) {
            $dateToObj = \DateTime::createFromFormat('Y-m-d', $dateTo);
        } else {
            $dateToObj = (new \DateTime())->setTime(23,59,59);
        }

        $importStats = $gameShopRepository->getImportStatsByDayAndShop($dateFromObj, $dateToObj);
        $importPriceStats = $priceHistoryRepository->getImportStatsByDay($dateFromObj, $dateToObj);

        return $this->render('admin/dashboard/index.html.twig', [
            'importStats' => $importStats,
            'importPriceStats' => $importPriceStats,
            'dateFrom' => $dateFromObj->format('Y-m-d'),
            'dateTo' => $dateToObj->format('Y-m-d'),
        ]);
    }
}
