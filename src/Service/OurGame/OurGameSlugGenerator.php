<?php

namespace App\Service\OurGame;

use App\Repository\OurGameRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/** Строит уникальный slug по названию своей игры (по образцу GameImportService::buildUniqueSlug()). */
class OurGameSlugGenerator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly OurGameRepository $ourGameRepository,
    ) {
    }

    /** @param int|null $excludeId id игры, чей текущий slug не считается занятым (при переименовании) */
    public function generate(string $name, ?int $excludeId = null): string
    {
        $base = strtolower((string) $this->slugger->slug($name));
        if ($base === '') {
            $base = 'game';
        }

        $slug = $base;
        $suffix = 2;
        while ($this->isTaken($slug, $excludeId)) {
            $slug = $base . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }

    private function isTaken(string $slug, ?int $excludeId): bool
    {
        $existing = $this->ourGameRepository->findOneBySlug($slug);

        return $existing !== null && $existing->getId() !== $excludeId;
    }
}
