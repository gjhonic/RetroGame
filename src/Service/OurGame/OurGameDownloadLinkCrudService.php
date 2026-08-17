<?php

namespace App\Service\OurGame;

use App\Dto\OurGame\OurGameDownloadLinkRequest;
use App\Entity\Enum\DownloadPlatform;
use App\Entity\OurGame;
use App\Entity\OurGameDownloadLink;
use Doctrine\ORM\EntityManagerInterface;

/** Создание/обновление/удаление ссылок на скачивание OurGame. */
class OurGameDownloadLinkCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OurGameImageStorage $imageStorage,
    ) {
    }

    public function create(OurGame $game, OurGameDownloadLinkRequest $request): OurGameDownloadLink
    {
        $link = new OurGameDownloadLink($game, DownloadPlatform::from($request->platform), $request->url);

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $link;
    }

    public function update(OurGameDownloadLink $link, OurGameDownloadLinkRequest $request): OurGameDownloadLink
    {
        $link->setPlatform(DownloadPlatform::from($request->platform));
        $link->setUrl($request->url);

        $this->entityManager->flush();

        return $link;
    }

    public function delete(OurGameDownloadLink $link): void
    {
        $imagePath = $link->getImagePath();

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        $this->imageStorage->remove($imagePath);
    }
}
