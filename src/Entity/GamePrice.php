<?php

namespace App\Entity;

use App\Repository\GamePriceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Снимок цены игры в Steam (регион RU) на конкретный день — одна строка
 * на пару (игра, дата). Источник — App\Service\Steam\PriceImportService.
 * Валюта всегда RUB (см. GamePriceMapper) — отдельным полем не хранится.
 * Состояние кодируется одним nullable-полем priceKopecks: null — недоступна
 * в РФ, 0 — бесплатна, >0 — цена в копейках.
 */
#[ORM\Entity(repositoryClass: GamePriceRepository::class)]
#[ORM\Table(name: 'steam_game_prices')]
#[ORM\UniqueConstraint(name: 'steam_game_prices_game_date', columns: ['game_id', 'date'])]
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

    /** Цена в копейках — null у недоступных в РФ, 0 у бесплатных игр. */
    #[ORM\Column(nullable: true)]
    private ?int $priceKopecks = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Создаёт пустой снимок цены игры на указанный день — статус задаётся через mark*(). */
    public function __construct(Game $game, \DateTimeImmutable $date)
    {
        $this->game = $game;
        $this->date = $date;
        $this->createdAt = new \DateTimeImmutable();
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

    /** Возвращает цену в копейках (null — бесплатно или недоступно в РФ, см. isFree()/isAvailableInRussia()). */
    public function getPriceKopecks(): ?int
    {
        return $this->priceKopecks;
    }

    /** Является ли игра бесплатной. */
    public function isFree(): bool
    {
        return $this->priceKopecks === 0;
    }

    /** Доступна ли игра для покупки в российском Steam. */
    public function isAvailableInRussia(): bool
    {
        return $this->priceKopecks !== null;
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Фиксирует, что игра бесплатна. */
    public function markFree(): static
    {
        $this->priceKopecks = 0;

        return $this;
    }

    /** Фиксирует, что игра недоступна для покупки в российском Steam (регион-лок или нет цены без пометки "бесплатно"). */
    public function markUnavailable(): static
    {
        $this->priceKopecks = null;

        return $this;
    }

    /** Фиксирует платную цену игры (в копейках, валюта всегда RUB). */
    public function markPriced(int $priceKopecks): static
    {
        $this->priceKopecks = $priceKopecks;

        return $this;
    }
}
