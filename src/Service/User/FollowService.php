<?php

namespace App\Service\User;

use App\Entity\User;
use App\Entity\UserFollow;
use App\Repository\UserFollowRepository;
use App\Service\User\Exceptions\CannotFollowSelfException;
use Doctrine\ORM\EntityManagerInterface;

/** Подписка/отписка пользователя на другого пользователя — идемпотентные операции. */
class FollowService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserFollowRepository $userFollowRepository,
    ) {
    }

    /** @throws CannotFollowSelfException если $follower и $followed — один и тот же пользователь */
    public function follow(User $follower, User $followed): void
    {
        if ($follower->getId() !== null && $follower->getId() === $followed->getId()) {
            throw new CannotFollowSelfException('Нельзя подписаться на самого себя.');
        }

        if ($this->userFollowRepository->findOneByFollowerAndFollowed($follower, $followed) !== null) {
            return;
        }

        $this->entityManager->persist(new UserFollow($follower, $followed));
        $this->entityManager->flush();
    }

    /** Снимает подписку, если она есть — идемпотентно. */
    public function unfollow(User $follower, User $followed): void
    {
        $follow = $this->userFollowRepository->findOneByFollowerAndFollowed($follower, $followed);
        if ($follow === null) {
            return;
        }

        $this->entityManager->remove($follow);
        $this->entityManager->flush();
    }
}
