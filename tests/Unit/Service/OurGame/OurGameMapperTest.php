<?php

namespace App\Tests\Unit\Service\OurGame;

use App\Entity\Enum\DownloadPlatform;
use App\Entity\Enum\OurGameStatus;
use App\Entity\Genre;
use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;
use App\Service\OurGame\OurGameMapper;
use PHPUnit\Framework\TestCase;

class OurGameMapperTest extends TestCase
{
    private OurGameMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OurGameMapper();
    }

    public function testToAdminListItemMapsFieldsAndGenreNames(): void
    {
        $game = (new OurGame('Die Again', 'die-again', OurGameStatus::Published))
            ->setCoverImagePath('uploads/our_games/1/cover/abc.jpg')
            ->setCurrentVersion('1.0.0')
            ->setReleaseDate(new \DateTimeImmutable('2026-01-15'));
        $game->addGenre(new Genre('Roguelike'));

        $item = $this->mapper->toAdminListItem($game);

        self::assertSame('Die Again', $item['name']);
        self::assertSame('die-again', $item['slug']);
        self::assertSame('published', $item['status']);
        self::assertSame('/uploads/our_games/1/cover/abc.jpg', $item['coverImageUrl']);
        self::assertSame('1.0.0', $item['currentVersion']);
        self::assertSame('2026-01-15', $item['releaseDate']);
        self::assertSame(['Roguelike'], $item['genres']);
    }

    public function testToAdminListItemReturnsNullCoverImageUrlWhenNotSet(): void
    {
        $game = new OurGame('Die Again', 'die-again');

        $item = $this->mapper->toAdminListItem($game);

        self::assertNull($item['coverImageUrl']);
        self::assertSame('draft', $item['status']);
    }

    public function testToDetailIncludesScreenshotsGenreIdsAndDownloadLinks(): void
    {
        $game = (new OurGame('Die Again', 'die-again'))
            ->setDescription('A roguelike survival game.')
            ->setBannerImagePath('uploads/our_games/1/banner/x.jpg')
            ->setScreenshotUrls(['uploads/our_games/1/screenshots/a.jpg'])
            ->setTrailerUrl('https://youtube.com/watch?v=abc');

        $genre = new Genre('Roguelike');
        $game->addGenre($genre);

        $link = new OurGameDownloadLink($game, DownloadPlatform::Windows, 'https://example.test/download.exe');
        $game->getDownloadLinks()->add($link);

        $detail = $this->mapper->toDetail($game);

        self::assertSame('A roguelike survival game.', $detail['description']);
        self::assertSame('/uploads/our_games/1/banner/x.jpg', $detail['bannerImageUrl']);
        self::assertSame(['/uploads/our_games/1/screenshots/a.jpg'], $detail['screenshotUrls']);
        self::assertSame('https://youtube.com/watch?v=abc', $detail['trailerUrl']);
        self::assertSame([$genre->getId()], $detail['genreIds']);
        self::assertCount(1, $detail['downloadLinks']);
        self::assertSame('windows', $detail['downloadLinks'][0]['platform']);
        self::assertSame('https://example.test/download.exe', $detail['downloadLinks'][0]['url']);
    }

    public function testToDownloadLinkItemMapsFields(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $link = (new OurGameDownloadLink($game, DownloadPlatform::Android, 'https://example.test/app.apk'))
            ->setImagePath('uploads/our_games/1/downloads/icon.png');

        $item = $this->mapper->toDownloadLinkItem($link);

        self::assertSame('android', $item['platform']);
        self::assertSame('https://example.test/app.apk', $item['url']);
        self::assertSame('/uploads/our_games/1/downloads/icon.png', $item['imageUrl']);
    }
}
