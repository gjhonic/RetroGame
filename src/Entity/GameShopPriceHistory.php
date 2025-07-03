<?php

namespace App\Entity;

use App\Repository\GameShopPriceHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameShopPriceHistoryRepository::class)]
class GameShopPriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'priceHistory')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameShop $gameShop = null;

    #[ORM\Column(type: 'float')]
    private float $price;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGameShop(): ?GameShop
    {
        return $this->gameShop;
    }

    public function setGameShop(?GameShop $gameShop): static
    {
        $this->gameShop = $gameShop;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
