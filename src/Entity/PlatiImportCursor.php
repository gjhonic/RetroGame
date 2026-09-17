<?php

namespace App\Entity;

use App\Repository\PlatiImportCursorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Курсор пачечного обхода game для проверки на plati.market — по убыванию
 * Game::popularity, тот же приём, что и у остальных курсоров импорта (см.
 * SteamGameRepository::findBatchForPriceImport()). Обход идёт только по
 * играм, у которых ещё нет PlatiGame (см.
 * PlatiGameRepository::findGamesPendingCheck()) — найденные игры сами
 * выпадают из очереди, а к ненайденным можно возвращаться на следующем
 * круге, не рискуя зациклиться на верхушке списка.
 *
 * lastPopularity === null означает "курсор в начале списка" (см. reset()).
 * В таблице всегда ровно одна строка (см. getOrCreate() в репозитории).
 */
#[ORM\Entity(repositoryClass: PlatiImportCursorRepository::class)]
class PlatiImportCursor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastPopularity = null;

    #[ORM\Column]
    private int $lastGameId = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт курсор в начале списка. */
    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает popularity последней проверенной игры (null — курсор в начале списка). */
    public function getLastPopularity(): ?int
    {
        return $this->lastPopularity;
    }

    /** Возвращает id последней проверенной игры — тай-брейк при равной популярности. */
    public function getLastGameId(): int
    {
        return $this->lastGameId;
    }

    /** Сдвигает курсор на позицию последней проверенной игры. */
    public function setPosition(int $lastPopularity, int $lastGameId): static
    {
        $this->lastPopularity = $lastPopularity;
        $this->lastGameId = $lastGameId;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает время последнего сдвига курсора. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Сбрасывает курсор в начало списка — конец обхода достигнут, начинаем новый круг. */
    public function reset(): static
    {
        $this->lastPopularity = null;
        $this->lastGameId = 0;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
