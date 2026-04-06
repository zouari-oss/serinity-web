<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Api\AbstractApiController;
use App\Dto\Mood\MoodCreateRequest;
use App\Dto\Mood\MoodHistoryFilterRequest;
use App\Dto\Mood\MoodSummaryRequest;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\User\UserMoodService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user/mood', name: 'api_user_mood_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserMoodController extends AbstractApiController
{
    public function __construct(
        private readonly UserMoodService $userMoodService,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new MoodCreateRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        $dto->momentType = $this->normalizeMomentType($dto->momentType) ?? '';

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userMoodService->create($guard, $dto);

        return $this->json($result->toArray(), $result->success ? 201 : 400);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new MoodHistoryFilterRequest();
        $dto->search = $request->query->get('search');
        $dto->momentType = $this->normalizeMomentType($request->query->get('momentType'));
        $dto->fromDate = $request->query->get('fromDate');
        $dto->toDate = $request->query->get('toDate');
        $dto->page = max(1, (int) $request->query->get('page', 1));
        $dto->limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userMoodService->history($guard, $dto);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new MoodSummaryRequest();
        $dto->days = max(1, (int) $request->query->get('days', 7));
        $dto->fromDate = $request->query->get('fromDate');
        $dto->toDate = $request->query->get('toDate');

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userMoodService->summary($guard, $dto);

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

    private function normalizeMomentType(?string $momentType): ?string
    {
        if ($momentType === null) {
            return null;
        }

        $trimmed = trim($momentType);

        return $trimmed === '' ? null : strtoupper($trimmed);
    }
}
