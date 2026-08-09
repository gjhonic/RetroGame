<?php

namespace App\Tests\Unit\Service\Steam;

use App\Entity\Enum\SteamGameStatus;
use App\Entity\Game;
use App\Entity\Interfaces\NamedEntityInterface;
use App\Entity\SteamGame;
use App\Entity\SteamImportCursor;
use App\Repository\GameRepository;
use App\Repository\SteamGameRepository;
use App\Repository\SteamImportCursorRepository;
use App\Service\Image\GameImageDownloader;
use App\Service\Steam\Exceptions\SteamApiException;
use App\Service\Steam\GameImportService;
use App\Service\Steam\Interfaces\RateLimiterInterface;
use App\Service\Steam\SteamClient;
use App\Service\Steam\SteamReleaseDateParser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

/**
 * Мок-объекты здесь намеренно используются и как стабы (готовые ответы
 * SteamClient/репозиториев), и как моки (проверка persist/flush/delay) —
 * поэтому строгая проверка PHPUnit "мок без expects()" отключена.
 */
#[AllowMockObjectsWithoutExpectations]
class GameImportServiceTest extends TestCase
{
    private SteamClient&MockObject $steamClient;
    private EntityManagerInterface&MockObject $entityManager;
    private GameRepository&MockObject $gameRepository;
    private SteamGameRepository&MockObject $steamGameRepository;
    private SteamImportCursorRepository&MockObject $cursorRepository;
    private SluggerInterface&MockObject $slugger;
    private RateLimiterInterface&MockObject $rateLimiter;
    private GameImageDownloader&MockObject $imageDownloader;
    private GameImportService $service;

    protected function setUp(): void
    {
        $this->steamClient = $this->createMock(SteamClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->steamGameRepository = $this->createMock(SteamGameRepository::class);
        $this->cursorRepository = $this->createMock(SteamImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new SteamImportCursor());
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);
        $this->imageDownloader = $this->createMock(GameImageDownloader::class);
        $this->imageDownloader->method('downloadCover')->willReturn(null);

        // Developer/Publisher/Genre/Platform ищутся через EntityManager::getRepository() —
        // по умолчанию "не найдено", чтобы findOrCreateNamed() создавал новые сущности.
        $namedEntityRepository = $this->createMock(EntityRepository::class);
        $namedEntityRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->method('getRepository')->willReturn($namedEntityRepository);

        $this->service = $this->newService();
    }

    /** Собирает сервис из текущих моков — для тестов, переопределяющих cursorRepository. */
    private function newService(): GameImportService
    {
        return new GameImportService(
            $this->steamClient,
            $this->entityManager,
            $this->gameRepository,
            $this->steamGameRepository,
            $this->cursorRepository,
            $this->slugger,
            $this->rateLimiter,
            $this->imageDownloader,
            new SteamReleaseDateParser(),
        );
    }

    public function testImportNextBatchCreatesNewGameAndSteamGameOnSuccess(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 10, 'name' => 'Half-Life']],
            'hasMore' => true,
            'lastAppId' => 10,
        ]);

        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('half-life'));
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'Half-Life',
            'short_description' => 'A sci-fi shooter',
            'header_image' => 'https://example.test/header.jpg',
            'metacritic' => ['score' => 96],
        ]);

        $persisted = [];
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        // Один flush из fetchAndStore и один — после сдвига курсора.
        $this->entityManager->expects($this->exactly(2))->method('flush');
        $this->rateLimiter->expects($this->never())->method('delay');

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertCount(1, $result->steamGames);
        self::assertTrue($result->hasMore);
        self::assertSame(10, $result->lastAppId);

        $steamGame = $result->steamGames[0];
        self::assertSame(SteamGameStatus::Success, $steamGame->getStatus());
        self::assertSame(10, $steamGame->getSteamAppId());
        self::assertSame(1, $steamGame->getAttempts());

        $game = $steamGame->getGame();
        self::assertSame('Half-Life', $game->getName());
        self::assertSame('half-life', $game->getSlug());
        self::assertSame('A sci-fi shooter', $game->getDescription());
        self::assertSame(96, $game->getMetacriticScore());

        self::assertInstanceOf(Game::class, $persisted[0]);
        self::assertInstanceOf(SteamGame::class, $persisted[1]);
    }

    public function testImportNextBatchReusesExistingSteamGameWithoutPersisting(): void
    {
        $existingGame = new Game('Old Name', 'old-name');
        $existingSteamGame = new SteamGame($existingGame, 20);

        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 20, 'name' => 'Old Name']],
            'hasMore' => false,
            'lastAppId' => 20,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn($existingSteamGame);

        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'New Name',
            'short_description' => null,
            'header_image' => null,
        ]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertSame($existingSteamGame, $result->steamGames[0]);
        self::assertSame('New Name', $existingGame->getName());
        self::assertNull($existingGame->getDescription());
    }

    public function testImportNextBatchMarksFailureWhenDetailsAreNull(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 30, 'name' => 'Delisted Game']],
            'hasMore' => false,
            'lastAppId' => 30,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('delisted-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->steamClient->method('fetchAppDetails')->willReturn(null);

        $result = $this->service->importNextBatch(5, 0, 1500);

        $steamGame = $result->steamGames[0];
        self::assertSame(SteamGameStatus::Failed, $steamGame->getStatus());
        self::assertSame(1, $steamGame->getAttempts());
        self::assertStringContainsString('success=false', (string) $steamGame->getLastError());
    }

    public function testImportNextBatchMarksFailureWhenSteamApiExceptionIsThrown(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 40, 'name' => 'Flaky Game']],
            'hasMore' => false,
            'lastAppId' => 40,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('flaky-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->steamClient->method('fetchAppDetails')
            ->willThrowException(new SteamApiException('Ошибка запроса к Steam appdetails (appid 40): timeout'));

        $result = $this->service->importNextBatch(5, 0, 1500);

        $steamGame = $result->steamGames[0];
        self::assertSame(SteamGameStatus::Failed, $steamGame->getStatus());
        self::assertSame(
            'Ошибка запроса к Steam appdetails (appid 40): timeout',
            $steamGame->getLastError(),
        );
    }

    public function testImportNextBatchReturnsEmptyResultWhenNoApps(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [],
            'hasMore' => false,
            'lastAppId' => 5,
        ]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->rateLimiter->expects($this->never())->method('delay');

        $result = $this->service->importNextBatch(5, 5, 1500);

        self::assertSame([], $result->steamGames);
        self::assertFalse($result->hasMore);
        self::assertSame(5, $result->lastAppId);
    }

    public function testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [
                ['appid' => 1, 'name' => 'Game One'],
                ['appid' => 2, 'name' => 'Game Two'],
                ['appid' => 3, 'name' => 'Game Three'],
            ],
            'hasMore' => false,
            'lastAppId' => 3,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn(null);

        $this->rateLimiter->expects($this->exactly(2))->method('delay')->with(777);

        $this->service->importNextBatch(3, 0, 777);
    }

    public function testImportNextBatchUsesPersistedCursorWhenLastAppIdIsNull(): void
    {
        $this->cursorRepository = $this->createMock(SteamImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new SteamImportCursor(999));
        $this->service = $this->newService();

        $this->steamClient->expects($this->once())->method('fetchGameAppList')
            ->with(5, 999)
            ->willReturn(['apps' => [], 'hasMore' => false, 'lastAppId' => 999]);

        $result = $this->service->importNextBatch(5, null, 1500);

        self::assertSame(999, $result->lastAppId);
    }

    public function testImportNextBatchIgnoresPersistedCursorWhenLastAppIdIsGivenExplicitly(): void
    {
        $this->cursorRepository = $this->createMock(SteamImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new SteamImportCursor(999));
        $this->service = $this->newService();

        $this->steamClient->expects($this->once())->method('fetchGameAppList')
            ->with(5, 42)
            ->willReturn(['apps' => [], 'hasMore' => false, 'lastAppId' => 42]);

        $this->service->importNextBatch(5, 42, 1500);
    }

    public function testImportNextBatchAdvancesCursorAfterSuccessfulBatch(): void
    {
        $cursor = new SteamImportCursor(0);
        $this->cursorRepository = $this->createMock(SteamImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 80, 'name' => 'Some Game']],
            'hasMore' => true,
            'lastAppId' => 80,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('some-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn(null);

        $this->service->importNextBatch(5, null, 1500);

        self::assertSame(80, $cursor->getLastAppId());
    }

    public function testImportNextBatchDoesNotAdvanceCursorWhenPageIsEmpty(): void
    {
        $cursor = new SteamImportCursor(50);
        $this->cursorRepository = $this->createMock(SteamImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [],
            'hasMore' => false,
            'lastAppId' => 50,
        ]);

        $this->service->importNextBatch(5, null, 1500);

        self::assertSame(50, $cursor->getLastAppId());
    }

    public function testImportNextBatchAppendsAppIdToSlugOnCollision(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 50, 'name' => 'Portal']],
            'hasMore' => false,
            'lastAppId' => 50,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('portal'));
        $this->gameRepository->method('findOneBy')
            ->willReturn(new Game('Portal (another edition)', 'portal'));
        $this->steamClient->method('fetchAppDetails')->willReturn(null);

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertSame('portal-50', $result->steamGames[0]->getGame()->getSlug());
    }

    public function testImportNextBatchFallsBackToAppIdWhenSluggerReturnsEmptyString(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 60, 'name' => '???']],
            'hasMore' => false,
            'lastAppId' => 60,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString(''));
        $this->steamClient->method('fetchAppDetails')->willReturn(null);

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertSame('-60', $result->steamGames[0]->getGame()->getSlug());
    }

    public function testImportNextBatchStoresDownloadedCoverImagePath(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 70, 'name' => 'Half-Life']],
            'hasMore' => false,
            'lastAppId' => 70,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('half-life'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'Half-Life',
            'header_image' => 'https://example.test/70/header.jpg',
        ]);

        $this->imageDownloader = $this->createMock(GameImageDownloader::class);
        $this->imageDownloader->expects($this->once())
            ->method('downloadCover')
            ->with('https://example.test/70/header.jpg', 70)
            ->willReturn('uploads/games/70.jpg');
        $this->service = $this->newService();

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertSame('uploads/games/70.jpg', $result->steamGames[0]->getGame()->getCoverImagePath());
    }

    public function testImportNextBatchLeavesCoverImagePathNullWhenNoHeaderImage(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 71, 'name' => 'No Cover Game']],
            'hasMore' => false,
            'lastAppId' => 71,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('no-cover-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'No Cover Game',
            'header_image' => null,
        ]);

        $this->imageDownloader = $this->createMock(GameImageDownloader::class);
        $this->imageDownloader->expects($this->never())->method('downloadCover');
        $this->service = $this->newService();

        $result = $this->service->importNextBatch(5, 0, 1500);

        self::assertNull($result->steamGames[0]->getGame()->getCoverImagePath());
    }

    public function testImportNextBatchExtractsDevelopersPublishersGenresPlatformsAndScreenshots(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 300, 'name' => 'Day of Defeat']],
            'hasMore' => false,
            'lastAppId' => 300,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('day-of-defeat'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'Day of Defeat',
            'developers' => ['Valve'],
            'publishers' => ['Valve'],
            'genres' => [['id' => '1', 'description' => 'Экшены']],
            'categories' => [['id' => '1', 'description' => 'Для нескольких игроков']],
            'platforms' => ['windows' => true, 'mac' => false, 'linux' => true],
            'screenshots' => [
                [
                    'id' => 0,
                    'path_thumbnail' => 'https://example.test/thumb.jpg',
                    'path_full' => 'https://example.test/full.jpg',
                ],
            ],
            'release_date' => ['coming_soon' => false, 'date' => '12 июл. 2010 г.'],
        ]);

        $result = $this->service->importNextBatch(5, 0, 1500);

        $game = $result->steamGames[0]->getGame();
        self::assertSame(['Valve'], self::names($game->getDevelopers()->toArray()));
        self::assertSame(['Valve'], self::names($game->getPublishers()->toArray()));
        self::assertSame(['Экшены'], self::names($game->getGenres()->toArray()));
        self::assertSame(['Windows', 'Linux'], self::names($game->getPlatforms()->toArray()));
        self::assertSame(['https://example.test/full.jpg'], $game->getScreenshotUrls());
        self::assertEquals(new \DateTimeImmutable('2010-07-12'), $game->getReleaseDate());
    }

    public function testImportNextBatchLeavesNewFieldsNullWhenAbsentFromResponse(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 301, 'name' => 'Minimal Game']],
            'hasMore' => false,
            'lastAppId' => 301,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('minimal-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn(['name' => 'Minimal Game']);

        $result = $this->service->importNextBatch(5, 0, 1500);

        $game = $result->steamGames[0]->getGame();
        self::assertCount(0, $game->getDevelopers());
        self::assertCount(0, $game->getPublishers());
        self::assertCount(0, $game->getGenres());
        self::assertCount(0, $game->getPlatforms());
        self::assertNull($game->getScreenshotUrls());
        self::assertNull($game->getReleaseDate());
        self::assertNull($game->getPopularity());
    }

    public function testImportNextBatchExtractsPopularityFromRecommendationsTotal(): void
    {
        $this->steamClient->method('fetchGameAppList')->willReturn([
            'apps' => [['appid' => 302, 'name' => 'Popular Game']],
            'hasMore' => false,
            'lastAppId' => 302,
        ]);
        $this->steamGameRepository->method('findOneBySteamAppId')->willReturn(null);
        $this->slugger->method('slug')->willReturn(new UnicodeString('popular-game'));
        $this->gameRepository->method('findOneBy')->willReturn(null);
        $this->steamClient->method('fetchAppDetails')->willReturn([
            'name' => 'Popular Game',
            'recommendations' => ['total' => 169229],
        ]);

        $result = $this->service->importNextBatch(5, 0, 1500);

        $game = $result->steamGames[0]->getGame();
        self::assertSame(169229, $game->getPopularity());
    }

    /**
     * @param array<int, NamedEntityInterface> $entities
     *
     * @return array<int, string>
     */
    private static function names(array $entities): array
    {
        return array_values(array_map(
            static fn (NamedEntityInterface $entity): string => $entity->getName(),
            $entities,
        ));
    }
}
