<?php

namespace App\Entity;

use App\Entity\Enum\AuditLogStatus;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

/** Запись журнала действий: кто, когда, что сделал и с каким результатом — неизменяема после создания. */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Index(columns: ['action'], name: 'IDX_AUDIT_LOG_ACTION')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_AUDIT_LOG_CREATED_AT')]
#[ORM\Index(columns: ['status'], name: 'IDX_AUDIT_LOG_STATUS')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Автор действия — null для анонимных/системных действий (например, неудачный вход с несуществующим email). */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    /** Тип действия в точечной нотации, например "user.login", "user.register". */
    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(length: 10, enumType: AuditLogStatus::class)]
    private AuditLogStatus $status;

    /**
     * Произвольные подробности действия (форма зависит от action).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed>|null $details */
    public function __construct(?User $user, string $action, AuditLogStatus $status, ?array $details = null)
    {
        $this->user = $user;
        $this->action = $action;
        $this->status = $status;
        $this->details = $details;
        $this->createdAt = new \DateTimeImmutable();
    }

    /** Возвращает ID записи. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает автора действия (null для анонимных/системных действий). */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /** Возвращает тип действия. */
    public function getAction(): string
    {
        return $this->action;
    }

    /** Возвращает результат действия. */
    public function getStatus(): AuditLogStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed>|null */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /** Возвращает дату действия. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
