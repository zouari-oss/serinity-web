<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Entity\Reply;
use App\Enum\ThreadStatus;
use App\Enum\ThreadType;
use App\Repository\CategoryRepository;
use App\Repository\ForumThreadRepository;
use App\Repository\NotificationRepository;
use App\Repository\ReplyRepository;

class StatisticsService
{
    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly ReplyRepository $replyRepository,
        private readonly ProfileLookupService $profileLookupService,
        private readonly CategoryRepository $categoryRepository,
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getForumStatistics(): array
    {
        $threads = $this->threadRepository->findAll();
        $replies = $this->replyRepository->findAll();
        $totalLikes = 0;
        $totalDislikes = 0;
        $totalFollows = 0;

        foreach ($threads as $thread) {
            $totalLikes += $thread->getLikeCount();
            $totalDislikes += $thread->getDislikeCount();
            $totalFollows += $thread->getFollowCount();
        }

        $threadCountsByDay = $this->buildThreadCountsByDay($threads, 30);
        $topUsers = $this->buildTopUsersByActivity($threads, $replies, 10);
        $activeUsersToday = $this->countActiveUsersSince($threads, $replies, new \DateTimeImmutable('today'));
        $activeUsersThisWeek = $this->countActiveUsersSince($threads, $replies, new \DateTimeImmutable('-7 days'));

        return [
            'totalThreads' => count($threads),
            'openThreads' => count(array_filter($threads, fn ($t) => $t->getStatus() === ThreadStatus::OPEN)),
            'lockedThreads' => count(array_filter($threads, fn ($t) => $t->getStatus() === ThreadStatus::LOCKED)),
            'archivedThreads' => count(array_filter($threads, fn ($t) => $t->getStatus() === ThreadStatus::ARCHIVED)),
            'discussionThreads' => count(array_filter($threads, fn ($t) => $t->getType() === ThreadType::DISCUSSION)),
            'questionThreads' => count(array_filter($threads, fn ($t) => $t->getType() === ThreadType::QUESTION)),
            'announcementThreads' => count(array_filter($threads, fn ($t) => $t->getType() === ThreadType::ANNOUNCEMENT)),
            'totalUsers' => $this->profileLookupService->countProfiles(),
            'totalReplies' => count($replies),
            'totalCategories' => count($this->categoryRepository->findAll()),
            'totalLikes' => $totalLikes,
            'totalDislikes' => $totalDislikes,
            'totalFollows' => $totalFollows,
            'activeUsersToday' => $activeUsersToday,
            'activeUsersThisWeek' => $activeUsersThisWeek,
            'threadsPerDayLabels' => array_keys($threadCountsByDay),
            'threadsPerDayValues' => array_values($threadCountsByDay),
            'topUsersLabels' => array_column($topUsers, 'username'),
            'topUsersValues' => array_column($topUsers, 'activity'),
            'notificationsCount' => count($this->notificationRepository->findAll()),
        ];
    }

    /**
     * @param list<ForumThread> $threads
     * @return array<string, int>
     */
    private function buildThreadCountsByDay(array $threads, int $days): array
    {
        $startDate = (new \DateTimeImmutable('today'))->modify('-' . max(0, $days - 1) . ' days');
        $timeline = [];

        for ($i = 0; $i < $days; $i++) {
            $key = $startDate->modify('+' . $i . ' days')->format('m/d');
            $timeline[$key] = 0;
        }

        foreach ($threads as $thread) {
            $created = $thread->getCreatedAt();
            if ($created < $startDate) {
                continue;
            }

            $key = $created->format('m/d');
            if (array_key_exists($key, $timeline)) {
                $timeline[$key]++;
            }
        }

        return $timeline;
    }

    /**
     * @param list<ForumThread> $threads
     * @param list<Reply> $replies
     * @return list<array{username: string, activity: int}>
     */
    private function buildTopUsersByActivity(array $threads, array $replies, int $limit): array
    {
        $activityByUser = [];

        foreach ($threads as $thread) {
            $authorId = $thread->getAuthorId();
            if ($authorId === null || $authorId === '') {
                continue;
            }

            $userKey = $authorId;

            if (!isset($activityByUser[$userKey])) {
                $activityByUser[$userKey] = ['username' => 'Unknown', 'activity' => 0];
            }

            $activityByUser[$userKey]['activity']++;
        }

        foreach ($replies as $reply) {
            $authorId = $reply->getAuthorId();
            if ($authorId === null || $authorId === '') {
                continue;
            }

            $userKey = $authorId;

            if (!isset($activityByUser[$userKey])) {
                $activityByUser[$userKey] = ['username' => 'Unknown', 'activity' => 0];
            }

            $activityByUser[$userKey]['activity']++;
        }

        $usernames = $this->profileLookupService->usernamesByIds(array_keys($activityByUser));
        foreach ($activityByUser as $userKey => $row) {
            $activityByUser[$userKey]['username'] = $usernames[$userKey] ?? 'Unknown';
        }

        $rows = array_values($activityByUser);
        usort($rows, static fn (array $a, array $b): int => $b['activity'] <=> $a['activity']);

        $topRows = array_slice($rows, 0, $limit);

        return array_map(static function (array $row): array {
            $username = (string) $row['username'];
            if (mb_strlen($username) > 12) {
                $username = mb_substr($username, 0, 10) . '...';
            }

            return [
                'username' => $username,
                'activity' => (int) $row['activity'],
            ];
        }, $topRows);
    }

    /**
     * @param list<ForumThread> $threads
     * @param list<Reply> $replies
     */
    private function countActiveUsersSince(array $threads, array $replies, \DateTimeImmutable $since): int
    {
        $activeUsers = [];

        foreach ($threads as $thread) {
            if ($thread->getCreatedAt() < $since) {
                continue;
            }

            $authorId = $thread->getAuthorId();
            if ($authorId !== null && $authorId !== '') {
                $activeUsers[$authorId] = true;
            }
        }

        foreach ($replies as $reply) {
            if ($reply->getCreatedAt() < $since) {
                continue;
            }

            $authorId = $reply->getAuthorId();
            if ($authorId !== null && $authorId !== '') {
                $activeUsers[$authorId] = true;
            }
        }

        return count($activeUsers);
    }
}
