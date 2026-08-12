<?php

namespace App\Entity;

use App\Entity\Enum\DownloadPlatform;
use App\Repository\OurGameDownloadLinkRepository;
use Doctrine\ORM\Mapping as ORM;

/** Ссылка на скачивание OurGame под конкретную платформу, с иконкой кнопки. */
#[ORM\Entity(repositoryClass: OurGameDownloadLinkRepository::class)]
class OurGameDownloadLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: OurGame::class, inversedBy: 'downloadLinks')]
    #[ORM\JoinColumn(name: 'our_game_id', nullable: false, onDelete: 'CASCADE')]
    private OurGame $ourGame;

    #[ORM\Column(length: 20, enumType: DownloadPlatform::class)]
    private DownloadPlatform $platform;

    #[ORM\Column(length: 500)]
    private string $url;

    /** Иконка/картинка кнопки скачивания (относительно public/). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** Создаёт ссылку на скачивание с обязательными полями, картинка задаётся отдельно. */
    public function __construct(OurGame $ourGame, DownloadPlatform $platform, string $url)
    {
        $this->ourGame = $ourGame;
        $this->platform = $platform;
        $this->url = $url;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** Возвращает ID ссылки. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Возвращает игру, к которой относится ссылка. */
    public function getOurGame(): OurGame
    {
        return $this->ourGame;
    }

    /** Возвращает платформу скачивания. */
    public function getPlatform(): DownloadPlatform
    {
        return $this->platform;
    }

    /** Задаёт платформу скачивания. */
    public function setPlatform(DownloadPlatform $platform): static
    {
        $this->platform = $platform;

        return $this->touch();
    }

    /** Возвращает URL скачивания. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** Задаёт URL скачивания. */
    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this->touch();
    }

    /** Возвращает путь к иконке кнопки скачивания (относительно public/). */
    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    /** Задаёт путь к иконке кнопки скачивания. */
    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this->touch();
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
