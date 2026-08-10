<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Отдаёт в шаблон случайный файл из public/media/<папка> — используется страницами ошибок (404/403). */
class RandomMediaExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('random_media', $this->randomMedia(...)),
        ];
    }

    /** @param non-empty-string $folder */
    public function randomMedia(string $folder): ?string
    {
        $files = glob(sprintf('%s/public/media/%s/*.{gif,webm,png,jpg,jpeg}', $this->projectDir, $folder), \GLOB_BRACE);

        if ($files === false || $files === []) {
            return null;
        }

        return '/media/' . $folder . '/' . basename($files[array_rand($files)]);
    }
}
