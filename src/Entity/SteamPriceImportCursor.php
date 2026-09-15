<?php

namespace App\Entity;

use App\Repository\SteamPriceImportCursorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Курсор пачечного импорта цен — на чём остановился прошлый запуск
 * app:games:import-prices. Порядок обхода — по популярности игры
 * (Game::popularity) по убыванию, а не по id, чтобы в первую очередь
 * обновлялись цены у самых популярных игр.
 *
 * Каждый день курсор сбрасывается в начало списка (см.
 * PriceImportService::importNextBatch() — сравнение updatedAt с текущей
 * датой), а не продолжает докручивать вчерашний круг: при 90k+ играх и
 * растущем каталоге пройти его целиком за сутки всё равно не успеть, а
 * топовые игры должны получать свежую цену каждый день, а не раз в
 * несколько дней, когда докрутится очередь. Игры, до которых в рамках
 * дня не дошла очередь, просто остаются без свежего снимка на этот день.
 *
 * lastPopularity === null означает "курсор в начале списка" (после
 * reset() или до первого запуска вовсе) — это состояние специально
 * отличается от значения -1, которым помечается пройденная игра без
 * popularity (NULL трактуется как самый низкий приоритет, "меньше любого
 * реального значения"): иначе такая запись была бы неотличима от "курсор
 * не начинал работу", и обход зациклился бы на верхушке списка, никогда
 * не добравшись до игр без popularity. lastSteamGameId — тай-брейк при
 * одинаковой популярности, имеет смысл только когда lastPopularity задан.
 *
 * В таблице всегда ровно одна строка (см. getOrCreate() в репозитории).
 * Отдельный от SteamImportCursor (курсора каталожного импорта) — процессы
 * независимы.
 */
#[ORM\Entity(repositoryClass: SteamPriceImportCursorRepository::class)]
class SteamPriceImportCursor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastPopularity = null;

    #[ORM\Column]
    private int $lastSteamGameId = 0;

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

    /** Возвращает id SteamGame последней обработанной игры — тай-брейк при равной популярности. */
    public function getLastSteamGameId(): int
    {
        return $this->lastSteamGameId;
    }

    /** Сдвигает курсор на позицию последней обработанной игры. */
    public function setPosition(int $lastPopularity, int $lastSteamGameId): static
    {
        $this->lastPopularity = $lastPopularity;
        $this->lastSteamGameId = $lastSteamGameId;
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
        $this->lastSteamGameId = 0;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
