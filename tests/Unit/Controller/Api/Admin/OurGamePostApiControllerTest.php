<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\OurGamePostApiController;
use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Entity\OurGamePost;
use App\Entity\User;
use App\Repository\OurGamePostRepository;
use App\Service\OurGamePost\Exceptions\OurGameNotFoundException;
use App\Service\OurGamePost\OurGamePostCrudService;
use App\Service\OurGamePost\OurGamePostImageUploadService;
use App\Service\OurGamePost\OurGamePostMapper;
use App\Service\OurGamePost\OurGamePostRequestFactory;
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
 * Моки OurGamePostRepository/OurGamePostCrudService/OurGamePostImageUploadService здесь
 * и как стабы, и как моки (проверка вызовов) — строгая проверка "мок без expects()"
 * отключена, как и в OurGameApiControllerTest. OurGamePostRequestFactory собран из
 * реального Serializer+Validator, т.к. это простой класс без побочных эффектов.
 */
#[AllowMockObjectsWithoutExpectations]
class OurGamePostApiControllerTest extends TestCase
{
    private OurGamePostRepository&MockObject $ourGamePostRepository;
    private OurGamePostCrudService&MockObject $ourGamePostCrudService;
    private OurGamePostImageUploadService&MockObject $imageUploadService;
    private OurGamePostMapper $ourGamePostMapper;
    private OurGamePostRequestFactory $requestFactory;
    private OurGamePostApiController $controller;
    private User $user;

    protected function setUp(): void
    {
        $this->ourGamePostRepository = $this->createMock(OurGamePostRepository::class);
        $this->ourGamePostCrudService = $this->createMock(OurGamePostCrudService::class);
        $this->imageUploadService = $this->createMock(OurGamePostImageUploadService::class);
        $this->ourGamePostMapper = new OurGamePostMapper();
        $this->user = new User('admin@retrogame.local', 'hash');

        $serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $this->requestFactory = new OurGamePostRequestFactory($serializer, $validator);

        $this->controller = new OurGamePostApiController();
        $this->controller->setContainer(new Container());
    }

    private function makePost(): OurGamePost
    {
        return new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            $this->user,
            OurGamePostType::MajorUpdate,
            new \DateTimeImmutable('2026-03-01'),
            'Большое обновление вышло',
            'Большое обновление.',
            OurGameStatus::Published,
        );
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $this->ourGamePostRepository->method('countForAdminList')->willReturn(1);
        $this->ourGamePostRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'postedAt', 'DESC', 25, 0)
            ->willReturn([$this->makePost()]);

        $response = $this->controller->list(new Request(), $this->ourGamePostRepository, $this->ourGamePostMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('major_update', $data['items'][0]['type']);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->ourGamePostRepository->method('countForAdminList')->willReturn(0);
        $this->ourGamePostRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['game' => '1', 'type' => 'info'], 'game', 'ASC', 10, 0)
            ->willReturn([]);

        $request = new Request(query: [
            'filters' => ['game' => '1', 'type' => 'info'],
            'sortBy' => 'game',
            'sortDir' => 'asc',
            'perPage' => '10',
        ]);

        $this->controller->list($request, $this->ourGamePostRepository, $this->ourGamePostMapper);
    }

    public function testCreateReturnsCreatedPostOnValidRequest(): void
    {
        $this->ourGamePostCrudService->expects($this->once())
            ->method('create')
            ->with($this->user)
            ->willReturn($this->makePost());

        $request = new Request(content: json_encode([
            'gameId' => 1,
            'type' => 'major_update',
            'status' => 'published',
            'postedAt' => '2026-03-01',
            'title' => 'Большое обновление вышло',
            'shortDescription' => 'Большое обновление.',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->requestFactory,
            $this->ourGamePostCrudService,
            $this->ourGamePostMapper,
            $this->user,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateReturnsValidationErrorForBlankShortDescription(): void
    {
        $this->ourGamePostCrudService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'gameId' => 1,
            'type' => 'info',
            'status' => 'draft',
            'postedAt' => '2026-03-01',
            'title' => 'Анонс',
            'shortDescription' => '',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->requestFactory,
            $this->ourGamePostCrudService,
            $this->ourGamePostMapper,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateReturnsValidationErrorWhenGameNotFound(): void
    {
        $this->ourGamePostCrudService->method('create')
            ->willThrowException(new OurGameNotFoundException('Игра не найдена.'));

        $request = new Request(content: json_encode([
            'gameId' => 999,
            'type' => 'info',
            'status' => 'draft',
            'postedAt' => '2026-03-01',
            'title' => 'Анонс',
            'shortDescription' => 'Анонс.',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->requestFactory,
            $this->ourGamePostCrudService,
            $this->ourGamePostMapper,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('gameId', $data['errors']);
    }

    public function testShowReturnsPostDetail(): void
    {
        $this->ourGamePostRepository->expects($this->once())->method('find')->with(1)->willReturn($this->makePost());

        $response = $this->controller->show(1, $this->ourGamePostRepository, $this->ourGamePostMapper);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->show(999, $this->ourGamePostRepository, $this->ourGamePostMapper);
    }

    public function testUpdateSavesPostAndReturnsUpdatedData(): void
    {
        $post = $this->makePost();
        $this->ourGamePostRepository->expects($this->once())->method('find')->with(1)->willReturn($post);
        $this->ourGamePostCrudService->expects($this->once())->method('update')->with($post)->willReturn($post);

        $request = new Request(content: json_encode([
            'gameId' => 1,
            'type' => 'info',
            'status' => 'draft',
            'postedAt' => '2026-03-05',
            'title' => 'Обновлённый анонс',
            'shortDescription' => 'Обновлённый анонс.',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->update(
            1,
            $request,
            $this->requestFactory,
            $this->ourGamePostRepository,
            $this->ourGamePostCrudService,
            $this->ourGamePostMapper,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->update(
            999,
            new Request(),
            $this->requestFactory,
            $this->ourGamePostRepository,
            $this->ourGamePostCrudService,
            $this->ourGamePostMapper,
        );
    }

    public function testDeleteRemovesPostAndReturns204(): void
    {
        $post = $this->makePost();
        $this->ourGamePostRepository->expects($this->once())->method('find')->with(1)->willReturn($post);
        $this->ourGamePostCrudService->expects($this->once())->method('delete')->with($post);

        $response = $this->controller->delete(1, $this->ourGamePostRepository, $this->ourGamePostCrudService);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->delete(999, $this->ourGamePostRepository, $this->ourGamePostCrudService);
    }

    public function testUploadImageStoresFileAndReturnsUpdatedPost(): void
    {
        $post = $this->makePost();
        $updated = $this->makePost()->setImagePath('uploads/our_game_posts/1/image/x.jpg');
        $this->ourGamePostRepository->method('find')->willReturn($post);
        $this->imageUploadService->expects($this->once())->method('upload')->willReturn($updated);

        $file = new UploadedFile(__FILE__, 'post.jpg', 'image/jpeg', null, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadImage(
            1,
            $request,
            $this->ourGamePostRepository,
            $this->imageUploadService,
            $this->ourGamePostMapper,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('/uploads/our_game_posts/1/image/x.jpg', $data['imageUrl']);
    }

    public function testUploadImageReturnsValidationErrorWhenNoFile(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn($this->makePost());
        $this->imageUploadService->expects($this->never())->method('upload');

        $response = $this->controller->uploadImage(
            1,
            new Request(),
            $this->ourGamePostRepository,
            $this->imageUploadService,
            $this->ourGamePostMapper,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUploadContentImageStoresFileAndReturnsUrl(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn($this->makePost());
        $this->imageUploadService->expects($this->once())
            ->method('uploadContentImage')
            ->willReturn('uploads/our_game_posts/1/content/x.jpg');

        $file = new UploadedFile(__FILE__, 'post.jpg', 'image/jpeg', null, true);
        $request = new Request(files: ['file' => $file]);

        $response = $this->controller->uploadContentImage(
            1,
            $request,
            $this->ourGamePostRepository,
            $this->imageUploadService,
        );

        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('/uploads/our_game_posts/1/content/x.jpg', $data['url']);
    }

    public function testUploadContentImageReturnsValidationErrorWhenNoFile(): void
    {
        $this->ourGamePostRepository->method('find')->willReturn($this->makePost());
        $this->imageUploadService->expects($this->never())->method('uploadContentImage');

        $response = $this->controller->uploadContentImage(
            1,
            new Request(),
            $this->ourGamePostRepository,
            $this->imageUploadService,
        );

        self::assertSame(422, $response->getStatusCode());
    }
}
