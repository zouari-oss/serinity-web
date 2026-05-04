<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\MoodEmotion;
use App\Entity\MoodEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;
use App\Service\User\CriticalPeriodDetectionService;
use PHPUnit\Framework\TestCase;

final class CriticalPeriodDetectionServiceTest extends TestCase
{
    public function testDetectReturnsCriticalWhenMultipleRiskSignalsArePresent(): void
    {
        $user = $this->buildUser();
        $entries = [
            $this->buildEntry($user, 2, '2026-04-11', ['Sad']),
            $this->buildEntry($user, 2, '2026-04-12', ['Sad']),
            $this->buildEntry($user, 1, '2026-04-13', ['Sad']),
            $this->buildEntry($user, 1, '2026-04-14', ['Angry']),
        ];

        $moodRepository = $this->createMock(MoodEntryRepository::class);
        $moodRepository->method('findWithinDateRange')->willReturn($entries);

        $journalRepository = $this->createMock(JournalEntryRepository::class);
        $journalRepository->method('countEntriesWithinRange')->willReturn(0);
        $journalRepository->method('countDistinctJournalDaysWithinRange')->willReturn(0);

        $service = new CriticalPeriodDetectionService($moodRepository, $journalRepository);
        $result = $service->detect($user, 7);

        self::assertSame('critical', $result['status']);
        self::assertGreaterThanOrEqual(70, $result['score']);
        self::assertNotEmpty($result['reasons']);
    }

    public function testDetectReturnsStableWithHealthyTrend(): void
    {
        $user = $this->buildUser();
        $entries = [
            $this->buildEntry($user, 4, '2026-04-11', ['Calm']),
            $this->buildEntry($user, 5, '2026-04-12', ['Happy']),
            $this->buildEntry($user, 4, '2026-04-13', ['Focused']),
        ];

        $moodRepository = $this->createMock(MoodEntryRepository::class);
        $moodRepository->method('findWithinDateRange')->willReturn($entries);

        $journalRepository = $this->createMock(JournalEntryRepository::class);
        $journalRepository->method('countEntriesWithinRange')->willReturn(2);
        $journalRepository->method('countDistinctJournalDaysWithinRange')->willReturn(2);

        $service = new CriticalPeriodDetectionService($moodRepository, $journalRepository);
        $result = $service->detect($user, 7);

        self::assertSame('stable', $result['status']);
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

    /**
     * @param list<string> $emotionNames
     */
    private function buildEntry(User $user, int $moodLevel, string $date, array $emotionNames): MoodEntry
    {
        $entry = (new MoodEntry())
            ->setUser($user)
            ->setEntryDate(new \DateTimeImmutable($date))
            ->setMomentType('MOMENT')
            ->setMoodLevel($moodLevel)
            ->setUpdatedAt(new \DateTimeImmutable($date . ' 10:00:00'));

        foreach ($emotionNames as $emotionName) {
            $entry->addEmotion((new MoodEmotion())->setName($emotionName));
        }

        return $entry;
    }
}
