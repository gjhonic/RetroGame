<?php

namespace App\Entity;

use App\Repository\GgselGameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Факт наличия игры на ggsel.net — создаётся только при успешном
 * нахождении товара (см. App\Service\Ggsel\GameImportService), поэтому
 * само существование строки уже означает "найдено", отдельный статус не
 * нужен. Отсутствие строки означает "ещё не проверяли" или "на прошлой
 * проверке не нашли" — обе ситуации неотличимы и обе значат "проверить
 * ещё раз" (см. GgselGameRepository::findGamesPendingCheck()).
 */
#[ORM\Entity(repositoryClass: GgselGameRepository::class)]
class GgselGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', nullable: false, unique: true)]
    private Game $game;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт запись о найденном на ggsel.net товаре. */
    public function __construct(Game $game, string $url)
    {
        $this->game = $game;
        $this->url = $url;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, для которой найден товар на ggsel.net. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает ссылку на товар на ggsel.net. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** Обновляет ссылку на товар (например, если ggsel сменил slug). */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает время первого нахождения товара. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Возвращает время последнего обновления записи. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
