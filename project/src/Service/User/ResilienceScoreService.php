<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;

final readonly class ResilienceScoreService
{
    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private JournalEntryRepository $journalEntryRepository,
    ) {
    }

    /**
     * @return array{
     *     score:int,
     *     label:string,
     *     breakdown:array{
     *         mood:int,
     *         tracking:int,
     *         journaling:int,
     *         moodAverage:float|null,
     *         trackedMoodDays:int,
     *         trackedJournalDays:int,
     *         windowDays:int
     *     },
     *     timeframe:array{days:int,from:string,to:string},
     *     interpretation:string
     * }
     */
    public function compute(User $user, int $days = 14): array
    {
        $toDate = new \DateTimeImmutable('today 23:59:59');
        $fromDate = $toDate->setTime(0, 0)->modify(sprintf('-%d days', max(0, $days - 1)));

        $moodEntries = $this->moodEntryRepository->findWithinDateRange($user, $fromDate, $toDate);
        $moodAverage = null;
        $moodScore = 0;

        if ($moodEntries !== []) {
            $total = 0;
            foreach ($moodEntries as $entry) {
                $total += $entry->getMoodLevel();
            }

            $moodAverage = $total / count($moodEntries);
            $moodScore = (int) round(max(0.0, min(1.0, ($moodAverage - 1) / 4)) * 60);
        }

        $trackedMoodDays = $this->moodEntryRepository->countDistinctMoodDaysWithinRange($user, $fromDate, $toDate);
        $trackingScore = (int) round(max(0.0, min(1.0, $trackedMoodDays / $days)) * 25);

        $trackedJournalDays = $this->journalEntryRepository->countDistinctJournalDaysWithinRange($user, $fromDate, $toDate);
        $journalingScore = (int) round(max(0.0, min(1.0, $trackedJournalDays / $days)) * 15);

        $score = min(100, $moodScore + $trackingScore + $journalingScore);
        $label = $score >= 70 ? 'Stable' : ($score >= 45 ? 'À surveiller' : 'Critique');

        return [
            'score' => $score,
            'label' => $label,
            'breakdown' => [
                'mood' => $moodScore,
                'tracking' => $trackingScore,
                'journaling' => $journalingScore,
                'moodAverage' => $moodAverage === null ? null : round($moodAverage, 1),
                'trackedMoodDays' => $trackedMoodDays,
                'trackedJournalDays' => $trackedJournalDays,
                'windowDays' => $days,
            ],
            'timeframe' => [
                'days' => $days,
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'interpretation' => $this->interpret($label, $moodAverage, $trackedMoodDays, $trackedJournalDays, $days),
        ];
    }

    private function interpret(
        string $label,
        ?float $moodAverage,
        int $trackedMoodDays,
        int $trackedJournalDays,
        int $windowDays,
    ): string {
        if ($label === 'Stable') {
            return 'Your recent mood and tracking habits suggest a stable resilience profile.';
        }

        if ($label === 'Critique') {
            return 'Your recent indicators are fragile. Prioritize daily mood tracking and more frequent journal entries.';
        }

        $average = $moodAverage === null ? 'n/a' : number_format($moodAverage, 1);

        return sprintf(
            'Mixed signals detected (avg mood: %s/5, mood days: %d/%d, journal days: %d/%d).',
            $average,
            $trackedMoodDays,
            $windowDays,
            $trackedJournalDays,
            $windowDays,
        );
    }
}
