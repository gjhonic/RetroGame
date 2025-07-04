<?php

namespace App\Controller\Admin;

use App\Entity\Shop;
use App\Form\ShopType;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/shop')]
class ShopController extends AbstractController
{
    #[Route('/', name: 'admin_shop_index', methods: ['GET'])]
    public function index(ShopRepository $shopRepository): Response
    {
        return $this->render('admin/shop/index.html.twig', [
            'shops' => $shopRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_shop_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $shop = new Shop();
        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $uploadDir = __DIR__ . '/../../../public/uploads/shops';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                            throw new \RuntimeException('Не удалось создать директорию загрузки: ' . $uploadDir);
                        }
                    }
                    $imageFile->move($uploadDir, $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Ошибка загрузки файла: ' . $e->getMessage());
                }
                $shop->setImage($newFilename);
            }
            $entityManager->persist($shop);
            $entityManager->flush();

            return $this->redirectToRoute('admin_shop_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/shop/new.html.twig', [
            'shop' => $shop,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_shop_show', methods: ['GET'])]
    public function show(Shop $shop): Response
    {
        return $this->render('admin/shop/show.html.twig', [
            'shop' => $shop,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_shop_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Shop $shop,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);
        $oldImage = $shop->getImage();

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();
                try {
                    $uploadDir = __DIR__ . '/../../../public/uploads/shops';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                            throw new \RuntimeException('Не удалось создать директорию загрузки: ' . $uploadDir);
                        }
                    }
                    $imageFile->move($uploadDir, $newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('danger', 'Ошибка загрузки файла: ' . $e->getMessage());
                }
                $shop->setImage($newFilename);
            } else {
                $shop->setImage($oldImage);
            }
            $entityManager->flush();

            return $this->redirectToRoute('admin_shop_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/shop/edit.html.twig', [
            'shop' => $shop,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_shop_delete', methods: ['POST'])]
    public function delete(Request $request, Shop $shop, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $shop->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($shop);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_shop_index', [], Response::HTTP_SEE_OTHER);
    }
}
