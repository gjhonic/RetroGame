<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Service\User\AvatarUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Работает с реальной файловой системой (временный каталог вместо public/) —
 * UploadedFile::move() выполняет настоящую операцию с файлом, мокать её не имеет смысла.
 */
class AvatarUploadServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AvatarUploadService $service;
    private string $publicDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->publicDir = sys_get_temp_dir() . '/retrogame-avatar-test-' . uniqid();

        $this->service = new AvatarUploadService($this->entityManager, new Filesystem(), $this->publicDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->publicDir);
    }

    private function makeUploadedJpeg(): UploadedFile
    {
        $sourcePath = sys_get_temp_dir() . '/retrogame-avatar-source-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        return new UploadedFile($sourcePath, 'avatar.jpg', 'image/jpeg', null, true);
    }

    public function testUploadMovesFileAndSetsAvatarUrl(): void
    {
        $user = new User('player@retrogame.local', 'hash');
        $this->setUserId($user, 42);
        $this->entityManager->expects($this->once())->method('flush');

        $updatedUser = $this->service->upload($user, $this->makeUploadedJpeg());

        self::assertSame('uploads/avatars/42.jpg', $updatedUser->getAvatarUrl());
        self::assertFileExists($this->publicDir . '/uploads/avatars/42.jpg');
    }

    public function testUploadRemovesPreviousAvatarWithDifferentExtension(): void
    {
        $user = new User('player@retrogame.local', 'hash');
        $this->setUserId($user, 42);

        $oldAvatarPath = $this->publicDir . '/uploads/avatars/42.png';
        (new Filesystem())->dumpFile($oldAvatarPath, 'old-avatar-content');
        $user->setAvatarUrl('uploads/avatars/42.png');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->upload($user, $this->makeUploadedJpeg());

        self::assertFileDoesNotExist($oldAvatarPath);
        self::assertFileExists($this->publicDir . '/uploads/avatars/42.jpg');
    }

    private function setUserId(User $user, int $id): void
    {
        $reflection = new \ReflectionProperty($user, 'id');
        $reflection->setValue($user, $id);
    }
}
