<?php

namespace App\Service\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Сохраняет аватар пользователя в public/uploads/avatars. Формат/размер
 * файла проверяются заранее валидатором (см. App\Dto\User\UploadAvatarRequest).
 */
class AvatarUploadService
{
    private const TARGET_SUBDIR = 'uploads/avatars';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function upload(User $user, UploadedFile $file): User
    {
        $extension = $file->guessExtension() ?? 'jpg';
        $relativePath = sprintf('%s/%d.%s', self::TARGET_SUBDIR, $user->getId(), $extension);
        $fullPath = $this->publicDir . '/' . $relativePath;

        $this->removeExistingAvatar($user, $relativePath);

        $this->filesystem->mkdir(\dirname($fullPath));
        $file->move(\dirname($fullPath), basename($fullPath));

        $user->setAvatarUrl($relativePath);
        $user->touch();

        $this->entityManager->flush();

        return $user;
    }

    /** Удаляет предыдущий файл аватара, если он существовал с другим расширением. */
    private function removeExistingAvatar(User $user, string $newRelativePath): void
    {
        $existingRelativePath = $user->getAvatarUrl();
        if ($existingRelativePath === null || $existingRelativePath === $newRelativePath) {
            return;
        }

        $this->filesystem->remove($this->publicDir . '/' . $existingRelativePath);
    }
}
