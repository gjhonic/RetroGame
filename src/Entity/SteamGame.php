<?php

namespace App\Entity;

use App\Entity\Enum\SteamGameStatus;
use App\Repository\SteamGameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Данные игры из источника Steam.
 *
 * Game — базовая сущность игры, общая для всех источников. SteamGame
 * хранит всё, что специфично для Steam: appid, сырой JSON от appdetails
 * и статус загрузки (в том числе неудачной — чтобы можно было повторить).
 */
#[ORM\Entity(repositoryClass: SteamGameRepository::class)]
class SteamGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Game::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'game_id', nullable: true, unique: true)]
    private ?Game $game = null;

    #[ORM\OneToOne(targetEntity: Dlc::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'dlc_id', nullable: true, unique: true)]
    private ?Dlc $dlc = null;

    #[ORM\Column(unique: true)]
    private int $steamAppId;

    #[ORM\Column(length: 20, enumType: SteamGameStatus::class)]
    private SteamGameStatus $status;

    /**
     * Полный сырой ответ Steam appdetails — на случай, если понадобятся
     * поля, которые мы ещё не вынесли в отдельные колонки/в Game.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $rawData = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private int $attempts = 0;

    /**
     * Игра точно бесплатна (подтверждено импортом цены — App\Service\Steam\PriceImportService).
     * Раз проставившись, больше не сбрасывается: бесплатная игра платной не
     * становится, а сама проверка цены каждый раз дорогая (запрос к Steam) —
     * такие игры сразу отсекаются из SteamGameRepository::findBatchForPriceImport().
     */
    #[ORM\Column]
    private bool $priceFree = false;

    /**
     * Доступна ли игра для покупки в российском Steam (последний известный
     * статус из PriceImportService) — в отличие от priceFree это не
     * одноразовая метка: регион-лок может как появиться, так и сняться,
     * поэтому значение обновляется при каждом импорте цены в обе стороны.
     * До первой проверки цены считается доступной (оптимистичное значение
     * по умолчанию, ещё не опровергнутое).
     */
    #[ORM\Column]
    private bool $availableInRussia = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт запись Steam-приложения в статусе "ожидает загрузки" (тип пока неизвестен). */
    public function __construct(int $steamAppId)
    {
        $this->steamAppId = $steamAppId;
        $this->status = SteamGameStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает связанную сущность Game (null — это не игра, а DLC/другой тип, либо детали ещё не загружены). */
    public function getGame(): ?Game
    {
        return $this->game;
    }

    /** Привязывает эту Steam-запись к базовой игре. */
    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    /** Возвращает связанную сущность Dlc (null — это не DLC). */
    public function getDlc(): ?Dlc
    {
        return $this->dlc;
    }

    /** Привязывает эту Steam-запись к DLC. */
    public function setDlc(Dlc $dlc): static
    {
        $this->dlc = $dlc;

        return $this;
    }

    /** Возвращает appid игры в Steam. */
    public function getSteamAppId(): int
    {
        return $this->steamAppId;
    }

    /** Возвращает текущий статус загрузки. */
    public function getStatus(): SteamGameStatus
    {
        return $this->status;
    }

    /**
     * Возвращает сырой JSON от Steam appdetails последней успешной загрузки.
     *
     * @return array<string, mixed>|null
     */
    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    /** Возвращает текст последней ошибки загрузки. */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /** Возвращает количество попыток загрузки. */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /** Подтверждено ли, что игра бесплатна (см. PriceImportService). */
    public function isPriceFree(): bool
    {
        return $this->priceFree;
    }

    /** Фиксирует, что игра бесплатна — исключает её из дальнейшего импорта цен. */
    public function markPriceFree(): static
    {
        $this->priceFree = true;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Доступна ли игра для покупки в российском Steam (последний известный статус). */
    public function isAvailableInRussia(): bool
    {
        return $this->availableInRussia;
    }

    /** Обновляет статус доступности игры в российском Steam по результату очередного импорта цены. */
    public function setAvailableInRussia(bool $availableInRussia): static
    {
        $this->availableInRussia = $availableInRussia;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает время последней успешной загрузки. */
    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    /** Возвращает время последней попытки загрузки (успешной или нет). */
    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    /**
     * Фиксирует успешную загрузку данных.
     *
     * @param array<string, mixed> $rawData
     */
    public function markSuccess(array $rawData): static
    {
        $now = new \DateTimeImmutable();

        $this->status = SteamGameStatus::Success;
        $this->rawData = $rawData;
        $this->lastError = null;
        ++$this->attempts;
        $this->fetchedAt = $now;
        $this->lastAttemptAt = $now;
        $this->updatedAt = $now;

        return $this;
    }

    /**
     * Фиксирует неудачную попытку — чтобы можно было отобрать такие
     * записи и повторить загрузку позже.
     */
    public function markFailure(string $error): static
    {
        $now = new \DateTimeImmutable();

        $this->status = SteamGameStatus::Failed;
        $this->lastError = $error;
        ++$this->attempts;
        $this->lastAttemptAt = $now;
        $this->updatedAt = $now;

        return $this;
    }
}
