<?php

namespace App\Service\OurGamePost;

use App\Entity\OurGamePost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Загрузка картинки OurGamePost — связывает OurGamePostImageStorage (файлы) с сущностью и flush(). */
class OurGamePostImageUploadService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OurGamePostImageStorage $imageStorage,
    ) {
    }

    public function upload(OurGamePost $post, UploadedFile $file): OurGamePost
    {
        $path = $this->imageStorage->store((int) $post->getId(), $file, $post->getImagePath());
        $post->setImagePath($path)->touch();
        $this->entityManager->flush();

        return $post;
    }

    /**
     * Картинка, вставленная в текст поста через редактор — не привязана к отдельному
     * полю сущности (ссылка на неё живёт прямо в HTML shortDescription/fullDescription),
     * поэтому flush() здесь не нужен.
     */
    public function uploadContentImage(OurGamePost $post, UploadedFile $file): string
    {
        return $this->imageStorage->storeContentImage((int) $post->getId(), $file);
    }
}
