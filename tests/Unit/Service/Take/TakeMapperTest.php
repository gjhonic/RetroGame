<?php

namespace App\Tests\Unit\Service\Take;

use App\Entity\Game;
use App\Entity\Take;
use App\Entity\TakeComment;
use App\Entity\User;
use App\Service\Take\TakeMapper;
use PHPUnit\Framework\TestCase;

class TakeMapperTest extends TestCase
{
    private TakeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TakeMapper();
    }

    public function testToListItemMapsFieldsIncludingCounts(): void
    {
        $author = (new User('author@retrogame.local', 'hash'))->setNickname('player1');
        $game = new Game('Half-Life', 'half-life');
        $take = new Take($author, $game, 'Great game, still holds up.');

        $data = $this->mapper->toListItem($take, 3, 1, 2);

        self::assertSame('Great game, still holds up.', $data['text']);
        self::assertSame(['id' => null, 'nickname' => 'player1'], $data['author']);
        self::assertSame(['id' => null, 'name' => 'Half-Life', 'slug' => 'half-life'], $data['game']);
        self::assertSame(3, $data['likeCount']);
        self::assertSame(1, $data['dislikeCount']);
        self::assertSame(2, $data['commentCount']);
    }

    public function testToDetailIncludesMappedComments(): void
    {
        $author = new User('author@retrogame.local', 'hash');
        $take = new Take($author, new Game('Half-Life', 'half-life'), 'Take text');
        $comment = new TakeComment($take, new User('commenter@retrogame.local', 'hash'), 'Totally agree!');

        $data = $this->mapper->toDetail($take, 0, 0, [$comment]);

        self::assertSame(1, $data['commentCount']);
        self::assertCount(1, $data['comments']);
        self::assertSame('Totally agree!', $data['comments'][0]['text']);
    }

    public function testToCommentMapsFields(): void
    {
        $author = (new User('commenter@retrogame.local', 'hash'))->setNickname('fan42');
        $take = new Take(new User('author@retrogame.local', 'hash'), new Game('Half-Life', 'half-life'), 'Take');
        $comment = new TakeComment($take, $author, 'Totally agree!');

        $data = $this->mapper->toComment($comment);

        self::assertSame('Totally agree!', $data['text']);
        self::assertSame(['id' => null, 'nickname' => 'fan42'], $data['author']);
    }
}
