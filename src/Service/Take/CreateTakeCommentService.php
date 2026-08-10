<?php

namespace App\Service\Take;

use App\Dto\Take\CreateTakeCommentRequest;
use App\Entity\Take;
use App\Entity\TakeComment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Создаёт комментарий текущего пользователя к тэйку. */
class CreateTakeCommentService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(Take $take, User $author, CreateTakeCommentRequest $request): TakeComment
    {
        $comment = new TakeComment($take, $author, $request->text);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $comment;
    }
}
