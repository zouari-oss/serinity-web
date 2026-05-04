<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\AI\MoodPredictionClient;
use App\Service\User\MoodPredictionPayloadBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/mood/prediction', name: 'api_user_mood_prediction_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserMoodPredictionController extends AbstractController
{
    public function __construct(
        private readonly MoodPredictionPayloadBuilder $payloadBuilder,
        private readonly MoodPredictionClient $moodPredictionClient,
    ) {
    }

    #[Route('/next-week', name: 'next_week', methods: ['GET'])]
    public function nextWeek(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $payload = $this->payloadBuilder->buildForLastDays($guard, 7);
        $prediction = $this->moodPredictionClient->predictNextWeek($payload);

        if ($prediction === null) {
            return $this->json([
                'success' => false,
                'message' => 'Next week prediction is temporarily unavailable.',
                'data' => null,
            ], 503);
        }

        return $this->json([
            'success' => true,
            'message' => 'Next week prediction generated successfully.',
            'data' => [
                'request' => [
                    'weekStart' => $payload['weekStart'],
                    'weekEnd' => $payload['weekEnd'],
                    'inputEntries' => count($payload['entries']),
                ],
                'prediction' => $prediction,
            ],
        ]);
    }

    private function guard(): User|JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (!in_array($user->getRole(), ['PATIENT', 'THERAPIST'], true)) {
            return $this->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($user->getAccountStatus() === AccountStatus::DISABLED->value) {
            return $this->json([
                'success' => false,
                'error' => 'account_disabled',
                'message' => 'Your account is disabled.',
            ], 403);
        }

        return $user;
    }
}
