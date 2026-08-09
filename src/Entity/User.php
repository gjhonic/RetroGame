<?php

namespace App\Entity;

use App\Entity\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 20, enumType: UserRole::class)]
    private UserRole $role;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Создаёт пользователя с обязательными полями, остальное — через сеттеры.
     *
     * @param non-empty-string $email
     */
    public function __construct(string $email, string $password, UserRole $role = UserRole::User)
    {
        $this->email = $email;
        $this->password = $password;
        $this->role = $role;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID пользователя. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Возвращает email пользователя.
     *
     * @return non-empty-string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Задаёт email пользователя.
     *
     * @param non-empty-string $email
     */
    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Идентификатор пользователя для Security — email.
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** Возвращает хеш пароля. */
    public function getPassword(): string
    {
        return $this->password;
    }

    /** Задаёт хеш пароля (пароль уже должен быть захеширован). */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /** Возвращает роль пользователя. */
    public function getRole(): UserRole
    {
        return $this->role;
    }

    /** Задаёт роль пользователя. */
    public function setRole(UserRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    /** Роли для Security — иерархия (ROLE_ADMIN → ROLE_MODERATOR → ROLE_USER) настроена в security.yaml. */
    public function getRoles(): array
    {
        return [$this->role->value];
    }

    /** Возвращает дату создания записи. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Возвращает дату последнего обновления записи. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Обновляет дату последнего изменения на текущий момент. */
    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
