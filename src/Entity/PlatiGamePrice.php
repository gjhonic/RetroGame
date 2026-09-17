<?php

namespace App\Entity;

use App\Repository\PlatiGamePriceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Снимок цены товара конкретного продавца на plati.market на конкретный
 * день — одна строка на пару (PlatiGame, дата). Привязка именно к
 * PlatiGame, а не к Game: у одной игры может быть несколько продавцов
 * (см. класс-докблок PlatiGame), и у каждого своя цена. Источник —
 * App\Service\Plati\PriceImportService, которая для каждого продавца
 * повторяет поиск (App\Service\Plati\GameMatcher) и ищет среди
 * результатов именно эту карточку (по ссылке), а не просто самую
 * продаваемую — иначе цена одного продавца перезаписала бы цену другого.
 * Валюта всегда RUB (Digiseller отдаёт цену в рублях без дробной части) —
 * отдельным полем не хранится. priceKopecks = null означает "на эту дату
 * повторным поиском карточку не нашли" (объявление могло исчезнуть) —
 * тот же приём, что и в GamePrice для недоступности в РФ.
 */
#[ORM\Entity(repositoryClass: PlatiGamePriceRepository::class)]
#[ORM\Table(name: 'plati_game_prices')]
#[ORM\UniqueConstraint(name: 'plati_game_prices_plati_game_date', columns: ['plati_game_id', 'date'])]
class PlatiGamePrice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlatiGame::class)]
    #[ORM\JoinColumn(name: 'plati_game_id', nullable: false, onDelete: 'CASCADE')]
    private PlatiGame $platiGame;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Цена в копейках — null, если повторным поиском карточку не нашли на эту дату. */
    #[ORM\Column(nullable: true)]
    private ?int $priceKopecks = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Создаёт пустой снимок цены на указанный день — значение задаётся через mark*(). */
    public function __construct(PlatiGame $platiGame, \DateTimeImmutable $date)
    {
        $this->platiGame = $platiGame;
        $this->date = $date;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает запись продавца (PlatiGame), к которой относится снимок цены. */
    public function getPlatiGame(): PlatiGame
    {
        return $this->platiGame;
    }

    /** Возвращает игру, к которой относится снимок цены. */
    public function getGame(): Game
    {
        return $this->platiGame->getGame();
    }

    /** Возвращает день, на который зафиксирован снимок цены. */
    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Возвращает цену в копейках (null — на эту дату карточку повторным поиском не нашли). */
    public function getPriceKopecks(): ?int
    {
        return $this->priceKopecks;
    }

    /** Найдена ли цена на эту дату. */
    public function isFound(): bool
    {
        return $this->priceKopecks !== null;
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Фиксирует найденную цену (в копейках). */
    public function markPriced(int $priceKopecks): static
    {
        $this->priceKopecks = $priceKopecks;

        return $this;
    }

    /** Фиксирует, что на эту дату повторным поиском карточку не нашли. */
    public function markUnavailable(): static
    {
        $this->priceKopecks = null;

        return $this;
    }
}
