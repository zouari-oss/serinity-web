<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Api\AbstractApiController;
use App\Dto\Exercice\CompleteControlRequest;
use App\Dto\Exercice\FavoriteToggleRequest;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\User\UserExerciceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user/exercice', name: 'api_user_exercice_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserExerciceController extends AbstractApiController
{
    public function __construct(
        private readonly UserExerciceService $userExerciceService,
    ) {
    }

    #[Route('/assigned', name: 'assigned', methods: ['GET'])]
    public function assigned(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userExerciceService->assigned($guard);

        return $this->json($result->toArray(), 200);
    }

    #[Route('/session/{id}/start', name: 'start', methods: ['POST'])]
    public function start(int $id): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userExerciceService->start($guard, $id);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/session/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new CompleteControlRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userExerciceService->complete($guard, $id, $dto->feedback, $dto->activeSeconds);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userExerciceService->history($guard);

        return $this->json($result->toArray(), 200);
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userExerciceService->summary($guard);

        return $this->json($result->toArray(), 200);
    }

    #[Route('/favorite', name: 'favorite', methods: ['POST'])]
    public function favorite(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new FavoriteToggleRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userExerciceService->toggleFavorite($guard, $dto->favoriteType, $dto->itemId);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
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
