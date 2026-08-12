<?php

namespace App\Service\OurGame;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Хранит картинки OurGame (обложка/баннер/скриншоты/иконки ссылок) на локальной ФС в public/uploads/our_games. */
class OurGameImageStorage
{
    private const BASE_DIR = 'uploads/our_games';

    public function __construct(
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    /**
     * Сохраняет файл в public/uploads/our_games/{ourGameId}/{subdir}/<случайное_имя>.<ext>
     * и удаляет $previousRelativePath, если он был задан.
     */
    public function store(
        int $ourGameId,
        string $subdir,
        UploadedFile $file,
        ?string $previousRelativePath = null,
    ): string {
        $extension = $file->guessExtension() ?? 'jpg';
        $relativePath = \sprintf(
            '%s/%d/%s/%s.%s',
            self::BASE_DIR,
            $ourGameId,
            $subdir,
            bin2hex(random_bytes(8)),
            $extension,
        );
        $fullPath = $this->publicDir . '/' . $relativePath;

        $this->filesystem->mkdir(\dirname($fullPath));
        $file->move(\dirname($fullPath), basename($fullPath));

        $this->remove($previousRelativePath);

        return $relativePath;
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

    /** Удаляет всю директорию файлов игры — вызывается при удалении OurGame. */
    public function removeAllForGame(int $ourGameId): void
    {
        $fullPath = $this->publicDir . '/' . self::BASE_DIR . '/' . $ourGameId;
        if ($this->filesystem->exists($fullPath)) {
            $this->filesystem->remove($fullPath);
        }
    }
}
