<?php

namespace App\Controller\Api\Admin;

use App\Repository\OurGameDownloadLinkRepository;
use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameDownloadLinkCrudService;
use App\Service\OurGame\OurGameDownloadLinkRequestFactory;
use App\Service\OurGame\OurGameImageUploadService;
use App\Service\OurGame\OurGameMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** JSON API ссылок на скачивание своих игр — используется Vue-компонентом Admin/OurGameDetail. */
#[Route('/api/admin/our-games/{ourGameId}/download-links', requirements: ['ourGameId' => '\d+'])]
#[IsGranted('ROLE_MODERATOR')]
#[OA\Tag(name: 'Admin/OurGames')]
class OurGameDownloadLinkApiController extends AbstractController
{
    /** Добавляет ссылку на скачивание. */
    #[Route('', name: 'app_api_admin_our_game_download_link_create', methods: ['POST'])]
    #[OA\Parameter(name: 'ourGameId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 201, description: 'Ссылка создана')]
    #[OA\Response(response: 404, description: 'Игра не найдена')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        int $ourGameId,
        Request $request,
        OurGameDownloadLinkRequestFactory $requestFactory,
        OurGameRepository $ourGameRepository,
        OurGameDownloadLinkCrudService $downloadLinkCrudService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $game = $ourGameRepository->find($ourGameId);
        if ($game === null) {
            throw $this->createNotFoundException('Игра не найдена.');
        }

        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        $link = $downloadLinkCrudService->create($game, $dto);

        return $this->json($ourGameMapper->toDownloadLinkItem($link), 201);
    }

    /** Обновляет платформу/ссылку. */
    #[Route(
        '/{linkId}',
        name: 'app_api_admin_our_game_download_link_update',
        methods: ['PATCH'],
        requirements: ['linkId' => '\d+'],
    )]
    #[OA\Parameter(name: 'ourGameId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'linkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Ссылка обновлена')]
    #[OA\Response(response: 404, description: 'Игра или ссылка не найдены')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function update(
        int $ourGameId,
        int $linkId,
        Request $request,
        OurGameDownloadLinkRequestFactory $requestFactory,
        OurGameDownloadLinkRepository $downloadLinkRepository,
        OurGameDownloadLinkCrudService $downloadLinkCrudService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $link = $downloadLinkRepository->find($linkId);
        if ($link === null || $link->getOurGame()->getId() !== $ourGameId) {
            throw $this->createNotFoundException('Ссылка не найдена.');
        }

        [$dto, $errors] = $requestFactory->fromJson($request->getContent());
        if ($dto === null) {
            return $this->json(['errors' => $errors], 422);
        }

        $link = $downloadLinkCrudService->update($link, $dto);

        return $this->json($ourGameMapper->toDownloadLinkItem($link));
    }

    /** Загружает/заменяет иконку кнопки скачивания. */
    #[Route(
        '/{linkId}/image',
        name: 'app_api_admin_our_game_download_link_upload_image',
        methods: ['POST'],
        requirements: ['linkId' => '\d+'],
    )]
    #[OA\Parameter(name: 'ourGameId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'linkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Иконка загружена')]
    #[OA\Response(response: 404, description: 'Игра или ссылка не найдены')]
    #[OA\Response(response: 422, description: 'Файл не передан')]
    public function uploadImage(
        int $ourGameId,
        int $linkId,
        Request $request,
        OurGameDownloadLinkRepository $downloadLinkRepository,
        OurGameImageUploadService $imageUploadService,
        OurGameMapper $ourGameMapper,
    ): JsonResponse {
        $link = $downloadLinkRepository->find($linkId);
        if ($link === null || $link->getOurGame()->getId() !== $ourGameId) {
            throw $this->createNotFoundException('Ссылка не найдена.');
        }

        $file = $request->files->get('file');
        if ($file === null || !$file->isValid()) {
            return $this->json(['errors' => ['file' => [$file?->getErrorMessage() ?? 'Файл не передан.']]], 422);
        }

        $link = $imageUploadService->uploadDownloadLinkImage($link, $file);

        return $this->json($ourGameMapper->toDownloadLinkItem($link));
    }

    /** Удаляет ссылку на скачивание вместе с её иконкой. */
    #[Route(
        '/{linkId}',
        name: 'app_api_admin_our_game_download_link_delete',
        methods: ['DELETE'],
        requirements: ['linkId' => '\d+'],
    )]
    #[OA\Parameter(name: 'ourGameId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'linkId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Ссылка удалена')]
    #[OA\Response(response: 404, description: 'Игра или ссылка не найдены')]
    public function delete(
        int $ourGameId,
        int $linkId,
        OurGameDownloadLinkRepository $downloadLinkRepository,
        OurGameDownloadLinkCrudService $downloadLinkCrudService,
    ): JsonResponse {
        $link = $downloadLinkRepository->find($linkId);
        if ($link === null || $link->getOurGame()->getId() !== $ourGameId) {
            throw $this->createNotFoundException('Ссылка не найдена.');
        }

        $downloadLinkCrudService->delete($link);

        return $this->json(null, 204);
    }
}
