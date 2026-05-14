<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Api\AbstractApiController;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\User\ExerciseProfileService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/exercice/profile', name: 'api_user_exercice_profile_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserExerciseProfileController extends AbstractApiController
{
    public function __construct(
        private readonly ExerciseProfileService $exerciseProfileService,
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->exerciseProfileService->predictForUser($guard),
        ]);
    }

    private function guard(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }
        if (!in_array($user->getRole(), ['PATIENT', 'THERAPIST'], true)) {
            return $this->json(['success' => false, 'message' => 'Forbidden.'], 403);
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
