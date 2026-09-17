<?php

namespace App\Entity;

use App\Repository\PlatiGameRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Факт наличия игры на plati.market у конкретного продавца — создаётся
 * только при успешном нахождении товара (см.
 * App\Service\Plati\GameImportService), поэтому само существование строки
 * уже означает "найдено", отдельный статус не нужен. У одной игры может
 * быть несколько записей (до GameImportService::TOP_SELLERS_LIMIT) — по
 * одной на каждого из самых продаваемых совпавших продавцов, а не только
 * на самого продаваемого: у разных продавцов разная цена и надёжность, и
 * пользователю может быть удобнее выбрать не самый популярный вариант.
 * Отсутствие записей означает "ещё не проверяли" или "на прошлой проверке
 * не нашли" — обе ситуации неотличимы и обе значат "проверить ещё раз"
 * (см. PlatiGameRepository::findGamesPendingCheck()).
 */
#[ORM\Entity(repositoryClass: PlatiGameRepository::class)]
class PlatiGame
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', nullable: false)]
    private Game $game;

    #[ORM\Column(length: 500)]
    private string $url;

    /** Имя магазина/продавца на plati.market (поле seller_name в ответе Digiseller). */
    #[ORM\Column(length: 255)]
    private string $sellerName;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт запись о найденном на plati.market товаре одного продавца. */
    public function __construct(Game $game, string $url, string $sellerName)
    {
        $this->game = $game;
        $this->url = $url;
        $this->sellerName = $sellerName;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, для которой найден товар на plati.market. */
    public function getGame(): Game
    {
        return $this->game;
    }

    /** Возвращает ссылку на товар на plati.market. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** Обновляет ссылку на товар (например, если у продавца сменился адрес карточки). */
    public function setUrl(string $url): static
    {
        $this->url = $url;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Возвращает имя продавца. */
    public function getSellerName(): string
    {
        return $this->sellerName;
    }

    /** Обновляет имя продавца (например, если продавец переименовался). */
    public function setSellerName(string $sellerName): static
    {
        $this->sellerName = $sellerName;
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
