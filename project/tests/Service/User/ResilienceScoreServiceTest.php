<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;
use App\Service\User\ResilienceScoreService;
use PHPUnit\Framework\TestCase;

final class ResilienceScoreServiceTest extends TestCase
{
    public function testComputeReturnsStableForConsistentHighMoodTracking(): void
    {
        $user = $this->buildUser();
        $moodEntries = [
            $this->buildEntry($user, 4, '2026-04-01'),
            $this->buildEntry($user, 5, '2026-04-02'),
            $this->buildEntry($user, 4, '2026-04-03'),
        ];

        $moodRepository = $this->createMock(MoodEntryRepository::class);
        $moodRepository->method('findWithinDateRange')->willReturn($moodEntries);
        $moodRepository->method('countDistinctMoodDaysWithinRange')->willReturn(14);

        $journalRepository = $this->createMock(JournalEntryRepository::class);
        $journalRepository->method('countDistinctJournalDaysWithinRange')->willReturn(10);

        $service = new ResilienceScoreService($moodRepository, $journalRepository);
        $result = $service->compute($user, 14);

        self::assertGreaterThanOrEqual(70, $result['score']);
        self::assertSame('Stable', $result['label']);
    }

    private function buildUser(): User
    {
        return (new User())
            ->setId('test-user')
            ->setEmail('test@example.com')
            ->setPassword('secret')
            ->setRole('PATIENT')
            ->setPresenceStatus('OFFLINE')
            ->setAccountStatus('ACTIVE')
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
    }

    private function buildEntry(User $user, int $moodLevel, string $date): MoodEntry
    {
        return (new MoodEntry())
            ->setUser($user)
            ->setEntryDate(new \DateTimeImmutable($date))
            ->setMomentType('MOMENT')
            ->setMoodLevel($moodLevel)
            ->setUpdatedAt(new \DateTimeImmutable($date . ' 10:00:00'));
    }
}
