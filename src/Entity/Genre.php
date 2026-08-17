<?php

namespace App\Entity;

use App\Entity\Interfaces\NamedEntityInterface;
use App\Repository\GenreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GenreRepository::class)]
class Genre implements NamedEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name;

    /**
     * Обратная сторона Game::$genres — не используется в коде напрямую, нужна
     * только чтобы Doctrine строил в AdminNamedEntityListTrait обычный JOIN
     * через game_genre вместо коррелированного EXISTS-подзапроса (MEMBER OF).
     *
     * @var Collection<int, Game>
     */
    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'genres')]
    private Collection $games;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->games = new ArrayCollection();
    }

    /** Возвращает ID жанра. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает название жанра. */
    public function getName(): string
    {
        return $this->name;
    }
}
