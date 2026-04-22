<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\MoodEmotion;
use App\Entity\MoodEntry;
use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;

final readonly class CriticalPeriodDetectionService
{
    /** @var list<string> */
    private const NEGATIVE_EMOTIONS = [
        'sad', 'sadness', 'angry', 'anger', 'anxious', 'anxiety', 'stress', 'stressed', 'fear', 'afraid',
        'depressed', 'hopeless', 'lonely', 'guilty', 'frustrated', 'overwhelmed', 'triste', 'tristesse',
        'colere', 'anxiete', 'peur', 'deprime', 'solitude', 'culpabilite', 'frustration',
    ];

    private const LOW_MOOD_THRESHOLD = 2;
    private const WARNING_THRESHOLD = 30;
    private const CRITICAL_THRESHOLD = 70;

    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private JournalEntryRepository $journalEntryRepository,
    ) {
    }

    /**
     * @return array{
     *     status:string,
     *     score:int,
     *     reasons:list<string>,
     *     timeframe:array{days:int,from:string,to:string},
     *     summary:string,
     *     repeatedNegativeEmotion:?array{name:string,count:int}
     * }
     */
    public function detect(User $user, int $days = 7): array
    {
        $toDate = new \DateTimeImmutable('today 23:59:59');
        $fromDate = $toDate->setTime(0, 0)->modify(sprintf('-%d days', max(0, $days - 1)));
        $entries = $this->moodEntryRepository->findWithinDateRange($user, $fromDate, $toDate);

        if ($entries === []) {
            return [
                'status' => 'stable',
                'score' => 0,
                'reasons' => ['Not enough mood entries in the selected period.'],
                'timeframe' => [
                    'days' => $days,
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
                ],
                'summary' => 'No significant risk detected yet. Track moods to improve reliability.',
                'repeatedNegativeEmotion' => null,
            ];
        }

        usort($entries, static function (MoodEntry $left, MoodEntry $right): int {
            $dateCompare = $left->getEntryDate() <=> $right->getEntryDate();
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return $left->getUpdatedAt() <=> $right->getUpdatedAt();
        });

        $score = 0;
        $reasons = [];

        $averageMood = $this->averageMood($entries);
        if ($averageMood < 2.2) {
            $score += 45;
            $reasons[] = sprintf('Average mood is very low over %d days (%.1f/5).', $days, $averageMood);
        } elseif ($averageMood < 2.8) {
            $score += 30;
            $reasons[] = sprintf('Average mood is below threshold over %d days (%.1f/5).', $days, $averageMood);
        }

        $maxLowStreak = $this->maxConsecutiveLowMoods($entries);
        if ($maxLowStreak >= 4) {
            $score += 40;
            $reasons[] = sprintf('%d consecutive low mood entries were detected.', $maxLowStreak);
        } elseif ($maxLowStreak >= 3) {
            $score += 25;
            $reasons[] = '3 consecutive low mood entries were detected.';
        }

        $repeatedNegativeEmotion = $this->findRepeatedNegativeEmotion($entries);
        if ($repeatedNegativeEmotion !== null && $repeatedNegativeEmotion['count'] >= 5) {
            $score += 35;
            $reasons[] = sprintf(
                'Repeated negative emotion detected: %s (%d times).',
                $repeatedNegativeEmotion['name'],
                $repeatedNegativeEmotion['count'],
            );
        } elseif ($repeatedNegativeEmotion !== null && $repeatedNegativeEmotion['count'] >= 3) {
            $score += 20;
            $reasons[] = sprintf(
                'Negative emotion repeated in recent entries: %s (%d times).',
                $repeatedNegativeEmotion['name'],
                $repeatedNegativeEmotion['count'],
            );
        }

        $journalEntries = $this->journalEntryRepository->countEntriesWithinRange($user, $fromDate, $toDate);
        $journalDays = $this->journalEntryRepository->countDistinctJournalDaysWithinRange($user, $fromDate, $toDate);
        if ($this->isMoodDeclining($entries) && $journalDays <= 1) {
            if ($journalEntries === 0) {
                $score += 30;
                $reasons[] = 'Mood is declining and no journal activity was found recently.';
            } else {
                $score += 20;
                $reasons[] = 'Mood is declining with very limited journal activity.';
            }
        }

        $status = 'stable';
        if ($score >= self::CRITICAL_THRESHOLD) {
            $status = 'critical';
        } elseif ($score >= self::WARNING_THRESHOLD) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'score' => min(100, $score),
            'reasons' => $reasons === [] ? ['No strong risk signal detected in recent data.'] : $reasons,
            'timeframe' => [
                'days' => $days,
                'from' => $fromDate->format('Y-m-d'),
                'to' => $toDate->format('Y-m-d'),
            ],
            'summary' => $this->buildSummary($status, $reasons),
            'repeatedNegativeEmotion' => $repeatedNegativeEmotion,
        ];
    }

    public function isCritical(User $user, int $days = 7): bool
    {
        $result = $this->detect($user, $days);

        return ($result['status'] ?? 'stable') === 'critical';
    }

    /**
     * @param list<MoodEntry> $entries
     */
    private function averageMood(array $entries): float
    {
        $total = 0;
        foreach ($entries as $entry) {
            $total += $entry->getMoodLevel();
        }

        return $total / count($entries);
    }

    /**
     * @param list<MoodEntry> $entries
     */
    private function maxConsecutiveLowMoods(array $entries): int
    {
        $max = 0;
        $current = 0;

        foreach ($entries as $entry) {
            if ($entry->getMoodLevel() <= self::LOW_MOOD_THRESHOLD) {
                $current++;
                $max = max($max, $current);
                continue;
            }

            $current = 0;
        }

        return $max;
    }

    /**
     * @param list<MoodEntry> $entries
     * @return array{name:string,count:int}|null
     */
    private function findRepeatedNegativeEmotion(array $entries): ?array
    {
        $counts = [];
        $labels = [];

        foreach ($entries as $entry) {
            foreach ($entry->getEmotions() as $emotion) {
                if (!$emotion instanceof MoodEmotion) {
                    continue;
                }

                $key = $this->normalize($emotion->getName());
                if (!in_array($key, self::NEGATIVE_EMOTIONS, true)) {
                    continue;
                }

                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $labels[$key] = $emotion->getName();
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);
        $topKey = (string) array_key_first($counts);

        return [
            'name' => $labels[$topKey] ?? $topKey,
            'count' => $counts[$topKey],
        ];
    }

    /**
     * @param list<MoodEntry> $entries
     */
    private function isMoodDeclining(array $entries): bool
    {
        if (count($entries) < 4) {
            return false;
        }

        $midpoint = (int) floor(count($entries) / 2);
        $firstChunk = array_slice($entries, 0, $midpoint);
        $secondChunk = array_slice($entries, $midpoint);

        if ($firstChunk === [] || $secondChunk === []) {
            return false;
        }

        $firstAverage = $this->averageMood($firstChunk);
        $secondAverage = $this->averageMood($secondChunk);

        return $secondAverage <= ($firstAverage - 0.5);
    }

    /**
     * @param list<string> $reasons
     */
    private function buildSummary(string $status, array $reasons): string
    {
        if ($status === 'critical') {
            return 'Critical emotional pattern detected. Prioritize daily tracking and regular journaling this week.';
        }

        if ($status === 'warning') {
            return 'Warning signs detected. Increase consistency in mood and journal tracking this week.';
        }

        if ($reasons === [] || str_contains($reasons[0], 'No strong risk signal')) {
            return 'Recent indicators look stable.';
        }

        return 'Recent indicators look stable, keep tracking to maintain this trend.';
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = mb_strtolower($ascii);
        }

        $normalized = preg_replace('/[^a-z]+/', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
