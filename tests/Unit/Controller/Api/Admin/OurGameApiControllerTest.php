<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\OurGameApiController;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameCrudService;
use App\Service\OurGame\OurGameImageUploadService;
use App\Service\OurGame\OurGameMapper;
use App\Service\OurGame\OurGameRequestFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

/**
 * Моки OurGameRepository/OurGameCrudService/OurGameImageUploadService здесь и как
 * стабы, и как моки (проверка вызовов) — строгая проверка "мок без expects()" отключена,
 * как и в GameApiControllerTest. OurGameRequestFactory собран из реального Serializer+Validator
 * (по образцу RegistrationApiControllerTest), т.к. это простой класс без побочных эффектов.
 */
#[AllowMockObjectsWithoutExpectations]
class OurGameApiControllerTest extends TestCase
{
    private OurGameRepository&MockObject $ourGameRepository;
    private OurGameCrudService&MockObject $ourGameCrudService;
    private OurGameImageUploadService&MockObject $imageUploadService;
    private OurGameMapper $ourGameMapper;
    private OurGameRequestFactory $requestFactory;
    private OurGameApiController $controller;

    protected function setUp(): void
    {
        $this->ourGameRepository = $this->createMock(OurGameRepository::class);
        $this->ourGameCrudService = $this->createMock(OurGameCrudService::class);
        $this->imageUploadService = $this->createMock(OurGameImageUploadService::class);
        $this->ourGameMapper = new OurGameMapper();

        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $this->requestFactory = new OurGameRequestFactory($serializer, $validator);

        $this->controller = new OurGameApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $game = (new OurGame('Die Again', 'die-again', OurGameStatus::Published))->setCurrentVersion('1.0.0');

        $this->ourGameRepository->method('countForAdminList')->willReturn(1);
        $this->ourGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([$game]);

        $response = $this->controller->list(new Request(), $this->ourGameRepository, $this->ourGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('Die Again', $data['items'][0]['name']);
        self::assertSame('1.0.0', $data['items'][0]['currentVersion']);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->ourGameRepository->method('countForAdminList')->willReturn(0);
        $this->ourGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'die', 'status' => 'published'], 'releaseDate', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' die ', 'status' => ' published '],
            'sortBy' => 'releaseDate',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->ourGameRepository, $this->ourGameMapper);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->ourGameRepository->method('countForAdminList')->willReturn(0);
        $this->ourGameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->ourGameRepository, $this->ourGameMapper);
    }

    public function testCreateReturnsCreatedGameOnValidRequest(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameCrudService->expects($this->once())->method('create')->willReturn($game);

        $request = new Request(content: json_encode([
            'name' => 'Die Again',
            'description' => '',
            'status' => 'draft',
            'currentVersion' => '',
            'releaseDate' => '',
            'trailerUrl' => '',
            'genreIds' => [],
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->requestFactory,
            $this->ourGameCrudService,
            $this->ourGameMapper,
        );

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Die Again', $data['name']);
    }

    public function testCreateReturnsValidationErrorsForBlankName(): void
    {
        $this->ourGameCrudService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'name' => '',
            'status' => 'draft',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->requestFactory,
            $this->ourGameCrudService,
            $this->ourGameMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('name', $data['errors']);
    }

    public function testShowReturnsFullDetail(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->expects($this->once())->method('find')->with(42)->willReturn($game);

        $response = $this->controller->show(42, $this->ourGameRepository, $this->ourGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Die Again', $data['name']);
        self::assertArrayHasKey('downloadLinks', $data);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->ourGameRepository, $this->ourGameMapper);
    }

    public function testUpdateThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGameRepository->expects($this->once())->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->update(
            999,
            new Request(content: '{}'),
            $this->requestFactory,
            $this->ourGameRepository,
            $this->ourGameCrudService,
            $this->ourGameMapper,
        );
    }

    public function testUpdateReturnsUpdatedGame(): void
    {
        $game = new OurGame('Old Name', 'old-name');
        $this->ourGameRepository->method('find')->willReturn($game);

        $updated = (new OurGame('Die Again', 'die-again'));
        $this->ourGameCrudService->expects($this->once())->method('update')->willReturn($updated);

        $request = new Request(content: json_encode([
            'name' => 'Die Again',
            'status' => 'draft',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->update(
            1,
            $request,
            $this->requestFactory,
            $this->ourGameRepository,
            $this->ourGameCrudService,
            $this->ourGameMapper,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('Die Again', $data['name']);
    }

    public function testDeleteRemovesGameAndReturns204(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->ourGameCrudService->expects($this->once())->method('delete')->with($game);

        $response = $this->controller->delete(1, $this->ourGameRepository, $this->ourGameCrudService);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->delete(999, $this->ourGameRepository, $this->ourGameCrudService);
    }

    public function testUploadCoverStoresFileAndReturnsUpdatedGame(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $updated = (new OurGame('Die Again', 'die-again'))->setCoverImagePath('uploads/our_games/1/cover/x.jpg');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->once())->method('uploadCover')->willReturn($updated);

        $file = new UploadedFile(__FILE__, 'cover.jpg', 'image/jpeg', null, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadCover(
            1,
            $request,
            $this->ourGameRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('/uploads/our_games/1/cover/x.jpg', $data['coverImageUrl']);
    }

    public function testUploadCoverReturnsValidationErrorWhenNoFile(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->never())->method('uploadCover');

        $response = $this->controller->uploadCover(
            1,
            new Request(),
            $this->ourGameRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUploadCoverReturnsValidationErrorWhenFileExceedsUploadLimit(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->never())->method('uploadCover');

        // Файл больше upload_max_filesize — PHP кладёт в $_FILES ошибку без валидного tmp_name.
        $file = new UploadedFile(__FILE__, 'cover.jpg', 'image/jpeg', \UPLOAD_ERR_INI_SIZE, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadCover(
            1,
            $request,
            $this->ourGameRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('file', $data['errors']);
    }

    public function testRemoveScreenshotPassesUrlFromRequestBody(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->once())
            ->method('removeScreenshot')
            ->with($game, '/uploads/our_games/1/screenshots/a.jpg')
            ->willReturn($game);

        $request = new Request(
            content: json_encode(['url' => '/uploads/our_games/1/screenshots/a.jpg'], \JSON_THROW_ON_ERROR),
        );

        $this->controller->removeScreenshot(
            1,
            $request,
            $this->ourGameRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );
    }

    public function testUploadContentImageStoresFileAndReturnsUrl(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->once())
            ->method('uploadContentImage')
            ->willReturn('uploads/our_games/1/content/x.jpg');

        $file = new UploadedFile(__FILE__, 'inline.jpg', 'image/jpeg', null, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadContentImage(
            1,
            $request,
            $this->ourGameRepository,
            $this->imageUploadService,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('/uploads/our_games/1/content/x.jpg', $data['url']);
    }

    public function testUploadContentImageReturnsValidationErrorWhenNoFile(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->imageUploadService->expects($this->never())->method('uploadContentImage');

        $response = $this->controller->uploadContentImage(
            1,
            new Request(),
            $this->ourGameRepository,
            $this->imageUploadService,
        );

        self::assertSame(422, $response->getStatusCode());
    }
}
