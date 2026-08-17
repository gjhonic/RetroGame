<?php

namespace App\Entity;

use App\Entity\Interfaces\NamedEntityInterface;
use App\Repository\PublisherRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublisherRepository::class)]
class Publisher implements NamedEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /**
     * Обратная сторона Game::$publishers — не используется в коде напрямую,
     * нужна только чтобы Doctrine строил в AdminNamedEntityListTrait обычный
     * JOIN через game_publisher вместо коррелированного EXISTS-подзапроса
     * (MEMBER OF).
     *
     * @var Collection<int, Game>
     */
    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'publishers')]
    private Collection $games;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->games = new ArrayCollection();
    }

    /** Возвращает ID издателя. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает название издателя. */
    public function getName(): string
    {
        return $this->name;
    }
}
