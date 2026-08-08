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
    #[ORM\JoinColumn(name: 'game_id', nullable: false, unique: true)]
    private Game $game;

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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт запись Steam-игры в статусе "ожидает загрузки". */
    public function __construct(Game $game, int $steamAppId)
    {
        $this->game = $game;
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

    /** Возвращает связанную базовую сущность Game. */
    public function getGame(): Game
    {
        return $this->game;
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
