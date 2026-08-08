<?php

namespace App\Service\Image;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Скачивает обложки игр в public/uploads/games, чтобы не зависеть
 * от доступности внешнего CDN (Steam и т.п.).
 */
class GameImageDownloader
{
    private const TARGET_SUBDIR = 'uploads/games';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    /**
     * Скачивает обложку игры по $url и сохраняет как {steamAppId}.{расширение}.
     * Возвращает относительный от public/ путь или null, если скачать не удалось —
     * это не критическая ошибка импорта, а не повод проваливать всю игру.
     */
    public function downloadCover(string $url, int $steamAppId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url);
            $content = $response->getContent();
        } catch (ExceptionInterface) {
            return null;
        }

        $relativePath = sprintf('%s/%d.%s', self::TARGET_SUBDIR, $steamAppId, $this->guessExtension($url));
        $fullPath = $this->publicDir . '/' . $relativePath;

        $this->filesystem->mkdir(dirname($fullPath));
        $this->filesystem->dumpFile($fullPath, $content);

        return $relativePath;
    }

    /** Определяет расширение файла по URL, по умолчанию — jpg. */
    private function guessExtension(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'jpg';
    }
}
