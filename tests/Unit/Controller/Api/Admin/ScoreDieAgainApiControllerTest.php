<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\ScoreDieAgainApiController;
use App\Entity\ScoreDieAgain;
use App\Repository\ScoreDieAgainRepository;
use App\Service\ScoreDieAgain\ScoreDieAgainMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * Мок ScoreDieAgainRepository здесь и как стаб (готовые ответы countAll/findForLeaderboard),
 * и как мок (проверка аргументов сортировки/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в DeveloperApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ScoreDieAgainApiControllerTest extends TestCase
{
    private ScoreDieAgainRepository&MockObject $scoreDieAgainRepository;
    private ScoreDieAgainMapper $scoreDieAgainMapper;
    private ScoreDieAgainApiController $controller;

    protected function setUp(): void
    {
        $this->scoreDieAgainRepository = $this->createMock(ScoreDieAgainRepository::class);
        $this->scoreDieAgainMapper = new ScoreDieAgainMapper();

        $this->controller = new ScoreDieAgainApiController();
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $score = new ScoreDieAgain('Player1', 3, 120, 5);

        $this->scoreDieAgainRepository->method('countAll')->willReturn(1);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('kills', 'DESC', 25, 0)
            ->willReturn([$score]);

        $response = $this->controller->list(
            new Request(),
            $this->scoreDieAgainRepository,
            $this->scoreDieAgainMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame('Player1', $data['items'][0]['nickname']);
        self::assertSame(5, $data['items'][0]['kills']);
    }

    public function testListPassesSortingAndPerPageToRepository(): void
    {
        $this->scoreDieAgainRepository->method('countAll')->willReturn(0);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('survivedSeconds', 'ASC', 10, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'survivedSeconds', 'sortDir' => 'asc', 'perPage' => '10']);
        $this->controller->list($request, $this->scoreDieAgainRepository, $this->scoreDieAgainMapper);
    }

    public function testListClampsPerPageToMax(): void
    {
        $this->scoreDieAgainRepository->method('countAll')->willReturn(0);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('kills', 'DESC', 100, 0)
            ->willReturn([]);

        $request = new Request(['perPage' => '9999']);
        $this->controller->list($request, $this->scoreDieAgainRepository, $this->scoreDieAgainMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->scoreDieAgainRepository->method('countAll')->willReturn(1);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('kills', 'DESC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(
            new Request(['page' => '999']),
            $this->scoreDieAgainRepository,
            $this->scoreDieAgainMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
    }

    public function testResetDeletesAllResultsAndReturnsDeletedCount(): void
    {
        $this->scoreDieAgainRepository->expects($this->once())->method('deleteAll')->willReturn(20);

        $response = $this->controller->reset($this->scoreDieAgainRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(20, $data['deleted']);
    }
}
