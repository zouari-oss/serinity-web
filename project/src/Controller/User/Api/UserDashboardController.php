<?php

declare(strict_types=1);

namespace App\Controller\User\Api;

use App\Controller\Api\AbstractApiController;
use App\Dto\User\UpdateProfileRequest;
use App\Dto\User\UpdateSettingsRequest;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Service\User\UserDashboardService;
use App\Service\User\UserProfileService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user', name: 'api_user_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserDashboardController extends AbstractApiController
{
    public function __construct(
        private readonly UserDashboardService $userDashboardService,
        private readonly UserProfileService $userProfileService,
    ) {
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->userProfileService->toArray($guard),
        ]);
    }

    #[Route('/me', name: 'update_me', methods: ['PUT'])]
    public function updateMe(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new UpdateProfileRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->userProfileService->update($guard, $dto);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => $this->userDashboardService->getSummary($guard),
        ]);
    }

    #[Route('/settings', name: 'settings', methods: ['PATCH'])]
    public function settings(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        $dto = new UpdateSettingsRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $settings = $this->userDashboardService->sanitizeSettings([
            'theme' => $dto->theme,
            'notifications' => $dto->notifications,
            'compactView' => $dto->compactView,
        ]);
        $encoded = $this->userDashboardService->encodeSettings($settings);

        $response = $this->json(['success' => true, 'data' => $settings]);
        $response->headers->setCookie(new Cookie(
            'user_settings',
            $encoded,
            time() + 31536000,
            '/',
            null,
            false,
            false,
            false,
            'lax',
        ));

        return $response;
    }

    #[Route('/{module}', name: 'module_placeholder', methods: ['GET'], requirements: ['module' => 'consultations|forum'])]
    public function modulePlaceholder(Request $request, string $module): JsonResponse
    {
        $guard = $this->guard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        return $this->json([
            'success' => true,
            'data' => [
                'module' => $module,
                'status' => 'coming_soon',
                'message' => ucfirst($module) . ' module API is reserved for future integration.',
            ],
        ]);
    }

    private function guard(Request $request): User|JsonResponse
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
