<?php

namespace App\Service\OurGame;

use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Загрузка/удаление картинок OurGame — связывает OurGameImageStorage (файлы) с сущностями и flush(). */
class OurGameImageUploadService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OurGameImageStorage $imageStorage,
    ) {
    }

    public function uploadCover(OurGame $game, UploadedFile $file): OurGame
    {
        $path = $this->imageStorage->store((int) $game->getId(), 'cover', $file, $game->getCoverImagePath());
        $game->setCoverImagePath($path)->touch();
        $this->entityManager->flush();

        return $game;
    }

    public function uploadBanner(OurGame $game, UploadedFile $file): OurGame
    {
        $path = $this->imageStorage->store((int) $game->getId(), 'banner', $file, $game->getBannerImagePath());
        $game->setBannerImagePath($path)->touch();
        $this->entityManager->flush();

        return $game;
    }

    public function addScreenshot(OurGame $game, UploadedFile $file): OurGame
    {
        $path = $this->imageStorage->store((int) $game->getId(), 'screenshots', $file);

        $screenshots = $game->getScreenshotUrls() ?? [];
        $screenshots[] = $path;
        $game->setScreenshotUrls($screenshots)->touch();
        $this->entityManager->flush();

        return $game;
    }

    /** $urlOrPath — то, что вернул OurGameMapper (URL с ведущим "/") или уже относительный путь. */
    public function removeScreenshot(OurGame $game, string $urlOrPath): OurGame
    {
        $relativePath = ltrim($urlOrPath, '/');

        $remaining = array_values(array_filter(
            $game->getScreenshotUrls() ?? [],
            static fn (string $path): bool => $path !== $relativePath,
        ));
        $game->setScreenshotUrls($remaining === [] ? null : $remaining)->touch();

        $this->imageStorage->remove($relativePath);
        $this->entityManager->flush();

        return $game;
    }

    /**
     * Картинка, вставленная в описание игры через редактор (Admin/RichTextEditor.vue) —
     * не привязана к отдельному полю сущности (ссылка на неё живёт прямо в HTML
     * description), поэтому flush() здесь не нужен.
     */
    public function uploadContentImage(OurGame $game, UploadedFile $file): string
    {
        return $this->imageStorage->store((int) $game->getId(), 'content', $file);
    }

    public function uploadDownloadLinkImage(OurGameDownloadLink $link, UploadedFile $file): OurGameDownloadLink
    {
        $path = $this->imageStorage->store(
            (int) $link->getOurGame()->getId(),
            'downloads',
            $file,
            $link->getImagePath(),
        );
        $link->setImagePath($path);
        $this->entityManager->flush();

        return $link;
    }
}
