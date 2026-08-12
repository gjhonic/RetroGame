<?php

namespace App\Service\OurGamePost;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Хранит картинку OurGamePost на локальной ФС в public/uploads/our_game_posts. */
class OurGamePostImageStorage
{
    private const BASE_DIR = 'uploads/our_game_posts';

    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    /**
     * Сохраняет файл в public/uploads/our_game_posts/{postId}/image/<случайное_имя>.<ext>
     * и удаляет $previousRelativePath, если он был задан.
     */
    public function store(int $postId, UploadedFile $file, ?string $previousRelativePath = null): string
    {
        $relativePath = $this->buildRelativePath($postId, 'image', $file);

        $this->filesystem->mkdir(\dirname($this->publicDir . '/' . $relativePath));
        $file->move(\dirname($this->publicDir . '/' . $relativePath), basename($relativePath));

        $this->remove($previousRelativePath);

        return $relativePath;
    }

    /**
     * Сохраняет картинку, вставленную в текст поста через редактор
     * (Admin/RichTextEditor.vue), в public/uploads/our_game_posts/{postId}/content/<случайное_имя>.<ext>.
     * В отличие от store() — без удаления предыдущего файла: картинок в тексте может быть много.
     */
    public function storeContentImage(int $postId, UploadedFile $file): string
    {
        $relativePath = $this->buildRelativePath($postId, 'content', $file);
        $fullPath = $this->publicDir . '/' . $relativePath;

        $this->filesystem->mkdir(\dirname($fullPath));
        $file->move(\dirname($fullPath), basename($fullPath));

        return $relativePath;
    }

    private function buildRelativePath(int $postId, string $subdir, UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?? 'jpg';

        return \sprintf(
            '%s/%d/%s/%s.%s',
            self::BASE_DIR,
            $postId,
            $subdir,
            bin2hex(random_bytes(8)),
            $extension,
        );
    }

    /** Удаляет файл по относительному (от public/) пути, если он существует. */
    public function remove(?string $relativePath): void
    {
        if ($relativePath === null) {
            return;
        }

        $fullPath = $this->publicDir . '/' . $relativePath;
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }
    }

    /** Удаляет всю директорию файлов поста — вызывается при удалении OurGamePost. */
    public function removeAllForPost(int $postId): void
    {
        $fullPath = $this->publicDir . '/' . self::BASE_DIR . '/' . $postId;
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }
    }
}
