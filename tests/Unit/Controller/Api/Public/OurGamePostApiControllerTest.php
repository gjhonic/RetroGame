<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\OurGamePostApiController;
use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Entity\OurGamePost;
use App\Entity\User;
use App\Repository\OurGamePostRepository;
use App\Service\OurGamePost\OurGamePostMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Мок OurGamePostRepository здесь используется и как стаб, и как мок (проверка аргументов). */
#[AllowMockObjectsWithoutExpectations]
class OurGamePostApiControllerTest extends TestCase
{
    private OurGamePostRepository&MockObject $ourGamePostRepository;
    private OurGamePostMapper $ourGamePostMapper;
    private OurGamePostApiController $controller;

    protected function setUp(): void
    {
        $this->ourGamePostRepository = $this->createMock(OurGamePostRepository::class);
        $this->ourGamePostMapper = new OurGamePostMapper();

        $this->controller = new OurGamePostApiController();
        $this->controller->setContainer(new Container());
    }

    private function makePost(): OurGamePost
    {
        return new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            new User('admin@retrogame.local', 'hash'),
            OurGamePostType::MajorUpdate,
            new \DateTimeImmutable('2026-03-01'),
            'Большое обновление вышло',
            'Большое обновление.',
            OurGameStatus::Published,
        );
    }

    public function testListReturnsPublishedPostsWithDefaultPagination(): void
    {
        $this->ourGamePostRepository->method('countPublishedForPublic')->willReturn(1);
        $this->ourGamePostRepository->expects($this->once())
            ->method('findPublishedForPublic')
            ->with([], 'postedAt', 'DESC', 20, 0)
            ->willReturn([$this->makePost()]);

        $response = $this->controller->list(new Request(), $this->ourGamePostRepository, $this->ourGamePostMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('major_update', $data['items'][0]['type']);
    }

    public function testListPassesGameFilterToRepository(): void
    {
        $this->ourGamePostRepository->method('countPublishedForPublic')->willReturn(0);
        $this->ourGamePostRepository->expects($this->once())
            ->method('findPublishedForPublic')
            ->with(['game' => '1'], 'postedAt', 'DESC', 20, 0)
            ->willReturn([]);

        $request = new Request(query: ['filters' => ['game' => '1']]);
        $this->controller->list($request, $this->ourGamePostRepository, $this->ourGamePostMapper);
    }

    public function testShowReturnsPublishedPostDetail(): void
    {
        $this->ourGamePostRepository->expects($this->once())
            ->method('findOnePublishedById')
            ->with(1)
            ->willReturn($this->makePost());

        $response = $this->controller->show(1, $this->ourGamePostRepository, $this->ourGamePostMapper);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testShowThrowsNotFoundExceptionForUnpublishedOrMissingPost(): void
    {
        $this->ourGamePostRepository->expects($this->once())
            ->method('findOnePublishedById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->show(999, $this->ourGamePostRepository, $this->ourGamePostMapper);
    }
}
