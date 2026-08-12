<?php

namespace App\Tests\Unit\Service\OurGamePost;

use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Entity\OurGamePost;
use App\Entity\User;
use App\Service\OurGamePost\OurGamePostMapper;
use PHPUnit\Framework\TestCase;

class OurGamePostMapperTest extends TestCase
{
    private OurGamePostMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OurGamePostMapper();
    }

    public function testToAdminListItemMapsFields(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $author = new User('admin@retrogame.local', 'hash');
        $post = (new OurGamePost(
            $game,
            $author,
            OurGamePostType::MajorUpdate,
            new \DateTimeImmutable('2026-03-01'),
            'Большое обновление вышло',
            'Большое обновление вышло.',
            OurGameStatus::Published,
        ))->setImagePath('uploads/our_game_posts/1/image/abc.jpg');

        $item = $this->mapper->toAdminListItem($post);

        self::assertSame('Die Again', $item['game']['name']);
        self::assertNull($item['author']['nickname']);
        self::assertSame('admin@retrogame.local', $item['author']['email']);
        self::assertSame('major_update', $item['type']);
        self::assertSame('published', $item['status']);
        self::assertSame('2026-03-01', $item['postedAt']);
        self::assertSame('/uploads/our_game_posts/1/image/abc.jpg', $item['imageUrl']);
        self::assertSame('Большое обновление вышло', $item['title']);
        self::assertSame('Большое обновление вышло.', $item['shortDescription']);
    }

    public function testToAdminListItemReturnsNullImageUrlWhenNotSet(): void
    {
        $post = new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            new User('admin@retrogame.local', 'hash'),
            OurGamePostType::Info,
            new \DateTimeImmutable('2026-03-01'),
            'Анонс',
            'Анонс.',
        );

        $item = $this->mapper->toAdminListItem($post);

        self::assertNull($item['imageUrl']);
        self::assertSame('draft', $item['status']);
    }

    public function testToDetailIncludesFullDescriptionAndTimestamps(): void
    {
        $post = (new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            new User('admin@retrogame.local', 'hash'),
            OurGamePostType::Info,
            new \DateTimeImmutable('2026-03-01'),
            'Анонс',
            'Анонс.',
        ))->setFullDescription('Подробности анонса.');

        $detail = $this->mapper->toDetail($post);

        self::assertSame('Подробности анонса.', $detail['fullDescription']);
        self::assertNotEmpty($detail['createdAt']);
        self::assertNotEmpty($detail['updatedAt']);
    }
}
