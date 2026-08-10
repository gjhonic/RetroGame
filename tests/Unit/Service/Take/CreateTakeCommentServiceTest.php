<?php

namespace App\Tests\Unit\Service\Take;

use App\Dto\Take\CreateTakeCommentRequest;
use App\Entity\Game;
use App\Entity\Take;
use App\Entity\User;
use App\Service\Take\CreateTakeCommentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateTakeCommentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CreateTakeCommentService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new CreateTakeCommentService($this->entityManager);
    }

    public function testCreateSavesCommentWithTakeAuthorAndText(): void
    {
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $take = new Take(new User('author@retrogame.local', 'hash'), new Game('Half-Life', 'half-life'), 'Take');
        $author = new User('commenter@retrogame.local', 'hash');

        $request = new CreateTakeCommentRequest();
        $request->text = 'Totally agree!';

        $comment = $this->service->create($take, $author, $request);

        self::assertSame($take, $comment->getTake());
        self::assertSame($author, $comment->getAuthor());
        self::assertSame('Totally agree!', $comment->getText());
    }
}
