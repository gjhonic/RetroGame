<?php

namespace App\Tests\Unit\Service\OurGamePost;

use App\Service\OurGamePost\OurGamePostImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Работает с реальной файловой системой (временный каталог вместо public/) —
 * UploadedFile::move() выполняет настоящую операцию с файлом, мокать её не имеет смысла
 * (см. OurGameImageStorageTest — тот же паттерн).
 */
class OurGamePostImageStorageTest extends TestCase
{
    private string $publicDir;
    private OurGamePostImageStorage $storage;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir() . '/retrogame-our-game-post-test-' . uniqid();
        $this->storage = new OurGamePostImageStorage(new Filesystem(), $this->publicDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->publicDir);
    }

    private function makeUploadedJpeg(): UploadedFile
    {
        $sourcePath = sys_get_temp_dir() . '/retrogame-our-game-post-source-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $sourcePath);
        imagedestroy($image);

        return new UploadedFile($sourcePath, 'post.jpg', 'image/jpeg', null, true);
    }

    public function testStoreMovesFileUnderPostSubdir(): void
    {
        $relativePath = $this->storage->store(1, $this->makeUploadedJpeg());

        self::assertMatchesRegularExpression('#^uploads/our_game_posts/1/image/[0-9a-f]+\.jpg$#', $relativePath);
        self::assertFileExists($this->publicDir . '/' . $relativePath);
    }

    public function testStoreRemovesPreviousFileWhenGiven(): void
    {
        $firstPath = $this->storage->store(1, $this->makeUploadedJpeg());
        $fullFirstPath = $this->publicDir . '/' . $firstPath;
        self::assertFileExists($fullFirstPath);

        $secondPath = $this->storage->store(1, $this->makeUploadedJpeg(), $firstPath);

        self::assertFileDoesNotExist($fullFirstPath);
        self::assertFileExists($this->publicDir . '/' . $secondPath);
    }

    public function testRemoveDeletesExistingFileAndIgnoresMissingOne(): void
    {
        $relativePath = $this->storage->store(1, $this->makeUploadedJpeg());
        self::assertFileExists($this->publicDir . '/' . $relativePath);

        $this->storage->remove($relativePath);

        self::assertFileDoesNotExist($this->publicDir . '/' . $relativePath);
        $this->storage->remove(null);
        $this->storage->remove('uploads/our_game_posts/999/image/missing.jpg');
    }

    public function testRemoveAllForPostDeletesWholePostDirectory(): void
    {
        $this->storage->store(1, $this->makeUploadedJpeg());

        $this->storage->removeAllForPost(1);

        self::assertDirectoryDoesNotExist($this->publicDir . '/uploads/our_game_posts/1');
    }

    public function testStoreContentImageMovesFileUnderContentSubdirAndKeepsPreviousOnes(): void
    {
        $firstPath = $this->storage->storeContentImage(1, $this->makeUploadedJpeg());
        $secondPath = $this->storage->storeContentImage(1, $this->makeUploadedJpeg());

        self::assertMatchesRegularExpression('#^uploads/our_game_posts/1/content/[0-9a-f]+\.jpg$#', $firstPath);
        self::assertNotSame($firstPath, $secondPath);
        self::assertFileExists($this->publicDir . '/' . $firstPath);
        self::assertFileExists($this->publicDir . '/' . $secondPath);
    }
}
