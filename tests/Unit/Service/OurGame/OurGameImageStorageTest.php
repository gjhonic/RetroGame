<?php

namespace App\Tests\Unit\Service\OurGame;

use App\Service\OurGame\OurGameImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Работает с реальной файловой системой (временный каталог вместо public/) —
 * UploadedFile::move() выполняет настоящую операцию с файлом, мокать её не имеет смысла
 * (см. AvatarUploadServiceTest — тот же паттерн).
 */
class OurGameImageStorageTest extends TestCase
{
    private string $publicDir;
    private OurGameImageStorage $storage;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/retrogame-our-game-test-' . uniqid();
        $this->storage = new OurGameImageStorage(new Filesystem(), $this->publicDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->publicDir);
    }

    private function makeUploadedJpeg(): UploadedFile
    {
        $sourcePath = sys_get_temp_dir() . '/retrogame-our-game-source-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        return new UploadedFile($sourcePath, 'cover.jpg', 'image/jpeg', null, true);
    }

    public function testStoreMovesFileUnderOurGameSubdir(): void
    {
        $relativePath = $this->storage->store(1, 'cover', $this->makeUploadedJpeg());

        self::assertMatchesRegularExpression('#^uploads/our_games/1/cover/[0-9a-f]+\.jpg$#', $relativePath);
        self::assertFileExists($this->publicDir . '/' . $relativePath);
    }

    public function testStoreRemovesPreviousFileWhenGiven(): void
    {
        $firstPath = $this->storage->store(1, 'cover', $this->makeUploadedJpeg());
        $fullFirstPath = $this->publicDir . '/' . $firstPath;
        self::assertFileExists($fullFirstPath);

        $secondPath = $this->storage->store(1, 'cover', $this->makeUploadedJpeg(), $firstPath);

        self::assertFileDoesNotExist($fullFirstPath);
        self::assertFileExists($this->publicDir . '/' . $secondPath);
    }

    public function testRemoveDeletesExistingFileAndIgnoresMissingOne(): void
    {
        $relativePath = $this->storage->store(1, 'screenshots', $this->makeUploadedJpeg());
        self::assertFileExists($this->publicDir . '/' . $relativePath);

        $this->storage->remove($relativePath);

        self::assertFileDoesNotExist($this->publicDir . '/' . $relativePath);
        $this->storage->remove(null);
        $this->storage->remove('uploads/our_games/999/cover/missing.jpg');
    }

    public function testRemoveAllForGameDeletesWholeGameDirectory(): void
    {
        $this->storage->store(1, 'cover', $this->makeUploadedJpeg());
        $this->storage->store(1, 'screenshots', $this->makeUploadedJpeg());

        $this->storage->removeAllForGame(1);

        self::assertDirectoryDoesNotExist($this->publicDir . '/uploads/our_games/1');
    }
}
