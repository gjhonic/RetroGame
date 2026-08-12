<?php

namespace App\Service\OurGamePost;

use App\Dto\OurGamePost\OurGamePostRequest;
use App\Entity\Enum\OurGamePostType;
use App\Entity\Enum\OurGameStatus;
use App\Entity\OurGamePost;
use App\Entity\User;
use App\Repository\OurGameRepository;
use App\Service\OurGamePost\Exceptions\OurGameNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

/** Создание/обновление/удаление OurGamePost — вынесено из контроллера по правилам modules.md. */
class OurGamePostCrudService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OurGameRepository $ourGameRepository,
        private readonly OurGamePostImageStorage $imageStorage,
    ) {
    }

    /** @throws OurGameNotFoundException если игра с указанным gameId не найдена */
    public function create(User $author, OurGamePostRequest $request): OurGamePost
    {
        $game = $this->ourGameRepository->find($request->gameId);
        if ($game === null) {
            throw new OurGameNotFoundException('Игра не найдена.');
        }

        $post = new OurGamePost(
            $game,
            $author,
            OurGamePostType::from($request->type),
            new \DateTimeImmutable($request->postedAt),
            $request->title,
            $request->shortDescription,
            OurGameStatus::from($request->status),
        );
        $post->setFullDescription($this->nullIfBlank($request->fullDescription));

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    /** @throws OurGameNotFoundException если игра с указанным gameId не найдена */
    public function update(OurGamePost $post, OurGamePostRequest $request): OurGamePost
    {
        $game = $this->ourGameRepository->find($request->gameId);
        if ($game === null) {
            throw new OurGameNotFoundException('Игра не найдена.');
        }

        $post->setGame($game);
        $post->setType(OurGamePostType::from($request->type));
        $post->setStatus(OurGameStatus::from($request->status));
        $post->setPostedAt(new \DateTimeImmutable($request->postedAt));
        $post->setTitle($request->title);
        $post->setShortDescription($request->shortDescription);
        $post->setFullDescription($this->nullIfBlank($request->fullDescription));
        $post->touch();

        $this->entityManager->flush();

        return $post;
    }

    public function delete(OurGamePost $post): void
    {
        $id = $post->getId();
        $this->entityManager->remove($post);
        $this->entityManager->flush();

        if ($id !== null) {
            $this->imageStorage->removeAllForPost($id);
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
