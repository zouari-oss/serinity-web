<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

final readonly class ExerciseProfileService
{
    public function __construct(
        private ExerciseProfileDatasetBuilder $datasetBuilder,
        private ExerciseProfileApiClient $apiClient,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     displayLabel: string,
     *     message: string,
     *     features: array<string, float|int>,
     *     totals: array{
     *         totalSessions: int,
     *         completedSessions: int,
     *         favoriteCount: int,
     *         feedbackCount: int
     *     },
     *     source: string
     * }
     */
    public function predictForUser(User $user): array
    {
        $dataset = $this->datasetBuilder->build($user);

        if (($dataset['enoughData'] ?? false) !== true) {
            return [
                'status' => 'not_enough_data',
                'label' => 'not_enough_data',
                'displayLabel' => 'Not enough data',
                'message' => 'Complete a few exercise sessions to unlock your ML exercise profile.',
                'features' => $dataset['features'],
                'totals' => $dataset['totals'],
                'source' => 'local_guard',
            ];
        }

        $prediction = $this->apiClient->predict($dataset['features']);
        if ($prediction === null) {
            return [
                'status' => 'service_unavailable',
                'label' => 'not_enough_data',
                'displayLabel' => 'Unavailable',
                'message' => 'Your exercise profile is temporarily unavailable. The rest of the exercise module still works normally.',
                'features' => $dataset['features'],
                'totals' => $dataset['totals'],
                'source' => 'fallback',
            ];
        }

        $label = $prediction['label'];

        return [
            'status' => 'ready',
            'label' => $label,
            'displayLabel' => ucfirst($label),
            'message' => 'Your current exercise profile is based on your recent exercise behaviour.',
            'features' => $dataset['features'],
            'totals' => $dataset['totals'],
            'source' => 'ml_api',
        ];
    }
}
