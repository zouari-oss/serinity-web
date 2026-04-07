<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Api\AbstractApiController;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\User\UserSleepService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/user/sleep', name: 'api_user_sleep_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserSleepController extends AbstractApiController
{
    public function __construct(
        private readonly UserSleepService $userSleepService,
    ) {
    }

    #[Route('/sessions', name: 'sessions', methods: ['GET'])]
    public function sessions(Request $request): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->userSleepService->listSessions($guard, [
                'q' => $this->queryString($request, 'q'),
                'quality' => $this->queryString($request, 'quality'),
                'mood' => $this->queryString($request, 'mood'),
                'insufficient' => (string) $request->query->get('insufficient', ''),
                'sort' => (string) $request->query->get('sort', 'date'),
                'direction' => (string) $request->query->get('direction', 'DESC'),
            ]),
        ]);
    }

    #[Route('/sessions', name: 'session_create', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->createSession($guard, $this->payload($request));

        return $this->json($result->toArray(), $result->success ? 201 : 422);
    }

    #[Route('/sessions/{id<\d+>}', name: 'session_update', methods: ['PUT'])]
    public function updateSession(Request $request, int $id): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->updateSession($guard, $id, $this->payload($request));

        return $this->json($result->toArray(), $result->success ? 200 : 422);
    }

    #[Route('/sessions/{id<\d+>}', name: 'session_delete', methods: ['DELETE'])]
    public function deleteSession(int $id): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->deleteSession($guard, $id);

        return $this->json($result->toArray(), $result->success ? 200 : 404);
    }

    #[Route('/dreams', name: 'dreams', methods: ['GET'])]
    public function dreams(Request $request): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->userSleepService->listDreams($guard, [
                'q' => $this->queryString($request, 'q'),
                'type' => $this->queryString($request, 'type'),
                'mood' => $this->queryString($request, 'mood'),
                'recurring' => (string) $request->query->get('recurring', ''),
                'nightmares' => (string) $request->query->get('nightmares', ''),
                'sort' => (string) $request->query->get('sort', 'date'),
                'direction' => (string) $request->query->get('direction', 'DESC'),
            ]),
        ]);
    }

    #[Route('/dreams', name: 'dream_create', methods: ['POST'])]
    public function createDream(Request $request): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->createDream($guard, $this->payload($request));

        return $this->json($result->toArray(), $result->success ? 201 : 422);
    }

    #[Route('/dreams/{id<\d+>}', name: 'dream_update', methods: ['PUT'])]
    public function updateDream(Request $request, int $id): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->updateDream($guard, $id, $this->payload($request));

        return $this->json($result->toArray(), $result->success ? 200 : 422);
    }

    #[Route('/dreams/{id<\d+>}', name: 'dream_delete', methods: ['DELETE'])]
    public function deleteDream(int $id): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $result = $this->userSleepService->deleteDream($guard, $id);

        return $this->json($result->toArray(), $result->success ? 200 : 404);
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->userSleepService->summary($guard),
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

    /**
     * @return array<string,mixed>
     */
    private function payload(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            try {
                $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }

            return is_array($data) ? $data : [];
        }

        return $request->request->all();
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if (!is_scalar($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
