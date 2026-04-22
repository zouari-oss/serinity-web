<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\ExerciceControl;
use App\Entity\ExerciceFavorite;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceFavoriteRepository;
use App\Repository\ExerciceRepository;

final readonly class PerformanceReportBuilder
{
    public function __construct(
        private ExerciceControlRepository $controlRepository,
        private ExerciceFavoriteRepository $favoriteRepository,
        private ExerciceRepository $exerciceRepository,
    ) {
    }

    /**
     * @return array{
     *     engagementScore:int,
     *     streakDays:int,
     *     completionRate:int,
     *     activeMinutesLast7Days:int,
     *     completedSessions:int,
     *     cancelledSessions:int,
     *     favoriteTypes:list<string>,
     *     recentPattern:string,
     *     bestMoment:string,
     *     consistencyLevel:string,
     *     averageSessionDuration:int,
     *     mostCompletedExercise:string
     * }
     */
    public function build(User $user): array
    {
        $controls = $this->controlRepository->findAssignedForUser($user);
        $totalSessions = count($controls);
        $completedControls = array_values(array_filter(
            $controls,
            static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_COMPLETED,
        ));
        $cancelledSessions = count(array_filter(
            $controls,
            static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_CANCELLED,
        ));
        $completedSessions = count($completedControls);
        $completionRate = $totalSessions > 0 ? (int) round(($completedSessions / $totalSessions) * 100) : 0;
        $activeMinutesLast7Days = $this->activeMinutesWithinDays($controls, 7);
        $streakDays = $this->streakDays($completedControls);
        $averageSessionDuration = $this->averageSessionDuration($completedControls);
        $recentPattern = $this->recentPattern($controls);
        $bestMoment = $this->bestMoment($completedControls);
        $consistencyLevel = $this->consistencyLevel($streakDays, $completionRate, $activeMinutesLast7Days);

        return [
            'engagementScore' => $this->engagementScore($completionRate, $streakDays, $activeMinutesLast7Days, $completedSessions),
            'streakDays' => $streakDays,
            'completionRate' => $completionRate,
            'activeMinutesLast7Days' => $activeMinutesLast7Days,
            'completedSessions' => $completedSessions,
            'cancelledSessions' => $cancelledSessions,
            'favoriteTypes' => $this->favoriteTypes($user),
            'recentPattern' => $recentPattern,
            'bestMoment' => $bestMoment,
            'consistencyLevel' => $consistencyLevel,
            'averageSessionDuration' => $averageSessionDuration,
            'mostCompletedExercise' => $this->mostCompletedExercise($completedControls),
        ];
    }

    /** @param list<ExerciceControl> $controls */
    private function activeMinutesWithinDays(array $controls, int $days): int
    {
        $since = (new \DateTimeImmutable(sprintf('-%d days', $days)))->setTime(0, 0);
        $activeSeconds = 0;

        foreach ($controls as $control) {
            $activityAt = $control->getCompletedAt() ?? $control->getStartedAt() ?? $control->getUpdatedAt();
            if ($activityAt < $since) {
                continue;
            }

            $activeSeconds += $control->getActiveSeconds();
        }

        return (int) round($activeSeconds / 60);
    }

    /** @param list<ExerciceControl> $completedControls */
    private function streakDays(array $completedControls): int
    {
        $completedDays = [];
        foreach ($completedControls as $control) {
            $completedAt = $control->getCompletedAt();
            if (!$completedAt instanceof \DateTimeImmutable) {
                continue;
            }

            $completedDays[$completedAt->format('Y-m-d')] = true;
        }

        $cursor = new \DateTimeImmutable('today');
        if (!isset($completedDays[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 day');
        }

        $streak = 0;
        while (isset($completedDays[$cursor->format('Y-m-d')])) {
            ++$streak;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /** @param list<ExerciceControl> $completedControls */
    private function averageSessionDuration(array $completedControls): int
    {
        $durations = array_values(array_filter(
            array_map(static fn(ExerciceControl $control): int => $control->getActiveSeconds(), $completedControls),
            static fn(int $seconds): bool => $seconds > 0,
        ));

        if ($durations === []) {
            return 0;
        }

        return (int) round((array_sum($durations) / count($durations)) / 60);
    }

    /** @param list<ExerciceControl> $controls */
    private function recentPattern(array $controls): string
    {
        $since = (new \DateTimeImmutable('-14 days'))->setTime(0, 0);
        $moments = ['Morning' => 0, 'Afternoon' => 0, 'Evening' => 0];

        foreach ($controls as $control) {
            $activityAt = $control->getCompletedAt() ?? $control->getStartedAt();
            if (!$activityAt instanceof \DateTimeImmutable || $activityAt < $since) {
                continue;
            }

            ++$moments[$this->momentLabel($activityAt)];
        }

        arsort($moments);
        $topMoment = (string) array_key_first($moments);
        $topCount = (int) reset($moments);

        return $topCount > 0 ? 'more active in the ' . strtolower($topMoment) : 'not enough recent activity yet';
    }

    /** @param list<ExerciceControl> $completedControls */
    private function bestMoment(array $completedControls): string
    {
        $moments = ['Morning' => 0, 'Afternoon' => 0, 'Evening' => 0];

        foreach ($completedControls as $control) {
            $completedAt = $control->getCompletedAt();
            if (!$completedAt instanceof \DateTimeImmutable) {
                continue;
            }

            ++$moments[$this->momentLabel($completedAt)];
        }

        arsort($moments);

        return ((int) reset($moments)) > 0 ? (string) array_key_first($moments) : 'Not enough data yet';
    }

    private function consistencyLevel(int $streakDays, int $completionRate, int $activeMinutesLast7Days): string
    {
        if ($streakDays >= 5 || ($completionRate >= 75 && $activeMinutesLast7Days >= 60)) {
            return 'Strong';
        }

        if ($streakDays >= 2 || $completionRate >= 45 || $activeMinutesLast7Days >= 20) {
            return 'Moderate';
        }

        return 'Building';
    }

    private function engagementScore(int $completionRate, int $streakDays, int $activeMinutesLast7Days, int $completedSessions): int
    {
        $score = (int) round(
            ($completionRate * 0.45)
            + (min($streakDays, 7) / 7 * 25)
            + (min($activeMinutesLast7Days, 120) / 120 * 20)
            + (min($completedSessions, 10) / 10 * 10),
        );

        return max(0, min(100, $score));
    }

    /** @return list<string> */
    private function favoriteTypes(User $user): array
    {
        $favorites = $this->favoriteRepository->findForUser($user);
        $exerciseIds = [];
        foreach ($favorites as $favorite) {
            if ($favorite->getFavoriteType() === ExerciceFavorite::TYPE_EXERCICE) {
                $exerciseIds[] = $favorite->getItemId();
            }
        }

        if ($exerciseIds === []) {
            return [];
        }

        $types = [];
        foreach ($this->exerciceRepository->findBy(['id' => array_values(array_unique($exerciseIds))]) as $exercise) {
            $type = trim(strtolower($exercise->getType()));
            if ($type !== '') {
                $types[] = $type;
            }
        }

        sort($types);

        return array_values(array_unique($types));
    }

    /** @param list<ExerciceControl> $completedControls */
    private function mostCompletedExercise(array $completedControls): string
    {
        $titles = [];
        foreach ($completedControls as $control) {
            $title = $control->getExercice()->getTitle();
            $titles[$title] = ($titles[$title] ?? 0) + 1;
        }

        if ($titles === []) {
            return 'Not enough data yet';
        }

        arsort($titles);

        return (string) array_key_first($titles);
    }

    private function momentLabel(\DateTimeImmutable $dateTime): string
    {
        $hour = (int) $dateTime->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Morning',
            $hour >= 12 && $hour < 18 => 'Afternoon',
            default => 'Evening',
        };
    }
}
