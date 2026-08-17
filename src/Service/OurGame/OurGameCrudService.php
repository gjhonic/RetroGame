<?php

namespace App\Service\OurGame;

use App\Dto\OurGame\OurGameRequest;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGame;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Создание/обновление/удаление OurGame — вынесено из контроллера по правилам modules.md. */
class OurGameCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OurGameSlugGenerator $slugGenerator,
        private readonly GenreRepository $genreRepository,
        private readonly OurGameImageStorage $imageStorage,
    ) {
    }

    public function create(OurGameRequest $request): OurGame
    {
        $game = new OurGame(
            $request->name,
            $this->slugGenerator->generate($request->name),
            OurGameStatus::from($request->status),
        );
        $this->applyRequest($game, $request);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    public function update(OurGame $game, OurGameRequest $request): OurGame
    {
        if ($game->getName() !== $request->name) {
            $game->setName($request->name);
            $game->setSlug($this->slugGenerator->generate($request->name, $game->getId()));
        }

        $this->applyRequest($game, $request);
        $game->touch();

        $this->entityManager->flush();

        return $game;
    }

    public function delete(OurGame $game): void
    {
        $id = $game->getId();
        $this->entityManager->remove($game);
        $this->entityManager->flush();

        if ($id !== null) {
            $this->imageStorage->removeAllForGame($id);
        }
    }

    private function applyRequest(OurGame $game, OurGameRequest $request): void
    {
        $game->setDescription($this->nullIfBlank($request->description));
        $game->setStatus(OurGameStatus::from($request->status));

        if ($game->getCurrentVersion() !== $request->currentVersion) {
            $game->setCurrentVersion($this->nullIfBlank($request->currentVersion));
        }

        $game->setReleaseDate($request->releaseDate !== null && $request->releaseDate !== ''
            ? new \DateTimeImmutable($request->releaseDate)
            : null);
        $game->setTrailerUrl($this->nullIfBlank($request->trailerUrl));

        foreach ($game->getGenres()->toArray() as $genre) {
            if (!\in_array($genre->getId(), $request->genreIds, true)) {
                $game->removeGenre($genre);
            }
        }

        foreach ($this->genreRepository->findBy(['id' => $request->genreIds]) as $genre) {
            $game->addGenre($genre);
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
