<?php

namespace App\Entity;

use App\Repository\PlatiPriceImportCursorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Курсор пачечного импорта цен plati.market — на чём остановился прошлый
 * запуск app:games:import-plati-prices. Обходит только игры, у которых
 * уже есть PlatiGame (найдены предыдущим импортом наличия), по убыванию
 * Game::popularity — тот же приём, что и SteamPriceImportCursor.
 *
 * Каждый день курсор сбрасывается в начало списка (см.
 * PriceImportService::importNextBatch()) по тем же причинам, что и у
 * Steam-аналога: за сутки каталог целиком не обойти, а у самых популярных
 * игр цена должна обновляться каждый день.
 *
 * lastPopularity === null означает "курсор в начале списка".
 * lastPlatiGameId — тай-брейк при одинаковой популярности.
 *
 * В таблице всегда ровно одна строка (см. getOrCreate() в репозитории).
 */
#[ORM\Entity(repositoryClass: PlatiPriceImportCursorRepository::class)]
class PlatiPriceImportCursor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastPopularity = null;

    #[ORM\Column]
    private int $lastPlatiGameId = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт курсор в начале списка (популярность ещё не учтена). */
    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает popularity последней обработанной игры (null — курсор в начале списка). */
    public function getLastPopularity(): ?int
    {
        return $this->lastPopularity;
    }

    /** Возвращает id PlatiGame последней обработанной игры — тай-брейк при равной популярности. */
    public function getLastPlatiGameId(): int
    {
        return $this->lastPlatiGameId;
    }

    /** Сдвигает курсор на позицию последней обработанной игры. */
    public function setPosition(int $lastPopularity, int $lastPlatiGameId): static
    {
        $this->lastPopularity = $lastPopularity;
        $this->lastPlatiGameId = $lastPlatiGameId;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает время последнего сдвига курсора. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Сбрасывает курсор в начало списка — новый день, новый круг с самых популярных игр. */
    public function reset(): static
    {
        $this->lastPopularity = null;
        $this->lastPlatiGameId = 0;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
