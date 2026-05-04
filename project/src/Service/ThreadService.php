<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Enum\ThreadStatus;
use App\Repository\ForumThreadRepository;
use Doctrine\ORM\EntityManagerInterface;

class ThreadService
{
    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ModerationService $moderationService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return ForumThread[]
     */
    public function feed(array $filters = []): array
    {
        return $this->threadRepository->findFeed($filters);
    }

    public function saveThread(ForumThread $thread): void
    {
        if ($this->moderationService->isToxic($thread->getTitle() ?? '') || $this->moderationService->isToxic($thread->getContent() ?? '')) {
            throw new \RuntimeException('Thread contains inappropriate content and cannot be published.');
        }

        $thread->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }

    public function deleteThread(ForumThread $thread): void
    {
        $this->entityManager->remove($thread);
        $this->entityManager->flush();
    }

    public function updateStatus(ForumThread $thread, ThreadStatus $status): void
    {
        $thread->setStatus($status);
        $thread->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function togglePin(ForumThread $thread): void
    {
        $thread->setIsPinned(!$thread->isPinned());
        $this->entityManager->flush();
    }

    public function canEdit(ForumThread $thread, ?string $userId): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }

        return $thread->getAuthorId() === $userId;
    }
}
