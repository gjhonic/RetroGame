<?php

namespace App\Entity;

use App\Repository\SteamImportCursorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Курсор постраничного импорта из Steam — на чём остановился прошлый
 * запуск команды. В таблице всегда ровно одна строка (см. getOrCreate()
 * в репозитории), чтобы cron мог продолжать без ручного --last-appid.
 */
#[ORM\Entity(repositoryClass: SteamImportCursorRepository::class)]
class SteamImportCursor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $lastAppId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт курсор, по умолчанию с начала каталога Steam. */
    public function __construct(int $lastAppId = 0)
    {
        $this->lastAppId = $lastAppId;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает appid, на котором остановился прошлый импорт. */
    public function getLastAppId(): int
    {
        return $this->lastAppId;
    }

    /** Сдвигает курсор на новый appid. */
    public function setLastAppId(int $lastAppId): static
    {
        $this->lastAppId = $lastAppId;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает время последнего сдвига курсора. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
