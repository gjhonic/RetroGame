<?php

namespace App\Entity;

use App\Repository\GameShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameShopRepository::class)]
class GameShop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shops')]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne(inversedBy: 'gameShops', fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: false)]
    private ?Shop $shop = null;

    #[ORM\Column]
    private ?int $link_game_id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $link = null;

    /**
     * @var Collection<int, GameShopPriceHistory>
     */
    #[ORM\OneToMany(mappedBy: 'gameShop', targetEntity: GameShopPriceHistory::class, orphanRemoval: true)]
    private Collection $priceHistory;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $shouldImportPrice = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalKey = null;

    /**
     * @var array<mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extraParams = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->priceHistory = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * @return Collection<int, GameShopPriceHistory>
     */
    public function getPriceHistory(): Collection
    {
        return $this->priceHistory;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

        return $this;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): self
    {
        $this->shop = $shop;

        return $this;
    }

    public function getLinkGameId(): ?int
    {
        return $this->link_game_id;
    }

    public function setLinkGameId(int $link_game_id): static
    {
        $this->link_game_id = $link_game_id;

        return $this;
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

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getLatestPrice(): ?float
    {
        $last = null;

        foreach ($this->priceHistory as $entry) {
            if (null === $last || $entry->getUpdatedAt() > $last->getUpdatedAt()) {
                $last = $entry;
            }
        }

        return $last?->getPrice();
    }

    public function getLatestPriceUpdatedAt(): ?\DateTimeInterface
    {
        $last = null;

        foreach ($this->priceHistory as $entry) {
            if (null === $last || $entry->getUpdatedAt() > $last->getUpdatedAt()) {
                $last = $entry;
            }
        }

        return $last?->getUpdatedAt();
    }

    public function getShouldImportPrice(): bool
    {
        return $this->shouldImportPrice;
    }

    public function setShouldImportPrice(bool $shouldImportPrice): static
    {
        $this->shouldImportPrice = $shouldImportPrice;

        return $this;
    }

    public function getExternalKey(): ?string
    {
        return $this->externalKey;
    }

    public function setExternalKey(?string $externalKey): static
    {
        $this->externalKey = $externalKey;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getExtraParams(): ?array
    {
        return $this->extraParams;
    }

    /**
     * @param array<mixed>|null $extraParams
     *
     * @return $this
     */
    public function setExtraParams(?array $extraParams): static
    {
        $this->extraParams = $extraParams;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getParamPrice(): ?array
    {
        if (!is_array($this->extraParams)) {
            return null;
        }

        return $this->extraParams['paramPrice'] ?? null;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
