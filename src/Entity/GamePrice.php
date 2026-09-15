<?php

namespace App\Entity;

use App\Repository\GamePriceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Снимок цены игры в Steam (регион RU) на конкретный день — одна строка
 * на пару (игра, дата). Источник — App\Service\Steam\PriceImportService.
 */
#[ORM\Entity(repositoryClass: GamePriceRepository::class)]
#[ORM\UniqueConstraint(name: 'game_price_game_date', columns: ['game_id', 'date'])]
class GamePrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Финальная цена в минимальных единицах валюты (копейки для RUB) — null у бесплатных/недоступных в РФ игр. */
    #[ORM\Column(nullable: true)]
    private ?int $priceKopecks = null;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column]
    private bool $isFree;

    #[ORM\Column]
    private bool $isAvailableInRussia;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт пустой снимок цены игры на указанный день — статус задаётся через mark*(). */
    public function __construct(Game $game, \DateTimeImmutable $date)
    {
        $this->game = $game;
        $this->date = $date;
        $this->currency = 'RUB';
        $this->isFree = false;
        $this->isAvailableInRussia = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, к которой относится снимок цены. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает день, на который зафиксирован снимок цены. */
    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Возвращает финальную цену в минимальных единицах валюты (null — бесплатно или недоступно в РФ). */
    public function getPriceKopecks(): ?int
    {
        return $this->priceKopecks;
    }

    /** Возвращает код валюты (сейчас всегда RUB). */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /** Является ли игра бесплатной. */
    public function isFree(): bool
    {
        return $this->isFree;
    }

    /** Доступна ли игра для покупки в российском Steam. */
    public function isAvailableInRussia(): bool
    {
        return $this->isAvailableInRussia;
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Возвращает дату последнего обновления записи. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Фиксирует, что игра бесплатна. */
    public function markFree(): static
    {
        $this->isFree = true;
        $this->isAvailableInRussia = true;
        $this->priceKopecks = 0;
        $this->touch();

        return $this;
    }

    /** Фиксирует, что игра недоступна для покупки в российском Steam (регион-лок или нет цены без пометки "бесплатно"). */
    public function markUnavailable(): static
    {
        $this->isFree = false;
        $this->isAvailableInRussia = false;
        $this->priceKopecks = null;
        $this->touch();

        return $this;
    }

    /** Фиксирует платную цену игры. */
    public function markPriced(int $priceKopecks, string $currency): static
    {
        $this->isFree = false;
        $this->isAvailableInRussia = true;
        $this->priceKopecks = $priceKopecks;
        $this->currency = $currency;
        $this->touch();

        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
