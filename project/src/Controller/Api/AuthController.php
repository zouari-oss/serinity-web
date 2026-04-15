<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RefreshRequest;
use App\Dto\Auth\RegisterRequest;
use App\Service\AccessControlService;
use App\Service\AuthenticationService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class AuthController extends AbstractApiController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly AccessControlService $accessControlService,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    #[Route('/signup', name: 'api_auth_signup', methods: ['POST'])]
    public function register(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new RegisterRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->authenticationService->register($dto);
        $response = $this->json($result->toArray(), $result->success ? 201 : 400);

        return $this->withRefreshCookie($response, $result->data['refreshToken'] ?? null, 604800);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    #[Route('/signin', name: 'api_auth_signin', methods: ['POST'])]
    public function login(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new LoginRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->authenticationService->login($dto);
        $response = $this->json($result->toArray(), $result->success ? 200 : 401);

        return $this->withRefreshCookie($response, $result->data['refreshToken'] ?? null, $dto->rememberMe ? 2592000 : 604800);
    }

    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new RefreshRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if ($dto->refreshToken === '' && $request->cookies->has('refresh_token')) {
            $dto->refreshToken = (string) $request->cookies->get('refresh_token', '');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->accessControlService->refresh($dto);
        $response = $this->json($result->toArray(), $result->success ? 200 : 401);

        return $this->withRefreshCookie($response, $result->data['refreshToken'] ?? null, 604800);
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $request->cookies->get('refresh_token') ?: $this->bearerToken($request);
        if ($token === null || $token === '') {
            return $this->json(['success' => false, 'message' => 'Missing refresh token.'], 401);
        }

        $result = $this->authenticationService->logout($token);
        $response = $this->json($result->toArray(), $result->success ? 200 : 401);

        $response->headers->clearCookie('refresh_token', '/', null, true, true, 'lax');

        return $response;
    }

    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        return $this->json([
            'success' => true,
            'data' => $this->authenticationService->me($user),
        ]);
    }

    private function withRefreshCookie(JsonResponse $response, ?string $refreshToken, int $maxAge): JsonResponse
    {
        if (!is_string($refreshToken) || $refreshToken === '') {
            return $response;
        }

        $response->headers->setCookie(new Cookie(
            'refresh_token',
            $refreshToken,
            time() + $maxAge,
            '/',
            null,
            false,
            true,
            false,
            'lax',
        ));

        return $response;
    }
}
