<?php

namespace App\Entity;

use App\Entity\Interfaces\NamedEntityInterface;
use App\Repository\DeveloperRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeveloperRepository::class)]
class Developer implements NamedEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', unique: true)]
    private string $name;

    /**
     * Обратная сторона Game::$developers — не используется в коде напрямую,
     * нужна только чтобы Doctrine строил в AdminNamedEntityListTrait обычный
     * JOIN через game_developer вместо коррелированного EXISTS-подзапроса
     * (MEMBER OF).
     *
     * @var Collection<int, Game>
     */
    #[ORM\ManyToMany(targetEntity: Game::class, mappedBy: 'developers')]
    private Collection $games;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->games = new ArrayCollection();
    }

    /** Возвращает ID разработчика. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает название разработчика. */
    public function getName(): string
    {
        return $this->name;
    }
}
