<?php

namespace App\Entity;

use App\Entity\Enum\UserReportType;
use App\Repository\UserReportRepository;
use Doctrine\ORM\Mapping as ORM;

/** Отчёт пользователя разработчикам о проблеме на сайте, в приложении или в игре DIE//AGAIN. */
#[ORM\Entity(repositoryClass: UserReportRepository::class)]
#[ORM\Index(columns: ['type'], name: 'IDX_USER_REPORT_TYPE')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_USER_REPORT_CREATED_AT')]
class UserReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'smallint', enumType: UserReportType::class)]
    private UserReportType $type;

    #[ORM\Column(type: 'text')]
    private string $comment;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(UserReportType $type, string $comment)
    {
        $this->type = $type;
        $this->comment = $comment;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): UserReportType
    {
        return $this->type;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
