<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Entity\MoodEntry;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;
use App\Service\User\CriticalPeriodDetectionService;
use App\Service\User\RecoveryPlanService;
use App\Service\User\ResilienceScoreService;
use PHPUnit\Framework\TestCase;

final class RecoveryPlanServiceTest extends TestCase
{
    public function testGenerateAdaptsPlanForWarningState(): void
    {
        $user = $this->buildUser();

        $criticalMoodRepository = $this->createMock(MoodEntryRepository::class);
        $criticalMoodRepository->method('findWithinDateRange')->willReturn([
            $this->buildEntry($user, 3, '2026-04-11'),
            $this->buildEntry($user, 2, '2026-04-12'),
            $this->buildEntry($user, 3, '2026-04-13'),
        ]);

        $criticalJournalRepository = $this->createMock(JournalEntryRepository::class);
        $criticalJournalRepository->method('countEntriesWithinRange')->willReturn(2);
        $criticalJournalRepository->method('countDistinctJournalDaysWithinRange')->willReturn(2);

        $criticalService = new CriticalPeriodDetectionService($criticalMoodRepository, $criticalJournalRepository);

        $resilienceMoodRepository = $this->createMock(MoodEntryRepository::class);
        $resilienceMoodRepository->method('findWithinDateRange')->willReturn([
            $this->buildEntry($user, 3, '2026-04-05'),
            $this->buildEntry($user, 3, '2026-04-06'),
            $this->buildEntry($user, 4, '2026-04-07'),
        ]);
        $resilienceMoodRepository->method('countDistinctMoodDaysWithinRange')->willReturn(8);

        $resilienceJournalRepository = $this->createMock(JournalEntryRepository::class);
        $resilienceJournalRepository->method('countDistinctJournalDaysWithinRange')->willReturn(5);

        $resilienceService = new ResilienceScoreService($resilienceMoodRepository, $resilienceJournalRepository);

        $moodRepository = $this->createMock(MoodEntryRepository::class);
        $moodRepository->method('countDistinctMoodDaysWithinRange')->willReturn(3);

        $journalRepository = $this->createMock(JournalEntryRepository::class);
        $journalRepository->method('countEntriesWithinRange')->willReturn(1);

        $service = new RecoveryPlanService(
            $criticalService,
            $resilienceService,
            $moodRepository,
            $journalRepository,
        );

        $plan = $service->generate($user);

        self::assertSame('warning', $plan['currentStatus']);
        self::assertSame('Weekly recovery plan (stabilization)', $plan['title']);
        self::assertCount(4, $plan['objectives']);
        self::assertSame(7, $plan['objectives'][0]['progress']['target']);
        self::assertSame(3, $plan['objectives'][1]['progress']['target']);
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
