<?php

namespace App\Entity;

use App\Repository\ShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopRepository::class)]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    /**
     * @var Collection<int, GameShop>
     *
     * @return Collection<int, GameShop>
     */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: GameShop::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $gameShops;

    public function __construct()
    {
        $this->gameShops = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return Collection<int, GameShop>
     */
    public function getGameShops(): Collection
    {
        return $this->gameShops;
    }

    public function addGameShop(GameShop $gameShop): static
    {
        if (!$this->gameShops->contains($gameShop)) {
            $this->gameShops->add($gameShop);
            $gameShop->setShop($this);
        }

        return $this;
    }

    public function removeGameShop(GameShop $gameShop): static
    {
        if ($this->gameShops->removeElement($gameShop)) {
            if ($gameShop->getShop() === $this) {
                $gameShop->setShop(null);
            }
        }

        return $this;
    }
}
