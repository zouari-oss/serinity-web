<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ForumThread;
use App\Enum\ThreadStatus;
use App\Repository\ForumThreadRepository;
use App\Service\ModerationService;
use App\Service\ThreadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ThreadServiceTest extends TestCase
{
    public function testFeedReturnsRepositoryResults(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);

        $threads = [$this->createMock(ForumThread::class)];
        $threadRepository->expects($this->once())
            ->method('findFeed')
            ->with(['status' => 'open'])
            ->willReturn($threads);

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);

        $this->assertSame($threads, $service->feed(['status' => 'open']));
    }

    public function testFeedDefaultsToEmptyFilters(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);

        $threads = [$this->createMock(ForumThread::class)];
        $threadRepository->expects($this->once())
            ->method('findFeed')
            ->with([])
            ->willReturn($threads);

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);

        $this->assertSame($threads, $service->feed());
    }

    public function testSaveThreadThrowsWhenToxic(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->method('getTitle')->willReturn('bad title');
        $thread->method('getContent')->willReturn('clean content');

        $moderationService->expects($this->once())
            ->method('isToxic')
            ->with('bad title')
            ->willReturn(true);

        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Thread contains inappropriate content');

        $service->saveThread($thread);
    }

   

    public function testSaveThreadPersistsCleanThread(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->method('getTitle')->willReturn('Good title');
        $thread->method('getContent')->willReturn('Good content');

        $moderationService->expects($this->exactly(2))
            ->method('isToxic')
            ->willReturnOnConsecutiveCalls(false, false);

        $thread->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->callback(static fn ($value): bool => $value instanceof \DateTimeImmutable));

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($thread);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);
        $service->saveThread($thread);
    }

    public function testDeleteThreadRemovesAndFlushes(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($thread);
        $entityManager->expects($this->once())
            ->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);
        $service->deleteThread($thread);
    }

    public function testUpdateStatusSetsStatusAndFlushes(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->expects($this->once())
            ->method('setStatus')
            ->with(ThreadStatus::OPEN);
        $thread->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->callback(static fn ($value): bool => $value instanceof \DateTimeImmutable));

        $entityManager->expects($this->once())
            ->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);
        $service->updateStatus($thread, ThreadStatus::OPEN);
    }

    public function testTogglePinFlipsPinnedState(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->expects($this->once())
            ->method('isPinned')
            ->willReturn(true);
        $thread->expects($this->once())
            ->method('setIsPinned')
            ->with(false);

        $entityManager->expects($this->once())
            ->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);
        $service->togglePin($thread);
    }

    public function testTogglePinFlipsFromFalseToTrue(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->expects($this->once())
            ->method('isPinned')
            ->willReturn(false);
        $thread->expects($this->once())
            ->method('setIsPinned')
            ->with(true);

        $entityManager->expects($this->once())
            ->method('flush');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);
        $service->togglePin($thread);
    }

    public function testCanEditReturnsTrueForMatchingAuthor(): void
    {
        $threadRepository = $this->createMock(ForumThreadRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $moderationService = $this->createMock(ModerationService::class);
        $thread = $this->createMock(ForumThread::class);

        $thread->method('getAuthorId')->willReturn('user-123');

        $service = new ThreadService($threadRepository, $entityManager, $moderationService);

        $this->assertTrue($service->canEdit($thread, 'user-123'));
        $this->assertFalse($service->canEdit($thread, 'user-999'));
        $this->assertFalse($service->canEdit($thread, null));
        $this->assertFalse($service->canEdit($thread, ''));
    }
}
