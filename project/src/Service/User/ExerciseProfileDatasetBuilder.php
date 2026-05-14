<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\ExerciceControl;
use App\Entity\ExerciceFavorite;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceFavoriteRepository;

final readonly class ExerciseProfileDatasetBuilder
{
    /**
     * Only the model's numerical features are exposed here.
     * We intentionally exclude user_id to avoid leaking identity into the model
     * and to keep predictions based strictly on behaviour.
     */
    public const FEATURE_COLUMNS = [
        'total_sessions',
        'completed_sessions',
        'completion_rate',
        'favorite_count',
        'favorite_rate',
        'avg_duration_minutes',
        'avg_active_seconds',
        'avg_engagement_ratio',
        'calm_type_ratio',
        'balanced_type_ratio',
        'active_type_ratio',
        'feedback_positive_score',
        'feedback_present_rate',
    ];

    public function __construct(
        private ExerciceControlRepository $controlRepository,
        private ExerciceFavoriteRepository $favoriteRepository,
    ) {
    }

    /**
     * @return array{
     *     enoughData: bool,
     *     reason: string|null,
     *     features: array<string, float|int>,
     *     totals: array{
     *         totalSessions: int,
     *         completedSessions: int,
     *         favoriteCount: int,
     *         feedbackCount: int
     *     }
     * }
     */
    public function build(User $user): array
    {
        $controls = $this->controlRepository->findAssignedForUser($user);
        $favorites = $this->favoriteRepository->findForUser($user);

        $totalSessions = count($controls);
        $completedSessions = count(array_filter(
            $controls,
            static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_COMPLETED
        ));

        $favoriteCount = count($favorites);
        $feedbackCount = count(array_filter(
            $controls,
            static fn(ExerciceControl $control): bool => trim((string) $control->getFeedback()) !== ''
        ));

        $features = [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $this->safeRatio($completedSessions, $totalSessions),
            'favorite_count' => $favoriteCount,
            'favorite_rate' => $this->safeRatio($favoriteCount, $totalSessions),
            'avg_duration_minutes' => $this->averageDurationMinutes($controls),
            'avg_active_seconds' => $this->averageActiveSeconds($controls),
            'avg_engagement_ratio' => $this->averageEngagementRatio($controls),
            'calm_type_ratio' => 0.0,
            'balanced_type_ratio' => 0.0,
            'active_type_ratio' => 0.0,
            'feedback_positive_score' => $this->feedbackPositiveScore($controls),
            'feedback_present_rate' => $this->safeRatio($feedbackCount, $totalSessions),
        ];

        $typeDistribution = $this->typeDistribution($controls, $totalSessions);
        $features['calm_type_ratio'] = $typeDistribution['calm'];
        $features['balanced_type_ratio'] = $typeDistribution['balanced'];
        $features['active_type_ratio'] = $typeDistribution['active'];

        return [
            'enoughData' => $totalSessions >= 3 && $completedSessions >= 1,
            'reason' => $totalSessions >= 3 && $completedSessions >= 1
                ? null
                : 'not_enough_data',
            'features' => $features,
            'totals' => [
                'totalSessions' => $totalSessions,
                'completedSessions' => $completedSessions,
                'favoriteCount' => $favoriteCount,
                'feedbackCount' => $feedbackCount,
            ],
        ];
    }

    /**
     * @param list<ExerciceControl> $controls
     */
    private function averageDurationMinutes(array $controls): float
    {
        if ($controls === []) {
            return 0.0;
        }

        $totalDuration = array_sum(array_map(
            static fn(ExerciceControl $control): int => max(0, $control->getExercice()->getDurationMinutes()),
            $controls
        ));

        return round($totalDuration / count($controls), 4);
    }

    /**
     * @param list<ExerciceControl> $controls
     */
    private function averageActiveSeconds(array $controls): float
    {
        if ($controls === []) {
            return 0.0;
        }

        $totalActiveSeconds = array_sum(array_map(
            static fn(ExerciceControl $control): int => max(0, $control->getActiveSeconds()),
            $controls
        ));

        return round($totalActiveSeconds / count($controls), 4);
    }

    /**
     * Engagement is computed per session as:
     * active seconds / expected duration seconds.
     * That keeps the feature behavioural and independent from any identifier.
     *
     * @param list<ExerciceControl> $controls
     */
    private function averageEngagementRatio(array $controls): float
    {
        if ($controls === []) {
            return 0.0;
        }

        $ratios = [];
        foreach ($controls as $control) {
            $expectedSeconds = max(1, $control->getExercice()->getDurationMinutes() * 60);
            $ratios[] = min(1.5, max(0.0, $control->getActiveSeconds() / $expectedSeconds));
        }

        return round(array_sum($ratios) / count($ratios), 4);
    }

    /**
     * @param list<ExerciceControl> $controls
     *
     * @return array{calm: float, balanced: float, active: float}
     */
    private function typeDistribution(array $controls, int $totalSessions): array
    {
        if ($controls === [] || $totalSessions <= 0) {
            return ['calm' => 0.0, 'balanced' => 0.0, 'active' => 0.0];
        }

        $counts = ['calm' => 0, 'balanced' => 0, 'active' => 0];
        foreach ($controls as $control) {
            $bucket = $this->normalizeExerciseType((string) $control->getExercice()->getType());
            $counts[$bucket]++;
        }

        return [
            'calm' => $this->safeRatio($counts['calm'], $totalSessions),
            'balanced' => $this->safeRatio($counts['balanced'], $totalSessions),
            'active' => $this->safeRatio($counts['active'], $totalSessions),
        ];
    }

    /**
     * The existing entity stores a free-form exercise type string.
     * We keep that structure unchanged and map it into the model's
     * three buckets using simple keyword rules.
     */
    private function normalizeExerciseType(string $type): string
    {
        $normalized = mb_strtolower(trim($type));

        foreach (['calm', 'breath', 'breathing', 'relax', 'yoga', 'stretch', 'mobility', 'meditation'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'calm';
            }
        }

        foreach (['active', 'cardio', 'run', 'hiit', 'dance', 'strength', 'workout', 'intense'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'active';
            }
        }

        return 'balanced';
    }

    /**
     * Feedback is free text in this codebase, so we derive a simple
     * positive score using light keyword matching rather than changing entities.
     *
     * @param list<ExerciceControl> $controls
     */
    private function feedbackPositiveScore(array $controls): float
    {
        $scores = [];

        foreach ($controls as $control) {
            $feedback = mb_strtolower(trim((string) $control->getFeedback()));
            if ($feedback === '') {
                continue;
            }

            $score = 0.5;
            foreach (['good', 'great', 'better', 'helpful', 'calm', 'focused', 'easy', 'nice', 'relaxed', 'excellent'] as $keyword) {
                if (str_contains($feedback, $keyword)) {
                    $score += 0.1;
                }
            }
            foreach (['bad', 'hard', 'pain', 'worse', 'tired', 'stress', 'boring', 'difficult', 'awful'] as $keyword) {
                if (str_contains($feedback, $keyword)) {
                    $score -= 0.1;
                }
            }

            $scores[] = min(1.0, max(0.0, $score));
        }

        if ($scores === []) {
            return 0.0;
        }

        return round(array_sum($scores) / count($scores), 4);
    }

    private function safeRatio(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }
}
