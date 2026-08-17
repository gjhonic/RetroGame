<?php

namespace App\Tests\Unit\Service\OurGamePost;

use App\Dto\OurGamePost\OurGamePostRequest;
use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Entity\OurGamePost;
use App\Entity\User;
use App\Repository\OurGameRepository;
use App\Service\OurGamePost\Exceptions\OurGameNotFoundException;
use App\Service\OurGamePost\OurGamePostCrudService;
use App\Service\OurGamePost\OurGamePostImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OurGamePostCrudServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private OurGameRepository&MockObject $ourGameRepository;
    private OurGamePostImageStorage&MockObject $imageStorage;
    private OurGamePostCrudService $service;
    private User $author;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->ourGameRepository = $this->createMock(OurGameRepository::class);
        $this->imageStorage = $this->createMock(OurGamePostImageStorage::class);
        $this->author = new User('admin@retrogame.local', 'hash');

        $this->service = new OurGamePostCrudService(
            $this->entityManager,
            $this->ourGameRepository,
            $this->imageStorage,
        );
    }

    private function makeRequest(): OurGamePostRequest
    {
        $request = new OurGamePostRequest();
        $request->gameId = 1;
        $request->type = 'major_update';
        $request->status = 'published';
        $request->postedAt = '2026-03-01';
        $request->title = 'Большое обновление вышло';
        $request->shortDescription = 'Большое обновление.';
        $request->fullDescription = 'Подробности.';

        return $request;
    }

    public function testCreatePersistsPostWithAuthorAndGame(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->expects($this->once())->method('find')->with(1)->willReturn($game);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(OurGamePost::class));
        $this->entityManager->expects($this->once())->method('flush');

        $post = $this->service->create($this->author, $this->makeRequest());

        self::assertSame($game, $post->getGame());
        self::assertSame($this->author, $post->getAuthor());
        self::assertSame(OurGamePostType::MajorUpdate, $post->getType());
        self::assertSame(OurGameStatus::Published, $post->getStatus());
        self::assertSame('2026-03-01', $post->getPostedAt()->format('Y-m-d'));
        self::assertSame('Большое обновление.', $post->getShortDescription());
        self::assertSame('Подробности.', $post->getFullDescription());
    }

    public function testCreateThrowsExceptionWhenGameNotFound(): void
    {
        $this->ourGameRepository->expects($this->once())->method('find')->with(1)->willReturn(null);
        $this->entityManager->expects($this->never())->method('persist');

        $this->expectException(OurGameNotFoundException::class);
        $this->service->create($this->author, $this->makeRequest());
    }

    public function testUpdateReassignsGameAndFields(): void
    {
        $oldGame = new OurGame('Old Game', 'old-game');
        $newGame = new OurGame('Die Again', 'die-again');
        $this->ourGameRepository->expects($this->once())->method('find')->with(1)->willReturn($newGame);
        $this->entityManager->expects($this->once())->method('flush');

        $post = new OurGamePost(
            $oldGame,
            $this->author,
            OurGamePostType::Info,
            new \DateTimeImmutable('2026-01-01'),
            'Старый заголовок',
            'Старое описание',
        );

        $this->service->update($post, $this->makeRequest());

        self::assertSame($newGame, $post->getGame());
        self::assertSame(OurGamePostType::MajorUpdate, $post->getType());
        self::assertSame('Большое обновление.', $post->getShortDescription());
    }

    public function testUpdateThrowsExceptionWhenGameNotFound(): void
    {
        $post = new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            $this->author,
            OurGamePostType::Info,
            new \DateTimeImmutable('2026-01-01'),
            'Заголовок',
            'Описание',
        );
        $this->ourGameRepository->method('find')->willReturn(null);

        $this->expectException(OurGameNotFoundException::class);
        $this->service->update($post, $this->makeRequest());
    }

    public function testDeleteRemovesPostAndItsImage(): void
    {
        $post = new OurGamePost(
            new OurGame('Die Again', 'die-again'),
            $this->author,
            OurGamePostType::Info,
            new \DateTimeImmutable('2026-01-01'),
            'Заголовок',
            'Описание',
        );
        $reflection = new \ReflectionProperty(OurGamePost::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($post, 7);

        $this->entityManager->expects($this->once())->method('remove')->with($post);
        $this->entityManager->expects($this->once())->method('flush');
        $this->imageStorage->expects($this->once())->method('removeAllForPost')->with(7);

        $this->service->delete($post);
    }
}
