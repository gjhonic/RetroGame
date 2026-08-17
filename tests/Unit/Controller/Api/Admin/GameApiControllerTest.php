<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\GameApiController;
use App\Entity\Developer;
use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Platform;
use App\Entity\Publisher;
use App\Repository\GameRepository;
use App\Service\Game\GameMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок GameRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в GameApiControllerTest (публичный API).
 */
#[AllowMockObjectsWithoutExpectations]
class GameApiControllerTest extends TestCase
{
    private GameRepository&MockObject $gameRepository;
    private GameMapper $gameMapper;
    private GameApiController $controller;

    protected function setUp(): void
    {
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->gameMapper = new GameMapper();

        $this->controller = new GameApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $gameWithCover = (new Game('Half-Life', 'half-life'))
            ->setDescription('A sci-fi shooter')
            ->setCoverImagePath('uploads/games/1.jpg')
            ->setMetacriticScore(96)
            ->setReleaseDate(new \DateTimeImmutable('1998-11-19'));
        $gameWithCover->addDeveloper(new Developer('Valve'));

        $this->gameRepository->method('countForAdminList')->willReturn(1);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([$gameWithCover]);

        $response = $this->controller->list(new Request(), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
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
            'avgPopularity' => null,
            'releaseYear' => '1998',
            'developers' => ['Valve'],
            'publishers' => [],
            'genres' => [],
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['name' => 'half', 'developer' => 'valve'], 'metacriticScore', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['name' => ' half ', 'developer' => ' valve ', 'unknownField' => 'ignored'],
            'sortBy' => 'metacriticScore',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListSortsByDevelopersOrPublishersWhenRequested(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'publishers', 'ASC', 25, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'publishers']);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListFallsBackToNameSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(0);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->gameRepository, $this->gameMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->gameRepository->method('countForAdminList')->willReturn(1);
        $this->gameRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'name', 'ASC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(new Request(['page' => '999']), $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetailWithRelatedEntityNames(): void
    {
        $game = (new Game('Day of Defeat', 'day-of-defeat'))
            ->setDescription('Team-based shooter')
            ->setRating(4.5)
            ->setMetacriticScore(80)
            ->setReleaseDate(new \DateTimeImmutable('2003-05-01'))
            ->setScreenshotUrls(['https://example.test/screenshot.jpg']);
        $game->addDeveloper(new Developer('Valve'));
        $game->addPublisher(new Publisher('Valve'));
        $game->addGenre(new Genre('Экшены'));
        $game->addPlatform(new Platform('Windows'));

        $this->gameRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($game);

        $response = $this->controller->show(42, $this->gameRepository, $this->gameMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('Day of Defeat', $data['name']);
        self::assertSame(['Valve'], $data['developers']);
        self::assertSame(['Экшены'], $data['genres']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->gameRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->gameRepository, $this->gameMapper);
    }
}
