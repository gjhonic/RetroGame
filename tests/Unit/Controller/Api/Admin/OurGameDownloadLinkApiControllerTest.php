<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\OurGameDownloadLinkApiController;
use App\Entity\Enum\DownloadPlatform;
use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;
use App\Repository\OurGameDownloadLinkRepository;
use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameDownloadLinkCrudService;
use App\Service\OurGame\OurGameDownloadLinkRequestFactory;
use App\Service\OurGame\OurGameImageUploadService;
use App\Service\OurGame\OurGameMapper;
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

#[AllowMockObjectsWithoutExpectations]
class OurGameDownloadLinkApiControllerTest extends TestCase
{
    private OurGameRepository&MockObject $ourGameRepository;
    private OurGameDownloadLinkRepository&MockObject $downloadLinkRepository;
    private OurGameDownloadLinkCrudService&MockObject $downloadLinkCrudService;
    private OurGameImageUploadService&MockObject $imageUploadService;
    private OurGameMapper $ourGameMapper;
    private OurGameDownloadLinkRequestFactory $requestFactory;
    private OurGameDownloadLinkApiController $controller;

    protected function setUp(): void
    {
        $this->ourGameRepository = $this->createMock(OurGameRepository::class);
        $this->downloadLinkRepository = $this->createMock(OurGameDownloadLinkRepository::class);
        $this->downloadLinkCrudService = $this->createMock(OurGameDownloadLinkCrudService::class);
        $this->imageUploadService = $this->createMock(OurGameImageUploadService::class);
        $this->ourGameMapper = new OurGameMapper();

        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $this->requestFactory = new OurGameDownloadLinkRequestFactory($serializer, $validator);

        $this->controller = new OurGameDownloadLinkApiController();
        $this->controller->setContainer(new Container());
    }

    private function gameWithId(int $id): OurGame
    {
        $game = new OurGame('Die Again', 'die-again');
        $reflection = new \ReflectionProperty(OurGame::class, 'id');
        $reflection->setValue($game, $id);

        return $game;
    }

    public function testCreateReturnsCreatedLinkOnValidRequest(): void
    {
        $game = $this->gameWithId(1);
        $this->ourGameRepository->expects($this->once())->method('find')->with(1)->willReturn($game);

        $link = new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe');
        $this->downloadLinkCrudService->expects($this->once())->method('create')->willReturn($link);

        $request = new Request(content: json_encode([
            'platform' => 'windows',
            'url' => 'https://example.test/download.exe',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            1,
            $request,
            $this->requestFactory,
            $this->ourGameRepository,
            $this->downloadLinkCrudService,
            $this->ourGameMapper,
        );

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('windows', $data['platform']);
    }

    public function testCreateReturnsValidationErrorsForInvalidPayload(): void
    {
        $game = $this->gameWithId(1);
        $this->ourGameRepository->method('find')->willReturn($game);
        $this->downloadLinkCrudService->expects($this->never())->method('create');

        $request = new Request(content: json_encode(['platform' => 'ps5', 'url' => 'not-a-url'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            1,
            $request,
            $this->requestFactory,
            $this->ourGameRepository,
            $this->downloadLinkCrudService,
            $this->ourGameMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('platform', $data['errors']);
        self::assertArrayHasKey('url', $data['errors']);
    }

    public function testCreateThrowsNotFoundExceptionForUnknownGame(): void
    {
        $this->ourGameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->create(
            999,
            new Request(content: '{}'),
            $this->requestFactory,
            $this->ourGameRepository,
            $this->downloadLinkCrudService,
            $this->ourGameMapper,
        );
    }

    public function testUpdateReturnsUpdatedLink(): void
    {
        $game = $this->gameWithId(1);
        $link = new OurGameDownloadLink($game, DownloadPlatform::Web, 'https://example.test/old');
        $this->downloadLinkRepository->expects($this->once())->method('find')->with(10)->willReturn($link);

        $updated = new OurGameDownloadLink($game, DownloadPlatform::Linux, 'https://example.test/new');
        $this->downloadLinkCrudService->expects($this->once())->method('update')->willReturn($updated);

        $request = new Request(content: json_encode([
            'platform' => 'linux',
            'url' => 'https://example.test/new',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->update(
            1,
            10,
            $request,
            $this->requestFactory,
            $this->downloadLinkRepository,
            $this->downloadLinkCrudService,
            $this->ourGameMapper,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('linux', $data['platform']);
    }

    public function testUpdateThrowsNotFoundExceptionWhenLinkBelongsToAnotherGame(): void
    {
        $link = new OurGameDownloadLink($this->gameWithId(2), DownloadPlatform::Web, 'https://example.test/old');
        $this->downloadLinkRepository->method('find')->willReturn($link);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->update(
            1,
            10,
            new Request(content: '{}'),
            $this->requestFactory,
            $this->downloadLinkRepository,
            $this->downloadLinkCrudService,
            $this->ourGameMapper,
        );
    }

    public function testUploadImageStoresFileAndReturnsUpdatedLink(): void
    {
        $game = $this->gameWithId(1);
        $link = new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe');
        $this->downloadLinkRepository->expects($this->once())->method('find')->with(10)->willReturn($link);

        $updated = (new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe'))
            ->setImagePath('uploads/our_games/1/downloads/icon.png');
        $this->imageUploadService->expects($this->once())->method('uploadDownloadLinkImage')->willReturn($updated);

        $file = new UploadedFile(__FILE__, 'icon.png', 'image/png', null, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadImage(
            1,
            10,
            $request,
            $this->downloadLinkRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('/uploads/our_games/1/downloads/icon.png', $data['imageUrl']);
    }

    public function testUploadImageReturnsValidationErrorWhenNoFile(): void
    {
        $game = $this->gameWithId(1);
        $link = new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe');
        $this->downloadLinkRepository->method('find')->willReturn($link);
        $this->imageUploadService->expects($this->never())->method('uploadDownloadLinkImage');

        $response = $this->controller->uploadImage(
            1,
            10,
            new Request(),
            $this->downloadLinkRepository,
            $this->imageUploadService,
            $this->ourGameMapper,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testDeleteRemovesLinkAndReturns204(): void
    {
        $game = $this->gameWithId(1);
        $link = new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe');
        $this->downloadLinkRepository->expects($this->once())->method('find')->with(10)->willReturn($link);
        $this->downloadLinkCrudService->expects($this->once())->method('delete')->with($link);

        $response = $this->controller->delete(1, 10, $this->downloadLinkRepository, $this->downloadLinkCrudService);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteThrowsNotFoundExceptionForUnknownLink(): void
    {
        $this->downloadLinkRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->delete(1, 999, $this->downloadLinkRepository, $this->downloadLinkCrudService);
    }
}
