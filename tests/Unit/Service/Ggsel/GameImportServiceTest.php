<?php

namespace App\Tests\Unit\Service\Ggsel;

use App\Entity\Game;
use App\Entity\GgselGame;
use App\Entity\GgselImportCursor;
use App\Repository\GgselGameRepository;
use App\Repository\GgselImportCursorRepository;
use App\Service\Ggsel\Exceptions\GgselApiException;
use App\Service\Ggsel\GameImportService;
use App\Service\Ggsel\GgselClient;
use App\Service\Ggsel\GgselProduct;
use App\Service\Ggsel\GgselSlugAttempt;
use App\Service\Ggsel\Interfaces\RateLimiterInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

/**
 * Мок-объекты здесь намеренно используются и как стабы (готовые ответы
 * GgselClient/репозиториев), и как моки (проверка persist/flush/delay) —
 * поэтому строгая проверка PHPUnit "мок без expects()" отключена, как и в
 * Steam\GameImportServiceTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GameImportServiceTest extends TestCase
{
    private GgselClient&MockObject $ggselClient;
    private EntityManagerInterface&MockObject $entityManager;
    private GgselGameRepository&MockObject $ggselGameRepository;
    private GgselImportCursorRepository&MockObject $cursorRepository;
    private SluggerInterface&MockObject $slugger;
    private RateLimiterInterface&MockObject $rateLimiter;
    private GameImportService $service;

    protected function setUp(): void
    {
        $this->ggselClient = $this->createMock(GgselClient::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->ggselGameRepository = $this->createMock(GgselGameRepository::class);
        $this->ggselGameRepository->method('findOneByGame')->willReturn(null);
        $this->cursorRepository = $this->createMock(GgselImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn(new GgselImportCursor());
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturn(new UnicodeString('half-life'));
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);

        $this->service = $this->newService();
    }

    /** Собирает сервис из текущих моков — для тестов, переопределяющих отдельные зависимости. */
    private function newService(): GameImportService
    {
        return new GameImportService(
            $this->ggselClient,
            $this->entityManager,
            $this->ggselGameRepository,
            $this->cursorRepository,
            $this->slugger,
            $this->rateLimiter,
        );
    }

    /** Создаёт Game с заданным id (обычно проставляется Doctrine при persist) — нужен курсору. */
    private static function makeGame(int $id, string $name, ?int $popularity = null): Game
    {
        $game = new Game($name, strtolower($name));
        $game->setPopularity($popularity);
        (new \ReflectionProperty($game, 'id'))->setValue($game, $id);

        return $game;
    }

    /** Ответ GgselClient::findBySlug() для найденного товара. */
    private static function found(string $url): GgselSlugAttempt
    {
        return new GgselSlugAttempt($url, new GgselProduct($url), 'найдено');
    }

    /** Ответ GgselClient::findBySlug() для ненайденного товара. */
    private static function notFound(string $url, string $reason = 'HTTP 200, нет данных о товаре'): GgselSlugAttempt
    {
        return new GgselSlugAttempt($url, null, $reason);
    }

    public function testImportNextBatchCreatesGgselGameWhenFoundByBaseSlug(): void
    {
        $game = self::makeGame(1, 'Half-Life', 100);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->ggselClient->expects($this->once())->method('findBySlug')
            ->with('half-life')
            ->willReturn(self::found('https://ggsel.net/catalog/half-life'));

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(GgselGame::class));
        $this->entityManager->expects($this->once())->method('flush');
        $this->rateLimiter->expects($this->never())->method('delay');

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(1, $result->checkedCount());
        self::assertSame(1, $result->foundCount());
        self::assertTrue($result->results[0]->isFound());
        self::assertSame('https://ggsel.net/catalog/half-life', $result->results[0]->ggselGame?->getUrl());
        self::assertCount(1, $result->results[0]->attempts);
    }

    public function testImportNextBatchTriesKeysSuffixWhenBaseSlugNotFound(): void
    {
        // Реальный случай: у Garry's Mod товар лежит на "garrys-mod-keys",
        // а не на голом "garrys-mod" (см. GameImportService::SLUG_SUFFIXES).
        $game = self::makeGame(1, "Garry's Mod", 100);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        // Свежий мок вместо переопределения slug() на общем — см. комментарий
        // в testImportNextBatchUpdatesExistingGgselGameUrlInsteadOfCreatingNew.
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturn(new UnicodeString('garrys-mod'));
        $this->service = $this->newService();

        $this->ggselClient->expects($this->exactly(2))->method('findBySlug')->willReturnMap([
            ['garrys-mod', self::notFound('https://ggsel.net/catalog/garrys-mod', 'HTTP 404')],
            ['garrys-mod-keys', self::found('https://ggsel.net/catalog/garrys-mod-keys')],
        ]);

        $result = $this->service->importNextBatch(20, 1500);

        self::assertTrue($result->results[0]->isFound());
        self::assertSame('https://ggsel.net/catalog/garrys-mod-keys', $result->results[0]->ggselGame?->getUrl());
        self::assertCount(2, $result->results[0]->attempts);
        self::assertSame('HTTP 404', $result->results[0]->attempts[0]->reason);
        self::assertSame('найдено', $result->results[0]->attempts[1]->reason);
    }

    public function testImportNextBatchReturnsOneResultPerGameInOrderWithMixedOutcomes(): void
    {
        $foundGame = self::makeGame(1, 'Found Game', 100);
        $notFoundGame = self::makeGame(2, 'Not Found Game', 50);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$foundGame, $notFoundGame]);

        // Свежий мок вместо переопределения slug() на общем — у общего уже
        // есть стаб из setUp() (см. комментарий в
        // testImportNextBatchUpdatesExistingGgselGameUrlInsteadOfCreatingNew).
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturnCallback(
            static fn (string $name): UnicodeString => new UnicodeString(strtolower($name)),
        );
        $this->service = $this->newService();

        $this->ggselClient->method('findBySlug')->willReturnCallback(
            static fn (string $slug): GgselSlugAttempt => $slug === 'found game'
                ? self::found('https://ggsel.net/catalog/found-game')
                : self::notFound('https://ggsel.net/catalog/' . str_replace(' ', '-', $slug)),
        );

        $result = $this->service->importNextBatch(20, 1500);

        self::assertCount(2, $result->results);
        self::assertSame($foundGame, $result->results[0]->game);
        self::assertTrue($result->results[0]->isFound());
        self::assertSame('https://ggsel.net/catalog/found-game', $result->results[0]->ggselGame?->getUrl());
        self::assertSame($notFoundGame, $result->results[1]->game);
        self::assertFalse($result->results[1]->isFound());
    }

    public function testImportNextBatchLeavesGameUncheckedResultWhenNoCandidateSlugMatches(): void
    {
        $game = self::makeGame(1, 'Unknown Game', 5);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        // Оба кандидата (базовый слаг и "-keys") пробуются и оба безуспешны.
        $this->ggselClient->expects($this->exactly(2))->method('findBySlug')
            ->willReturn(self::notFound('https://ggsel.net/catalog/unknown-game', 'HTTP 404'));

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(1, $result->checkedCount());
        self::assertSame(0, $result->foundCount());
        self::assertFalse($result->results[0]->isFound());
        self::assertNull($result->results[0]->ggselGame);
        self::assertSame($game, $result->results[0]->game);
        self::assertCount(2, $result->results[0]->attempts);
        self::assertSame('HTTP 404', $result->results[0]->attempts[0]->reason);
    }

    public function testImportNextBatchSkipsGameOnGgselApiExceptionAndContinuesWithRest(): void
    {
        $flakyGame = self::makeGame(1, 'Flaky Game', 100);
        $okGame = self::makeGame(2, 'OK Game', 50);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$flakyGame, $okGame]);

        $calls = 0;
        $this->ggselClient->method('findBySlug')->willReturnCallback(
            static function () use (&$calls): GgselSlugAttempt {
                ++$calls;

                if ($calls === 1) {
                    throw new GgselApiException('network error');
                }

                return self::found('https://ggsel.net/catalog/ok-game');
            },
        );

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(2, $result->checkedCount());
        self::assertSame(1, $result->foundCount());
        self::assertFalse($result->results[0]->isFound());
        self::assertCount(1, $result->results[0]->attempts);
        self::assertStringContainsString('ошибка запроса', $result->results[0]->attempts[0]->reason);
    }

    public function testImportNextBatchStopsTryingFurtherCandidateSlugsAfterFirstMatch(): void
    {
        $game = self::makeGame(1, 'Half-Life', 100);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->ggselClient->expects($this->once())->method('findBySlug')
            ->willReturn(self::found('https://ggsel.net/catalog/half-life'));

        $this->service->importNextBatch(20, 1500);
    }

    public function testImportNextBatchUpdatesExistingGgselGameUrlInsteadOfCreatingNew(): void
    {
        $game = self::makeGame(1, 'Half-Life', 100);
        $existing = new GgselGame($game, 'https://ggsel.net/catalog/old-slug');

        // Отдельный мок вместо переопределения findOneByGame на общем —
        // у общего уже есть стаб "null" из setUp(), и PHPUnit при повторном
        // stubbing того же метода без with() отдаёт приоритет первому
        // сработавшему совпадению, а не последнему добавленному.
        $this->ggselGameRepository = $this->createMock(GgselGameRepository::class);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);
        $this->ggselGameRepository->method('findOneByGame')->willReturn($existing);
        $this->service = $this->newService();

        $this->ggselClient->method('findBySlug')->willReturn(self::found('https://ggsel.net/catalog/new-slug'));

        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame('https://ggsel.net/catalog/new-slug', $result->results[0]->ggselGame?->getUrl());
        self::assertSame($existing, $result->results[0]->ggselGame);
    }

    public function testImportNextBatchDelaysBetweenItemsButNotAfterTheLastOne(): void
    {
        $game1 = self::makeGame(1, 'A', 10);
        $game2 = self::makeGame(2, 'B', 5);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game1, $game2]);
        $this->ggselClient->method('findBySlug')->willReturn(self::notFound('https://ggsel.net/catalog/x'));

        $this->rateLimiter->expects($this->once())->method('delay')->with(1500);

        $this->service->importNextBatch(20, 1500);
    }

    public function testImportNextBatchAdvancesCursorToLastCheckedGamePopularityAndId(): void
    {
        $cursor = new GgselImportCursor();
        $this->cursorRepository = $this->createMock(GgselImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game1 = self::makeGame(1, 'A', 10);
        $game2 = self::makeGame(2, 'B', 5);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game1, $game2]);
        $this->ggselClient->method('findBySlug')->willReturn(self::notFound('https://ggsel.net/catalog/x'));

        $this->service->importNextBatch(20, 1500);

        self::assertSame(5, $cursor->getLastPopularity());
        self::assertSame(2, $cursor->getLastGameId());
    }

    public function testImportNextBatchStoresPopularityAsMinusOneWhenLastGameHasNone(): void
    {
        $cursor = new GgselImportCursor();
        $this->cursorRepository = $this->createMock(GgselImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game = self::makeGame(1, 'No Popularity Game', null);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);
        $this->ggselClient->method('findBySlug')->willReturn(self::notFound('https://ggsel.net/catalog/x'));

        $this->service->importNextBatch(20, 1500);

        self::assertSame(-1, $cursor->getLastPopularity());
    }

    public function testImportNextBatchReturnsEmptyResultWhenNothingLeftToCheckEvenAfterWrap(): void
    {
        $cursor = (new GgselImportCursor())->setPosition(42, 7);
        $this->cursorRepository = $this->createMock(GgselImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(0, $result->checkedCount());
        self::assertTrue($result->wrapped);
        self::assertNull($cursor->getLastPopularity());
    }

    public function testImportNextBatchWrapsAndChecksGamesWhenBatchEmptyMidCursor(): void
    {
        $cursor = (new GgselImportCursor())->setPosition(42, 7);
        $this->cursorRepository = $this->createMock(GgselImportCursorRepository::class);
        $this->cursorRepository->method('getOrCreate')->willReturn($cursor);
        $this->service = $this->newService();

        $game = self::makeGame(1, 'A', 10);
        $this->ggselGameRepository->expects($this->exactly(2))->method('findGamesPendingCheck')
            ->willReturnOnConsecutiveCalls([], [$game]);
        $this->ggselClient->method('findBySlug')->willReturn(self::notFound('https://ggsel.net/catalog/x'));

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(1, $result->checkedCount());
        self::assertTrue($result->wrapped);
    }

    public function testImportNextBatchReturnsEmptyResultWithoutWrapWhenCursorAlreadyAtStart(): void
    {
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([]);

        $this->entityManager->expects($this->never())->method('flush');

        $result = $this->service->importNextBatch(20, 1500);

        self::assertSame(0, $result->checkedCount());
        self::assertFalse($result->wrapped);
    }

    public function testImportNextBatchSkipsClientCallWhenSluggerReturnsEmptyString(): void
    {
        $this->slugger = $this->createMock(SluggerInterface::class);
        $this->slugger->method('slug')->willReturn(new UnicodeString(''));
        $this->service = $this->newService();

        $game = self::makeGame(1, '', 10);
        $this->ggselGameRepository->method('findGamesPendingCheck')->willReturn([$game]);

        $this->ggselClient->expects($this->never())->method('findBySlug');

        $this->service->importNextBatch(20, 1500);
    }
}
