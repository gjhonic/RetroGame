<?php

namespace App\Entity;

use App\Repository\ScoreDieAgainRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScoreDieAgainRepository::class)]
#[ORM\Table(name: 'score_die_again')]
class ScoreDieAgain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $nickname;

    #[ORM\Column]
    private int $level;

    #[ORM\Column]
    private int $survivedSeconds;

    #[ORM\Column]
    private int $kills;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Создаёт запись результата раунда игры DIE//AGAIN. */
    public function __construct(string $nickname, int $level, int $survivedSeconds, int $kills)
    {
        $this->nickname = $nickname;
        $this->level = $level;
        $this->survivedSeconds = $survivedSeconds;
        $this->kills = $kills;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает ник игрока. */
    public function getNickname(): string
    {
        return $this->nickname;
    }

    /** Возвращает достигнутый уровень. */
    public function getLevel(): int
    {
        return $this->level;
    }

    /** Возвращает время выживания в секундах. */
    public function getSurvivedSeconds(): int
    {
        return $this->survivedSeconds;
    }

    /** Возвращает количество убитых противников. */
    public function getKills(): int
    {
        return $this->kills;
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
