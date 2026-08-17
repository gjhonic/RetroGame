<?php

namespace App\Tests\Unit\Service\Game;

use App\Entity\Game;
use App\Entity\Genre;
use App\Service\Game\GameMapper;
use PHPUnit\Framework\TestCase;

class GameMapperTest extends TestCase
{
    private GameMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GameMapper();
    }

    public function testIsHiddenFromPublicReturnsFalseWhenGameHasNoHiddenGenre(): void
    {
        $game = $this->makeGame(['Экшены', 'Инди']);

        self::assertFalse($this->mapper->isHiddenFromPublic($game));
    }

    public function testIsHiddenFromPublicReturnsTrueWhenGameHasHiddenGenre(): void
    {
        $game = $this->makeGame(['Экшены', 'Сексуальный контент']);

        self::assertTrue($this->mapper->isHiddenFromPublic($game));
    }

    public function testToListItemComputesAvgPopularityAsPopularityPerYearSinceRelease(): void
    {
        $game = (new Game('Old Hit', 'old-hit'))
            ->setReleaseDate(new \DateTimeImmutable('-3 years'))
            ->setPopularity(3000);

        self::assertSame(1000.0, $this->mapper->toListItem($game)['avgPopularity']);
    }

    public function testToListItemAvgPopularityIsNullWithoutReleaseDate(): void
    {
        $game = (new Game('No Date', 'no-date'))->setPopularity(3000);

        self::assertNull($this->mapper->toListItem($game)['avgPopularity']);
    }

    /** @param array<int, string> $genreNames */
    private function makeGame(array $genreNames): Game
    {
        $game = new Game('Test Game', 'test-game');

        foreach ($genreNames as $genreName) {
            $game->addGenre(new Genre($genreName));
        }

        return $game;
    }
}
