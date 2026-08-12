<?php

namespace App\Tests\Unit\Service\OurGame;

use App\Dto\OurGame\OurGameRequest;
use App\Entity\Enum\OurGameStatus;
use App\Entity\Genre;
use App\Entity\OurGame;
use App\Repository\GenreRepository;
use App\Service\OurGame\OurGameCrudService;
use App\Service\OurGame\OurGameImageStorage;
use App\Service\OurGame\OurGameSlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OurGameCrudServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private OurGameSlugGenerator&MockObject $slugGenerator;
    private GenreRepository&MockObject $genreRepository;
    private OurGameImageStorage&MockObject $imageStorage;
    private OurGameCrudService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->slugGenerator = $this->createMock(OurGameSlugGenerator::class);
        $this->genreRepository = $this->createMock(GenreRepository::class);
        $this->imageStorage = $this->createMock(OurGameImageStorage::class);

        $this->service = new OurGameCrudService(
            $this->entityManager,
            $this->slugGenerator,
            $this->genreRepository,
            $this->imageStorage,
        );
    }

    private function makeRequest(): OurGameRequest
    {
        $request = new OurGameRequest();
        $request->name = 'Die Again';
        $request->description = 'A roguelike survival game.';
        $request->status = 'published';
        $request->currentVersion = '1.0.0';
        $request->releaseDate = '2026-01-15';
        $request->trailerUrl = 'https://youtube.com/watch?v=abc';
        $request->genreIds = [];

        return $request;
    }

    public function testCreatePersistsGameWithGeneratedSlug(): void
    {
        $this->slugGenerator->expects($this->once())->method('generate')->with('Die Again')->willReturn('die-again');
        $this->genreRepository->method('findBy')->willReturn([]);
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(OurGame::class));
        $this->entityManager->expects($this->once())->method('flush');

        $game = $this->service->create($this->makeRequest());

        self::assertSame('Die Again', $game->getName());
        self::assertSame('die-again', $game->getSlug());
        self::assertSame(OurGameStatus::Published, $game->getStatus());
        self::assertSame('1.0.0', $game->getCurrentVersion());
        self::assertSame('2026-01-15', $game->getReleaseDate()?->format('Y-m-d'));
        self::assertSame('https://youtube.com/watch?v=abc', $game->getTrailerUrl());
    }

    public function testCreateAssignsGenresByRequestedIds(): void
    {
        $this->slugGenerator->method('generate')->willReturn('die-again');
        $genre = new Genre('Roguelike');
        $this->genreRepository->expects($this->once())->method('findBy')->with(['id' => [1]])->willReturn([$genre]);

        $request = $this->makeRequest();
        $request->genreIds = [1];

        $game = $this->service->create($request);

        self::assertTrue($game->getGenres()->contains($genre));
    }

    public function testUpdateRegeneratesSlugOnlyWhenNameChanges(): void
    {
        $game = new OurGame('Old Name', 'old-name');
        $this->slugGenerator->expects($this->once())
            ->method('generate')
            ->with('Die Again', $game->getId())
            ->willReturn('die-again');
        $this->genreRepository->method('findBy')->willReturn([]);
        $this->entityManager->expects($this->once())->method('flush');

        $request = $this->makeRequest();
        $this->service->update($game, $request);

        self::assertSame('Die Again', $game->getName());
        self::assertSame('die-again', $game->getSlug());
    }

    public function testUpdateKeepsSlugWhenNameUnchanged(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $this->slugGenerator->expects($this->never())->method('generate');
        $this->genreRepository->method('findBy')->willReturn([]);

        $this->service->update($game, $this->makeRequest());

        self::assertSame('die-again', $game->getSlug());
    }

    public function testUpdateRemovesGenresNoLongerRequested(): void
    {
        $genre = new Genre('Roguelike');
        $game = (new OurGame('Die Again', 'die-again'))->addGenre($genre);
        $this->genreRepository->method('findBy')->willReturn([]);

        $request = $this->makeRequest();
        $request->genreIds = [];

        $this->service->update($game, $request);

        self::assertFalse($game->getGenres()->contains($genre));
    }

    public function testDeleteRemovesGameAndItsImages(): void
    {
        $game = new OurGame('Die Again', 'die-again');
        $reflection = new \ReflectionProperty(OurGame::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($game, 42);

        $this->entityManager->expects($this->once())->method('remove')->with($game);
        $this->entityManager->expects($this->once())->method('flush');
        $this->imageStorage->expects($this->once())->method('removeAllForGame')->with(42);

        $this->service->delete($game);
    }
}
