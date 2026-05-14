<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ForumController;
use App\Entity\ForumThread;
use App\Enum\ThreadStatus;
use App\Enum\ThreadType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;


class ForumControllerTest extends TestCase
{


    private ForumController $controller;

    protected function setUp(): void
    {
        // Expose private methods through a subclass
        $this->controller = new class extends ForumController {
            public function publicApplyThreadFilters(array $threads, Request $request): array
            {
                return $this->applyThreadFilters($threads, $request);
            }

            public function publicCompareThreads(ForumThread $a, ForumThread $b, string $sort): int
            {
                return $this->compareThreads($a, $b, $sort);
            }

            public function publicReadSort(Request $request): string
            {
                return $this->readSort($request);
            }

            public function publicReadStatuses(Request $request): array
            {
                return $this->readStatuses($request);
            }

            public function publicReadTypes(Request $request): array
            {
                return $this->readTypes($request);
            }

            public function publicReadCategoryIds(Request $request): array
            {
                return $this->readCategoryIds($request);
            }
        };
    }



    public function testReadSortReturnsDefaultWhenMissing(): void
    {
        $request = new Request();
        $this->assertSame('most_followers', $this->controller->publicReadSort($request));
    }

    public function testReadSortReturnsValidValue(): void
    {
        $request = new Request(['sort' => 'oldest']);
        $this->assertSame('oldest', $this->controller->publicReadSort($request));
    }

    public function testReadSortFallsBackToDefaultForInvalidValue(): void
    {
        $request = new Request(['sort' => 'invalid_value']);
        $this->assertSame('most_followers', $this->controller->publicReadSort($request));
    }

    #[DataProvider('validSortProvider')]
    public function testReadSortAcceptsAllValidValues(string $sort): void
    {
        $request = new Request(['sort' => $sort]);
        $this->assertSame($sort, $this->controller->publicReadSort($request));
    }

    public static function validSortProvider(): array
    {
        return [
            ['newest'], ['oldest'], ['most_points'], ['least_points'],
            ['most_comments'], ['least_comments'], ['most_followers'], ['least_followers'],
        ];
    }

    // =========================================================================
    // readStatuses()
    // =========================================================================

    public function testReadStatusesReturnsEmptyArrayWhenMissing(): void
    {
        $request = new Request();
        $this->assertSame([], $this->controller->publicReadStatuses($request));
    }

    public function testReadStatusesFiltersInvalidValues(): void
    {
        $request = new Request(['status' => ['open', 'archived', 'invalid']]);
        // 'archived' is not in the allowed list; 'invalid' is not either
        $this->assertSame(['open'], $this->controller->publicReadStatuses($request));
    }

    public function testReadStatusesDeduplicates(): void
    {
        $request = new Request(['status' => ['open', 'open', 'locked']]);
        $result = $this->controller->publicReadStatuses($request);
        $this->assertCount(2, $result);
        $this->assertContains('open', $result);
        $this->assertContains('locked', $result);
    }

    // =========================================================================
    // readTypes()
    // =========================================================================

    public function testReadTypesReturnsEmptyArrayWhenMissing(): void
    {
        $request = new Request();
        $this->assertSame([], $this->controller->publicReadTypes($request));
    }

    public function testReadTypesFiltersInvalidValues(): void
    {
        $request = new Request(['type' => ['discussion', 'unknown_type']]);
        $this->assertSame(['discussion'], $this->controller->publicReadTypes($request));
    }

    public function testReadTypesDeduplicates(): void
    {
        $request = new Request(['type' => ['question', 'question']]);
        $this->assertCount(1, $this->controller->publicReadTypes($request));
    }

    // =========================================================================
    // readCategoryIds()
    // =========================================================================

    public function testReadCategoryIdsReturnsEmptyArrayWhenMissing(): void
    {
        $request = new Request();
        $this->assertSame([], $this->controller->publicReadCategoryIds($request));
    }

    public function testReadCategoryIdsReturnsIntegers(): void
    {
        $request = new Request(['category' => ['1', '2', '3']]);
        $this->assertSame([1, 2, 3], $this->controller->publicReadCategoryIds($request));
    }

    public function testReadCategoryIdsFiltersNonDigitValues(): void
    {
        $request = new Request(['category' => ['1', 'abc', '5']]);
        $this->assertSame([1, 5], $this->controller->publicReadCategoryIds($request));
    }

    public function testReadCategoryIdsDeduplicates(): void
    {
        $request = new Request(['category' => ['3', '3', '7']]);
        $this->assertSame([3, 7], $this->controller->publicReadCategoryIds($request));
    }

    // =========================================================================
    // compareThreads()
    // =========================================================================

    private function makeThread(
        int $likes = 0,
        int $dislikes = 0,
        int $replies = 0,
        int $follows = 0,
        ?\DateTimeImmutable $createdAt = null,
    ): ForumThread {
        $thread = $this->createMock(ForumThread::class);
        $thread->method('getLikeCount')->willReturn($likes);
        $thread->method('getDislikeCount')->willReturn($dislikes);
        $thread->method('getReplyCount')->willReturn($replies);
        $thread->method('getFollowCount')->willReturn($follows);
        $thread->method('getCreatedAt')->willReturn($createdAt ?? new \DateTimeImmutable());
        return $thread;
    }

    public function testCompareThreadsNewestPutsLaterThreadFirst(): void
    {
        $older = $this->makeThread(createdAt: new \DateTimeImmutable('2024-01-01'));
        $newer = $this->makeThread(createdAt: new \DateTimeImmutable('2024-06-01'));

        // newest: newer > older  → result < 0 (b before a when sorted descending)
        $result = $this->controller->publicCompareThreads($older, $newer, 'newest');
        $this->assertGreaterThan(0, $result); // older comes AFTER newer
    }

    public function testCompareThreadsOldestPutsEarlierThreadFirst(): void
    {
        $older = $this->makeThread(createdAt: new \DateTimeImmutable('2024-01-01'));
        $newer = $this->makeThread(createdAt: new \DateTimeImmutable('2024-06-01'));

        $result = $this->controller->publicCompareThreads($older, $newer, 'oldest');
        $this->assertLessThan(0, $result); // older comes BEFORE newer
    }

    public function testCompareThreadsMostPointsPutsHigherScoreFirst(): void
    {
        $low  = $this->makeThread(likes: 2, dislikes: 1);  // net 1
        $high = $this->makeThread(likes: 10, dislikes: 2); // net 8

        $result = $this->controller->publicCompareThreads($low, $high, 'most_points');
        $this->assertGreaterThan(0, $result); // low comes AFTER high
    }

    public function testCompareThreadsMostCommentsOrdersCorrectly(): void
    {
        $few  = $this->makeThread(replies: 1);
        $many = $this->makeThread(replies: 50);

        $result = $this->controller->publicCompareThreads($few, $many, 'most_comments');
        $this->assertGreaterThan(0, $result); // few comes AFTER many
    }

    public function testCompareThreadsLeastCommentsOrdersCorrectly(): void
    {
        $few  = $this->makeThread(replies: 1);
        $many = $this->makeThread(replies: 50);

        $result = $this->controller->publicCompareThreads($few, $many, 'least_comments');
        $this->assertLessThan(0, $result); // few comes BEFORE many
    }

    public function testCompareThreadsUnknownSortFallsBackToNewest(): void
    {
        $older = $this->makeThread(createdAt: new \DateTimeImmutable('2024-01-01'));
        $newer = $this->makeThread(createdAt: new \DateTimeImmutable('2024-06-01'));

        // default (newest): newer > older
        $result = $this->controller->publicCompareThreads($older, $newer, 'non_existent_sort');
        $this->assertGreaterThan(0, $result);
    }

    // =========================================================================
    // applyThreadFilters() — keyword search, status, type, pinned segregation
    // =========================================================================

    private function makeFullThread(
        string $title = 'Thread',
        bool $pinned = false,
        ?ThreadStatus $status = null,
        ?ThreadType $type = null,
        ?\DateTimeImmutable $createdAt = null,
    ): ForumThread {
        $thread = $this->createMock(ForumThread::class);
        $thread->method('getTitle')->willReturn($title);
        $thread->method('isPinned')->willReturn($pinned);
        $thread->method('getStatus')->willReturn($status ?? ThreadStatus::OPEN);
        $thread->method('getType')->willReturn($type ?? ThreadType::DISCUSSION);
        $thread->method('getCreatedAt')->willReturn($createdAt ?? new \DateTimeImmutable());
        $thread->method('getCategory')->willReturn(null);
        $thread->method('getLikeCount')->willReturn(0);
        $thread->method('getDislikeCount')->willReturn(0);
        $thread->method('getReplyCount')->willReturn(0);
        $thread->method('getFollowCount')->willReturn(0);
        return $thread;
    }

    public function testApplyThreadFiltersReturnsAllWhenNoFilters(): void
    {
        $threads = [
            $this->makeFullThread('Alpha'),
            $this->makeFullThread('Beta'),
        ];

        $result = $this->controller->publicApplyThreadFilters($threads, new Request());
        $this->assertCount(2, $result);
    }

    public function testApplyThreadFiltersSearchByTitle(): void
    {
        $threads = [
            $this->makeFullThread('PHP Unit Testing'),
            $this->makeFullThread('Symfony Routing'),
            $this->makeFullThread('PHP Generics'),
        ];

        $request = new Request(['q' => 'php']);
        $result  = $this->controller->publicApplyThreadFilters($threads, $request);

        $this->assertCount(2, $result);
    }

    public function testApplyThreadFiltersSearchIsCaseInsensitive(): void
    {
        $threads = [
            $this->makeFullThread('Hello World'),
            $this->makeFullThread('Goodbye'),
        ];

        $result = $this->controller->publicApplyThreadFilters($threads, new Request(['q' => 'HELLO']));
        $this->assertCount(1, $result);
    }

    public function testApplyThreadFiltersSeparatesPinnedThreads(): void
    {
        $pinned   = $this->makeFullThread('Pinned', pinned: true);
        $normal   = $this->makeFullThread('Normal', pinned: false);

        $result = $this->controller->publicApplyThreadFilters([$normal, $pinned], new Request());

        // Pinned thread must come first
        $this->assertSame($pinned, $result[0]);
        $this->assertSame($normal, $result[1]);
    }

    public function testApplyThreadFiltersFiltersByStatus(): void
    {
        $open   = $this->makeFullThread('Open thread', status: ThreadStatus::OPEN);
        $locked = $this->makeFullThread('Locked thread', status: ThreadStatus::LOCKED);

        $request = new Request(['status' => ['open']]);
        $result  = $this->controller->publicApplyThreadFilters([$open, $locked], $request);

        $this->assertCount(1, $result);
        $this->assertSame($open, $result[0]);
    }

    public function testApplyThreadFiltersEmptySearchReturnsAll(): void
    {
        $threads = [
            $this->makeFullThread('Alpha'),
            $this->makeFullThread('Beta'),
        ];

        // Whitespace-only search should be treated as no filter
        $result = $this->controller->publicApplyThreadFilters($threads, new Request(['q' => '   ']));
        $this->assertCount(2, $result);
    }
}
