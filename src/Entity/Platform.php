<?php

namespace App\Entity;

use App\Entity\Interfaces\NamedEntityInterface;
use App\Repository\PlatformRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlatformRepository::class)]
class Platform implements NamedEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /** Возвращает ID платформы. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает название платформы. */
    public function getName(): string
    {
        return $this->name;
    }
}
