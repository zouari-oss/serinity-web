<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Entity\Notification;
use App\Model\CurrentUser;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function createNotification(ForumThread $thread, NotificationType $type, CurrentUser $actor): void
    {
        $recipientId = $thread->getAuthorId();
        if ($recipientId === null || $recipientId === '' || $recipientId === $actor->getId()) {
            return;
        }

        $notification = new Notification();
        $notification->setThread($thread);
        $notification->setRecipientId($recipientId);
        $notification->setType($type);
        $notification->setContent($this->buildMessage($thread, $type, $actor));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function markAsSeen(Notification $notification): void
    {
        $this->entityManager->remove($notification);
        $this->entityManager->flush();
    }

    public function markAllAsSeen(string $userId): void
    {
        foreach ($this->notificationRepository->findForUser($userId) as $notification) {
            $this->entityManager->remove($notification);
        }

        $this->entityManager->flush();
    }

    private function buildMessage(ForumThread $thread, NotificationType $type, CurrentUser $actor): string
    {
        return match ($type) {
            NotificationType::LIKE => sprintf('%s liked your thread "%s"', $actor->getUsername(), $thread->getTitle()),
            NotificationType::DISLIKE => sprintf('%s disliked your thread "%s"', $actor->getUsername(), $thread->getTitle()),
            NotificationType::FOLLOW => sprintf('%s followed your thread "%s"', $actor->getUsername(), $thread->getTitle()),
            NotificationType::COMMENT => sprintf('%s commented on your thread "%s"', $actor->getUsername(), $thread->getTitle()),
        };
    }
}
