<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Message\GenerateAnimeAvatarMessage;
use App\Service\Avatar\AvatarGenerationPendingStore;
use App\Service\Security\AvatarGenerationRateLimiter;
use App\Service\User\UserProfileService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/avatar', name: 'api_avatar_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AvatarController extends AbstractApiController
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
        private readonly AvatarGenerationRateLimiter $rateLimiter,
        private readonly AvatarGenerationPendingStore $pendingStore,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $storedAvatar = $this->userProfileService->getStoredAvatarIfFresh($user);
        if (is_string($storedAvatar) && trim($storedAvatar) !== '') {
            return $this->json([
                'image' => $storedAvatar,
            ]);
        }

        $profileImageUrl = trim((string) $user->getProfile()?->getProfileImageUrl());
        if ($profileImageUrl === '') {
            return $this->json([
                'error' => 'profile_image_missing',
                'message' => 'Please upload a profile image first.',
            ], 422);
        }

        $rateLimitKey = sprintf(
            '%s|%s|%s',
            $user->getId(),
            (string) ($request->getClientIp() ?? 'unknown'),
            mb_substr((string) $request->headers->get('User-Agent', ''), 0, 120),
        );

        if ($this->rateLimiter->isLimited($rateLimitKey)) {
            return $this->json([
                'error' => 'avatar_generation_rate_limited',
                'message' => 'Too many avatar generation attempts. Please try again later.',
            ], 429);
        }

        try {
            if (!$this->pendingStore->isPending($user->getId())) {
                $this->pendingStore->markPending($user->getId());
                $this->messageBus->dispatch(new GenerateAnimeAvatarMessage($user->getId()));
            }
        } catch (\Throwable) {
            $this->rateLimiter->recordFailure($rateLimitKey);

            return $this->json([
                'error' => 'generation_enqueue_failed',
                'message' => 'Unable to queue avatar generation at the moment.',
            ], 503);
        }

        return $this->json([
            'status' => 'processing',
            'message' => 'Avatar generation is processing in background.',
        ], 202);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'unauthorized',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $storedAvatar = $this->userProfileService->getStoredAvatarIfFresh($user);
        if (is_string($storedAvatar) && trim($storedAvatar) !== '') {
            return $this->json([
                'image' => $storedAvatar,
            ]);
        }

        if ($this->pendingStore->isPending($user->getId())) {
            return $this->json([
                'status' => 'processing',
                'message' => 'Avatar generation is still processing.',
            ], 202);
        }

        return $this->json([
            'error' => 'generation_not_ready',
            'message' => 'Avatar is not ready. Please start generation again.',
        ], 409);
    }
}
