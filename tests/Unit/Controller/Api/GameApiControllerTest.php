<?php

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\GameApiController;
use App\Entity\Developer;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Repository\GameRepository;
use App\Repository\GenreRepository;
use App\Repository\PlatformRepository;
use App\Service\Game\GameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок GameRepository здесь и как стаб (готовые ответы findForPublicCatalog/findOneBy/countForPublicCatalog),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в GameImportServiceTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GameApiControllerTest extends TestCase
{
    private GameRepository&MockObject $gameRepository;
    private GenreRepository&MockObject $genreRepository;
    private PlatformRepository&MockObject $platformRepository;
    private GameMapper $gameMapper;
    private GameApiController $controller;

    protected function setUp(): void
    {
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->genreRepository = $this->createMock(GenreRepository::class);
        $this->platformRepository = $this->createMock(PlatformRepository::class);
        $this->gameMapper = new GameMapper();

        $this->controller = new GameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsNormalizedItemsAndPagination(): void
    {
        $gameWithCover = (new Game('Half-Life', 'half-life'))
            ->setDescription('A sci-fi shooter')
            ->setCoverImagePath('uploads/games/1.jpg')
            ->setMetacriticScore(96)
            ->setReleaseDate(new \DateTimeImmutable('1998-11-19'));

        $gameWithoutCover = new Game('No Cover Game', 'no-cover-game');

        $this->gameRepository->method('countForPublicCatalog')->willReturn(2);
        $this->gameRepository->expects($this->once())
            ->method('findForPublicCatalog')
            ->with([], 'popularity', 'DESC', 24, 0)
            ->willReturn([$gameWithCover, $gameWithoutCover]);

        $response = $this->controller->list(new Request(), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(2, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'name' => 'Half-Life',
            'slug' => 'half-life',
            'coverImageUrl' => '/uploads/games/1.jpg',
            'description' => 'A sci-fi shooter',
            'metacriticScore' => 96,
            'popularity' => null,
            'releaseYear' => '1998',
        ], $data['items'][0]);
        self::assertNull($data['items'][1]['coverImageUrl']);
    }

    public function testListDefaultsToFirstPageWhenPageIsMissing(): void
    {
        $this->gameRepository->method('countForPublicCatalog')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForPublicCatalog')
            ->with([], 'popularity', 'DESC', 24, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame([], $data['items']);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->gameRepository->method('countForPublicCatalog')->willReturn(1);
        $this->gameRepository->expects($this->once())
            ->method('findForPublicCatalog')
            ->with([], 'popularity', 'DESC', 24, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->gameRepository->method('countForPublicCatalog')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForPublicCatalog')
            ->with(
                ['name' => 'half', 'genre' => '1', 'releaseYearFrom' => '1998'],
                'releaseYear',
                'ASC',
                24,
                0,
            )
            ->willReturn([]);

        $request = new Request([
            'filters' => [
                'name' => ' half ',
                'genre' => ' 1 ',
                'releaseYearFrom' => ' 1998 ',
                'unknownField' => 'ignored',
            ],
            'sortBy' => 'releaseYear',
            'sortDir' => 'asc',
        ]);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListFallsBackToDefaultSortForUnknownSortField(): void
    {
        $this->gameRepository->method('countForPublicCatalog')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForPublicCatalog')
            ->with([], 'popularity', 'DESC', 24, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'sortDir' => 'unknownDirection']);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testFiltersReturnsGenresPlatformsAndReleaseYearRange(): void
    {
        $this->genreRepository->expects($this->once())
            ->method('findBy')
            ->with([], ['name' => 'ASC'])
            ->willReturn([(new Genre('Экшены'))]);
        $this->platformRepository->expects($this->once())
            ->method('findBy')
            ->with([], ['name' => 'ASC'])
            ->willReturn([(new Platform('Windows'))]);
        $this->gameRepository->method('findPublicReleaseYearRange')->willReturn(['min' => 1998, 'max' => 2024]);

        $response = $this->controller->filters(
            $this->gameRepository,
            $this->genreRepository,
            $this->platformRepository,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame([['id' => null, 'name' => 'Экшены']], $data['genres']);
        self::assertSame([['id' => null, 'name' => 'Windows']], $data['platforms']);
        self::assertSame(1998, $data['releaseYearMin']);
        self::assertSame(2024, $data['releaseYearMax']);
    }

    public function testFiltersReturnsNullReleaseYearRangeWhenNoGamesHaveReleaseDate(): void
    {
        $this->genreRepository->method('findBy')->willReturn([]);
        $this->platformRepository->method('findBy')->willReturn([]);
        $this->gameRepository->method('findPublicReleaseYearRange')->willReturn(null);

        $response = $this->controller->filters(
            $this->gameRepository,
            $this->genreRepository,
            $this->platformRepository,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertNull($data['releaseYearMin']);
        self::assertNull($data['releaseYearMax']);
    }

    public function testShowReturnsFullDetailWithRelatedEntityNames(): void
    {
        $game = (new Game('Day of Defeat', 'day-of-defeat'))
            ->setDescription('Team-based shooter')
            ->setRating(4.5)
            ->setMetacriticScore(80)
            ->setPopularity(1234)
            ->setReleaseDate(new \DateTimeImmutable('2003-05-01'))
            ->setScreenshotUrls(['https://example.test/screenshot.jpg']);
        $game->addDeveloper(new Developer('Valve'));
        $game->addPublisher(new Publisher('Valve'));
        $game->addGenre(new Genre('Экшены'));
        $game->addPlatform(new Platform('Windows'));

        $this->gameRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'day-of-defeat'])
            ->willReturn($game);

        $response = $this->controller->show('day-of-defeat', $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Day of Defeat', $data['name']);
        self::assertSame(['https://example.test/screenshot.jpg'], $data['screenshotUrls']);
        self::assertSame(4.5, $data['rating']);
        self::assertSame(1234, $data['popularity']);
        self::assertSame('2003-05-01', $data['releaseDate']);
        self::assertSame(['Valve'], $data['developers']);
        self::assertSame(['Valve'], $data['publishers']);
        self::assertSame(['Экшены'], $data['genres']);
        self::assertSame(['Windows'], $data['platforms']);
    }

    public function testShowNormalizesMissingScreenshotUrlsToEmptyArray(): void
    {
        $game = new Game('Minimal Game', 'minimal-game');
        $this->gameRepository->method('findOneBy')->willReturn($game);

        $response = $this->controller->show('minimal-game', $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame([], $data['screenshotUrls']);
        self::assertNull($data['coverImageUrl']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show('unknown-slug', $this->gameRepository, $this->gameMapper);
    }
}
