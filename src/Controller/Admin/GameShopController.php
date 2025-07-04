<?php

namespace App\Controller\Admin;

use App\Entity\GameShop;
use App\Entity\SteamApp;
use App\Form\GameShopType;
use App\Repository\GameShopRepository;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/game-shop')]
class GameShopController extends AbstractController
{
    #[Route('/', name: 'admin_game_shop_index', methods: ['GET'])]
    public function index(Request $request, GameShopRepository $gameShopRepository, ShopRepository $shopRepository): Response
    {
        $shopId = $request->query->get('shop_id');
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'desc');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 15;
        $offset = ($page - 1) * $limit;

        $qb = $gameShopRepository->createQueryBuilder('gs')
            ->leftJoin('gs.shop', 's')
            ->addSelect('s')
            ->leftJoin('gs.game', 'g')
            ->addSelect('g');

        if ($shopId) {
            $qb->andWhere('s.id = :shopId')
                ->setParameter('shopId', $shopId);
        }

        if ($sort === 'createdAt') {
            $qb->orderBy('gs.createdAt', $direction);
        } else {
            $qb->orderBy('gs.id', 'desc');
        }

        $qbCount = clone $qb;
        $qb->setFirstResult($offset)->setMaxResults($limit);
        $gameShops = $qb->getQuery()->getResult();
        $total = (int)$qbCount->select('COUNT(gs.id)')->getQuery()->getSingleScalarResult();
        $totalPages = (int)ceil($total / $limit);

        $shops = $shopRepository->findAll();

        return $this->render('admin/game_shop/index.html.twig', [
            'gameShops' => $gameShops,
            'shops' => $shops,
            'shopId' => $shopId,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id}', name: 'admin_game_shop_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, GameShopRepository $gameShopRepository, EntityManagerInterface $em): Response
    {
        $gameShop = $gameShopRepository->findWithRelations($id);
        if (!$gameShop) {
            throw $this->createNotFoundException('GameShop не найден');
        }

        $dataFromApi = null;
        if ($gameShop->getShop()->getId() == 1) {
            $steamApp = $em->getRepository(SteamApp::class)
                ->findOneBy(['app_id' => $gameShop->getLinkGameId()]);
            if ($steamApp) {
                $raw = $steamApp->getRawData();
                $decoded = json_decode($raw, true);
                if ($decoded) {
                    $dataFromApi = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                } else {
                    $dataFromApi = $raw;
                }
            }
        }

        return $this->render('admin/game_shop/show.html.twig', [
            'gameShop' => $gameShop,
            'dataFromApi' => $dataFromApi,
        ]);
    }
}
