<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\JournalEntryRepository;
use App\Repository\MoodEntryRepository;

final readonly class RecoveryPlanService
{
    public function __construct(
        private CriticalPeriodDetectionService $criticalPeriodDetectionService,
        private ResilienceScoreService $resilienceScoreService,
        private MoodEntryRepository $moodEntryRepository,
        private JournalEntryRepository $journalEntryRepository,
    ) {
    }

    /**
     * @return array{
     *     title:string,
     *     currentStatus:string,
     *     resilienceLabel:string,
     *     resilienceScore:int,
     *     objectives:list<array{
     *         title:string,
     *         icon:string,
     *         detail:?string,
     *         progress:array{current:int,target:int,unit:string},
     *         completed:bool,
     *         progressLabel:string
     *     }>,
     *     progress:array{completedObjectives:int,totalObjectives:int,percent:int},
     *     explanation:string,
     *     weekRange:array{from:string,to:string}
     * }
     */
    public function generate(User $user, bool $weeklyTrendReviewed = false): array
    {
        $critical = $this->criticalPeriodDetectionService->detect($user, 7);
        $resilience = $this->resilienceScoreService->compute($user, 14);
        $status = $critical['status'];

        $weekStart = (new \DateTimeImmutable('monday this week'))->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $moodDays = $this->moodEntryRepository->countDistinctMoodDaysWithinRange($user, $weekStart, $weekEnd);
        $journalEntries = $this->journalEntryRepository->countEntriesWithinRange($user, $weekStart, $weekEnd);

        $targets = match ($status) {
            'critical' => ['moodDays' => 7, 'journals' => 4, 'trigger' => 1, 'reviews' => 1],
            'warning' => ['moodDays' => 7, 'journals' => 3, 'trigger' => 1, 'reviews' => 1],
            default => ['moodDays' => 5, 'journals' => 2, 'trigger' => 1, 'reviews' => 1],
        };

        $topInfluence = $this->moodEntryRepository->findTopInfluenceWithinRange($user, $weekStart, $weekEnd);
        $triggerDetail = $this->buildTriggerDetail(
            $critical['repeatedNegativeEmotion'] ?? null,
            $topInfluence['label'] ?? null,
        );
        $triggerProgress = isset($critical['repeatedNegativeEmotion']) && $critical['repeatedNegativeEmotion'] !== null ? 1 : 0;
        $reviewProgress = $weeklyTrendReviewed ? 1 : 0;
        $objectives = [
            $this->buildObjective('Track your mood daily this week', 'calendar_today', null, $moodDays, $targets['moodDays'], 'days'),
            $this->buildObjective('Write focused journal entries this week', 'edit_note', null, $journalEntries, $targets['journals'], 'entries'),
            $this->buildObjective('Identify your main trigger/emotion pattern', 'psychology', $triggerDetail, $triggerProgress, $targets['trigger'], 'completed'),
            $this->buildObjective('Review your 7-day mood trend', 'insights', null, $reviewProgress, $targets['reviews'], 'completed'),
        ];

        $completedObjectives = count(array_filter(
            $objectives,
            static fn(array $objective): bool => $objective['completed'],
        ));

        return [
            'title' => $this->resolveTitle($status),
            'currentStatus' => $status,
            'resilienceLabel' => $resilience['label'],
            'resilienceScore' => $resilience['score'],
            'objectives' => $objectives,
            'progress' => [
                'completedObjectives' => $completedObjectives,
                'totalObjectives' => count($objectives),
                'percent' => (int) round(($completedObjectives / max(1, count($objectives))) * 100),
            ],
            'explanation' => $this->buildExplanation($critical, $resilience),
            'weekRange' => [
                'from' => $weekStart->format('Y-m-d'),
                'to' => $weekEnd->format('Y-m-d'),
            ],
        ];
    }

    /**
     * @return array{
     *     title:string,
     *     icon:string,
     *     detail:?string,
     *     progress:array{current:int,target:int,unit:string},
     *     completed:bool,
     *     progressLabel:string
     * }
     */
    private function buildObjective(
        string $title,
        string $icon,
        ?string $detail,
        int $current,
        int $target,
        string $unit,
    ): array
    {
        $completed = $current >= $target;

        return [
            'title' => $title,
            'icon' => $icon,
            'detail' => $detail,
            'progress' => [
                'current' => $current,
                'target' => $target,
                'unit' => $unit,
            ],
            'completed' => $completed,
            'progressLabel' => sprintf('%d/%d completed', $current, $target),
        ];
    }

    /**
     * @param array{name:string,count:int}|null $repeatedNegativeEmotion
     */
    private function buildTriggerDetail(?array $repeatedNegativeEmotion, ?string $influenceLabel): ?string
    {
        $parts = [];

        if ($repeatedNegativeEmotion !== null) {
            $parts[] = sprintf('Emotion: %s', $repeatedNegativeEmotion['name']);
        }

        if ($influenceLabel !== null && $influenceLabel !== '' && $influenceLabel !== 'No data') {
            $parts[] = sprintf('Influence: %s', $influenceLabel);
        }

        if ($parts === []) {
            return null;
        }

        return implode(' • ', $parts);
    }

    private function resolveTitle(string $status): string
    {
        return match ($status) {
            'critical' => 'Weekly recovery plan (critical support)',
            'warning' => 'Weekly recovery plan (stabilization)',
            default => 'Weekly maintenance plan',
        };
    }

    /**
     * @param array{
     *     status:string,
     *     reasons:list<string>
     * } $critical
     * @param array{
     *     score:int,
     *     label:string
     * } $resilience
     */
    private function buildExplanation(array $critical, array $resilience): string
    {
        $reason = $critical['reasons'][0] ?? 'Recent mood trend requires regular monitoring.';

        return sprintf(
            'Current status: %s. Resilience score: %d/100 (%s). Main signal: %s',
            ucfirst($critical['status']),
            $resilience['score'],
            $resilience['label'],
            $reason,
        );
    }
}
