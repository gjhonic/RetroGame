<?php

namespace App\Entity;

use App\Entity\Interfaces\NamedEntityInterface;
use App\Repository\PublisherRepository;
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

    public function __construct(string $name)
    {
        $this->name = $name;
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
