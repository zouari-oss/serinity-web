<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Entity\PostInteraction;
use App\Model\CurrentUser;
use App\Enum\NotificationType;
use App\Repository\PostInteractionRepository;
use Doctrine\ORM\EntityManagerInterface;

class InteractionService
{
    public function __construct(
        private readonly PostInteractionRepository $interactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function toggleUpvote(ForumThread $thread, CurrentUser $user): void
    {
        $interaction = $this->getOrCreate($thread, $user);
        $interaction->setVote($interaction->getVote() === 1 ? 0 : 1);
        $this->entityManager->flush();
        $this->recalculate($thread);

        if ($interaction->getVote() === 1) {
            $this->notificationService->createNotification($thread, NotificationType::LIKE, $user);
        }
    }

    public function toggleDownvote(ForumThread $thread, CurrentUser $user): void
    {
        $interaction = $this->getOrCreate($thread, $user);
        $interaction->setVote($interaction->getVote() === -1 ? 0 : -1);
        $this->entityManager->flush();
        $this->recalculate($thread);

        if ($interaction->getVote() === -1) {
            $this->notificationService->createNotification($thread, NotificationType::DISLIKE, $user);
        }
    }

    public function toggleFollow(ForumThread $thread, CurrentUser $user): void
    {
        $interaction = $this->getOrCreate($thread, $user);
        $interaction->setFollow(!$interaction->isFollow());
        $this->entityManager->flush();
        $this->recalculate($thread);

        if ($interaction->isFollow()) {
            $this->notificationService->createNotification($thread, NotificationType::FOLLOW, $user);
        }
    }

    public function getInteraction(ForumThread $thread, CurrentUser $user): ?PostInteraction
    {
        return $this->interactionRepository->findOneForUser($thread, $user->getId());
    }

    private function getOrCreate(ForumThread $thread, CurrentUser $user): PostInteraction
    {
        $interaction = $this->interactionRepository->findOneForUser($thread, $user->getId());
        if ($interaction !== null) {
            return $interaction;
        }

        $interaction = new PostInteraction();
        $interaction->setThread($thread);
        $interaction->setUserId($user->getId());
        $this->entityManager->persist($interaction);

        return $interaction;
    }

    private function recalculate(ForumThread $thread): void
    {
        $likes = 0;
        $dislikes = 0;
        $follows = 0;

        foreach ($thread->getInteractions() as $interaction) {
            if ($interaction->getVote() === 1) {
                ++$likes;
            }
            if ($interaction->getVote() === -1) {
                ++$dislikes;
            }
            if ($interaction->isFollow()) {
                ++$follows;
            }
        }

        $thread->setLikeCount($likes);
        $thread->setDislikeCount($dislikes);
        $thread->setFollowCount($follows);
        $this->entityManager->flush();
    }
}
