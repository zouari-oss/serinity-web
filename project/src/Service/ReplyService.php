<?php

namespace App\Service;

use App\Entity\Reply;
use App\Model\CurrentUser;
use App\Enum\NotificationType;
use App\Enum\ThreadStatus;
use Doctrine\ORM\EntityManagerInterface;

class ReplyService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
        private readonly ModerationService $moderationService,
    ) {
    }

    public function add(Reply $reply, CurrentUser $actor): void
    {
        if ($this->moderationService->isToxic($reply->getContent() ?? '')) {
            throw new \RuntimeException('Reply contains inappropriate content and cannot be published.');
        }

        $thread = $reply->getThread();
        if ($thread === null) {
            throw new \RuntimeException('Reply must belong to a thread.');
        }

        if ($thread->getStatus() === ThreadStatus::LOCKED) {
            throw new \RuntimeException('Cannot add replies to a locked thread.');
        }

        $thread->setReplyCount($thread->getReplyCount() + 1);
        $this->entityManager->persist($reply);
        $this->entityManager->flush();

        $this->notificationService->createNotification($thread, NotificationType::COMMENT, $actor);
    }

    public function delete(Reply $reply): void
    {
        $thread = $reply->getThread();
        if ($thread !== null && $thread->getReplyCount() > 0) {
            $deletedCount = $this->countReplyTree($reply);
            $thread->setReplyCount(max(0, $thread->getReplyCount() - $deletedCount));
        }

        $this->entityManager->remove($reply);
        $this->entityManager->flush();
    }

    public function update(Reply $reply, string $newContent): void
    {
        $newContent = trim($newContent);
        if ($newContent === '') {
            throw new \RuntimeException('Reply content cannot be empty.');
        }

        if ($this->moderationService->isToxic($newContent)) {
            throw new \RuntimeException('Reply contains inappropriate content and cannot be published.');
        }

        $reply->setContent($newContent);
        $this->entityManager->flush();
    }

    public function canManage(Reply $reply, ?string $userId): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }

        return $reply->getAuthorId() === $userId;
    }

    private function countReplyTree(Reply $reply): int
    {
        $count = 1;
        foreach ($reply->getChildren() as $child) {
            $count += $this->countReplyTree($child);
        }

        return $count;
    }
}
