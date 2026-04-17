<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Auth\TwoFactorCheckRequest;
use App\Dto\Auth\TwoFactorVerifyRequest;
use App\Entity\User;
use App\Service\AuthenticationService;
use App\Service\Security\RequestFingerprintService;
use App\Service\TwoFactorService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth/2fa')]
final class TwoFactorController extends AbstractApiController
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly AuthenticationService $authenticationService,
        private readonly RequestFingerprintService $requestFingerprintService,
    ) {
    }

    #[Route('/enable', name: 'api_auth_2fa_enable', methods: ['POST'])]
    public function enable(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $result = $this->twoFactorService->startSetup($user);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/verify', name: 'api_auth_2fa_verify', methods: ['POST'])]
    public function verify(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $dto = new TwoFactorVerifyRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->twoFactorService->verifySetup($user, trim($dto->code));
        $status = ($result->data['error'] ?? null) === 'invalid_2fa_code' ? 401 : ($result->success ? 200 : 400);

        return $this->json($result->toArray(), $status);
    }

    #[Route('/disable', name: 'api_auth_2fa_disable', methods: ['POST'])]
    public function disable(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $result = $this->twoFactorService->disable($user);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/check', name: 'api_auth_2fa_check', methods: ['POST'])]
    public function check(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new TwoFactorCheckRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $fingerprint = $this->requestFingerprintService->build($request);
        $result = $this->authenticationService->completeTwoFactorLogin(
            trim($dto->challengeId),
            trim($dto->code),
            $fingerprint,
        );
        $status = match ($result->data['error'] ?? null) {
            'two_factor_rate_limited' => 429,
            'invalid_2fa_code', 'invalid_2fa_challenge', 'two_factor_session_mismatch' => 401,
            default => ($result->success ? 200 : 400),
        };

        $response = $this->json($result->toArray(), $status);
        if ($result->success && is_string($result->data['refreshToken'] ?? null)) {
            $maxAge = (($result->data['rememberMe'] ?? false) === true) ? 2592000 : 604800;
            $response->headers->setCookie(new Cookie(
                'refresh_token',
                $result->data['refreshToken'],
                time() + $maxAge,
                '/',
                null,
                false,
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }
}
