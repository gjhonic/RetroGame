<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\StatsApiController;
use App\Repository\DeveloperRepository;
use App\Repository\GameRepository;
use App\Repository\GenreRepository;
use App\Repository\PublisherRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * Моки GameRepository/DeveloperRepository/PublisherRepository здесь — только
 * стабы (без expects()), GenreRepository — и стаб, и мок (проверка аргументов
 * findForAdminList) — строгая проверка "мок без expects()" отключена, как в
 * остальных Admin-контроллерах.
 */
#[AllowMockObjectsWithoutExpectations]
class StatsApiControllerTest extends TestCase
{
    public function testIndexReturnsTotalsAndAggregatedStats(): void
    {
        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->method('countAll')->willReturn(370);
        $gameRepository->method('findGamesCountByReleaseYear')->willReturn([
            ['year' => 2007, 'count' => 3],
            ['year' => 2008, 'count' => 5],
        ]);
        $gameRepository->method('findScoreDistribution')->willReturn([
            ['label' => '90–100', 'count' => 12],
            ['label' => 'Без оценки', 'count' => 40],
        ]);

        $genreRepository = $this->createMock(GenreRepository::class);
        $genreRepository->method('countForAdminList')->willReturn(13);
        $genreRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'gamesCount', 'DESC', 6, 0)
            ->willReturn([
                ['id' => 8, 'name' => 'Казуальные игры', 'gamesCount' => 83],
                ['id' => 5, 'name' => 'Инди', 'gamesCount' => 50],
            ]);

        $developerRepository = $this->createMock(DeveloperRepository::class);
        $developerRepository->method('countForAdminList')->willReturn(230);

        $publisherRepository = $this->createMock(PublisherRepository::class);
        $publisherRepository->method('countForAdminList')->willReturn(110);

        $controller = new StatsApiController();
        $controller->setContainer(new Container());

        $response = $controller->index($gameRepository, $genreRepository, $developerRepository, $publisherRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(
            ['games' => 370, 'genres' => 13, 'developers' => 230, 'publishers' => 110],
            $data['totals'],
        );
        self::assertSame(
            [['year' => 2007, 'count' => 3], ['year' => 2008, 'count' => 5]],
            $data['gamesByYear'],
        );
        self::assertSame(
            [['name' => 'Казуальные игры', 'count' => 83], ['name' => 'Инди', 'count' => 50]],
            $data['topGenres'],
        );
        self::assertSame(
            [['label' => '90–100', 'count' => 12], ['label' => 'Без оценки', 'count' => 40]],
            $data['scoreDistribution'],
        );
    }
}
