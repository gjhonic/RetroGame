<?php

namespace App\Entity\Interfaces;

use App\Entity\Developer;
use App\Entity\Genre;
use App\Entity\Platform;
use App\Entity\Publisher;
use Doctrine\Common\Collections\Collection;

/**
 * Общий контракт для сущностей, заполняемых из Steam appdetails
 * (Game, Dlc) — позволяет применять общую логику маппинга полей
 * (GameImportService::applyCommonDetails()) к обеим сразу.
 */
interface HasSteamDetailsInterface
{
    public function setName(string $name): static;

    public function setDescription(?string $description): static;

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static;

    public function setCoverImagePath(?string $coverImagePath): static;

    /** @return Collection<int, Developer> */
    public function getDevelopers(): Collection;

    public function addDeveloper(Developer $developer): static;

    /** @return Collection<int, Publisher> */
    public function getPublishers(): Collection;

    public function addPublisher(Publisher $publisher): static;

    /** @return Collection<int, Genre> */
    public function getGenres(): Collection;

    public function addGenre(Genre $genre): static;

    /** @return Collection<int, Platform> */
    public function getPlatforms(): Collection;

    public function addPlatform(Platform $platform): static;

    /** @param array<int, string>|null $screenshotUrls */
    public function setScreenshotUrls(?array $screenshotUrls): static;

    public function touch(): static;
}
