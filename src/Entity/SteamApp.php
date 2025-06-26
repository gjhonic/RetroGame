<?php

namespace App\Entity;

use App\Repository\SteamAppRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SteamAppRepository::class)]
class SteamApp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $app_id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rawData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppId(): ?int
    {
        return $this->app_id;
    }

    public function setAppId(int $app_id): static
    {
        $this->app_id = $app_id;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getRawData(): ?string
    {
        return $this->rawData;
    }

    public function setRawData(?string $rawData): static
    {
        $this->rawData = $rawData;
        return $this;
    }
}
