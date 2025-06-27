<?php

namespace App\Entity;

use App\Repository\SteampayAppRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SteampayAppRepository::class)]
class SteampayApp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column]
    private bool $notFound = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawHtml = null;

    #[ORM\Column]
    private \DateTimeImmutable $checkedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    public function setNotFound(bool $notFound): static
    {
        $this->notFound = $notFound;
        return $this;
    }

    public function getRawHtml(): ?string
    {
        return $this->rawHtml;
    }

    public function setRawHtml(?string $rawHtml): static
    {
        $this->rawHtml = $rawHtml;
        return $this;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(\DateTimeImmutable $checkedAt): static
    {
        $this->checkedAt = $checkedAt;
        return $this;
    }
}
