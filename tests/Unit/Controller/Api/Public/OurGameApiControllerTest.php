<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\OurGameApiController;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Repository\OurGameRepository;
use App\Service\OurGame\OurGameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Мок OurGameRepository здесь используется как стаб без проверки вызовов. */
#[AllowMockObjectsWithoutExpectations]
class OurGameApiControllerTest extends TestCase
{
    private OurGameRepository&MockObject $ourGameRepository;
    private OurGameMapper $ourGameMapper;
    private OurGameApiController $controller;

    protected function setUp(): void
    {
        $this->ourGameRepository = $this->createMock(OurGameRepository::class);
        $this->ourGameMapper = new OurGameMapper();

        $this->controller = new OurGameApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPublishedGamesFromRepository(): void
    {
        $game = new OurGame('Die Again', 'die-again', OurGameStatus::Published);
        $this->ourGameRepository->method('findPublishedForPublic')->willReturn([$game]);

        $response = $this->controller->list($this->ourGameRepository, $this->ourGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $data['items']);
        self::assertSame('Die Again', $data['items'][0]['name']);
    }

    public function testShowReturnsGameDetailForPublishedSlug(): void
    {
        $game = new OurGame('Die Again', 'die-again', OurGameStatus::Published);
        $this->ourGameRepository->expects($this->once())
            ->method('findOnePublishedBySlug')
            ->with('die-again')
            ->willReturn($game);

        $response = $this->controller->show('die-again', $this->ourGameRepository, $this->ourGameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('die-again', $data['slug']);
    }

    public function testShowThrowsNotFoundExceptionWhenGameNotFoundOrNotPublished(): void
    {
        $this->ourGameRepository->expects($this->once())
            ->method('findOnePublishedBySlug')
            ->with('missing')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->show('missing', $this->ourGameRepository, $this->ourGameMapper);
    }
}
