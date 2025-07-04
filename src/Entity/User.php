<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Сущность пользователя системы.
 *
 * Представляет пользователя в системе с поддержкой аутентификации и авторизации.
 * Реализует интерфейсы Symfony для работы с безопасностью.
 *
 * @ORM\Entity
 *
 * @ORM\Table(name="users")
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * Уникальный идентификатор пользователя.
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue
     *
     * @ORM\Column(type="integer")
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Email пользователя (уникальный).
     *
     * Используется как логин для входа в систему
     *
     * @ORM\Column(type="string", length=180, unique=true)
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Роли пользователя в системе.
     *
     * Хранится в формате JSON. По умолчанию каждый пользователь имеет ROLE_USER.
     * Пример: ["ROLE_USER", "ROLE_ADMIN"]
     *
     * @var array<string>
     *
     * @ORM\Column(type="json")
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * Хешированный пароль пользователя.
     *
     * Пароль хранится в зашифрованном виде для безопасности
     *
     * @ORM\Column(type="string")
     */
    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    /**
     * Дата и время создания записи пользователя.
     *
     * Автоматически устанавливается при создании нового пользователя
     *
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Дата и время последнего обновления записи пользователя.
     *
     * Автоматически обновляется при каждом изменении данных пользователя
     *
     * @ORM\Column(type="datetime")
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Имя пользователя.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    /**
     * Конструктор сущности.
     *
     * Инициализирует поля дат создания и обновления текущим временем
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Получить ID пользователя.
     *
     * @return int|null Уникальный идентификатор пользователя
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Получить email пользователя.
     *
     * @return string|null Email пользователя
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Установить email пользователя.
     *
     * @param string $email Email пользователя
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Получить уникальный идентификатор пользователя для аутентификации.
     *
     * Используется Symfony Security для идентификации пользователя
     *
     * @return string Email пользователя как идентификатор
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * Получить имя пользователя (устаревший метод).
     *
     * @deprecated since Symfony 5.3, use getUserIdentifier()
     *
     * @return string Email пользователя
     */
    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    /**
     * Получить роли пользователя.
     *
     * Гарантирует, что каждый пользователь имеет хотя бы ROLE_USER
     *
     * @return array<string> Массив ролей пользователя
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * Установить роли пользователя.
     *
     * @param list<string> $roles Массив ролей для установки
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Получить хешированный пароль пользователя.
     *
     * @return string|null Хешированный пароль
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Установить хешированный пароль пользователя.
     *
     * @param string $password Хешированный пароль
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Очистить временные чувствительные данные пользователя.
     *
     * Вызывается Symfony Security после аутентификации
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * Получить дату создания записи пользователя.
     *
     * @return \DateTimeInterface|null Дата и время создания
     */
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Установить дату создания записи пользователя.
     *
     * @param \DateTimeInterface $createdAt Дата и время создания
     */
    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Получить дату последнего обновления записи пользователя.
     *
     * @return \DateTimeInterface|null Дата и время последнего обновления
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Установить дату последнего обновления записи пользователя.
     *
     * @param \DateTimeInterface $updatedAt Дата и время обновления
     */
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Автоматически обновить дату изменения записи.
     *
     * Вызывается Doctrine перед каждым обновлением записи
     *
     * @ORM\PreUpdate
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    /**
     * Получить имя пользователя.
     *
     * @return string|null Имя пользователя
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Установить имя пользователя.
     *
     * @param string|null $name Имя пользователя
     * @return $this
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
