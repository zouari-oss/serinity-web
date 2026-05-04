<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RefreshRequest;
use App\Dto\Auth\RegisterRequest;
use App\Service\AccessControlService;
use App\Service\AuthenticationService;
use App\Service\GoogleAuthService;
use App\Service\Security\RequestFingerprintService;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        private readonly ClientRegistry $clientRegistry,
        private readonly GoogleAuthService $googleAuthService,
        private readonly RequestFingerprintService $requestFingerprintService,
        private readonly string $googleRedirectUri,
        private readonly LoggerInterface $logger,
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
        $status = $result->success
            ? (($result->data['requiresEmailVerification'] ?? false) ? 202 : 201)
            : 400;
        $response = $this->json($result->toArray(), $status);

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

        $result = $this->authenticationService->login(
            $dto,
            $this->requestFingerprintService->build($request),
        );
        $error = $result->data['error'] ?? null;
        $status = in_array($error, ['account_disabled', 'account_banned'], true)
            ? 403
            : ($result->success ? 200 : 401);
        $response = $this->json($result->toArray(), $status);

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

    public function googleConnect(Request $request): RedirectResponse
    {
        $options = [];
        if ($this->googleRedirectUri !== '') {
            $configured = parse_url($this->googleRedirectUri);
            $configuredHost = $configured['host'] ?? null;
            if (is_string($configuredHost) && $configuredHost !== '' && $configuredHost !== $request->getHost()) {
                $scheme = (string) ($configured['scheme'] ?? $request->getScheme());
                $port = isset($configured['port']) ? ':' . (int) $configured['port'] : '';

                return new RedirectResponse(sprintf(
                    '%s://%s%s%s',
                    $scheme,
                    $configuredHost,
                    $port,
                    '/api/auth/google/connect',
                ));
            }

            $options['redirect_uri'] = $this->googleRedirectUri;
        }

        return $this->clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], $options);
    }

    public function googleCallback(): RedirectResponse
    {
        $client = $this->clientRegistry->getClient('google');

        try {
            $googleUser = $client->fetchUser();
        } catch (IdentityProviderException|\RuntimeException $exception) {
            $message = mb_strtolower($exception->getMessage());
            $errorCode = str_contains($message, 'state')
                ? 'google_auth_state_mismatch'
                : 'google_auth_failed';

            $this->logger->error('Google OAuth callback failed.', [
                'error_code' => $errorCode,
                'exception' => $exception->getMessage(),
                'type' => $exception::class,
            ]);

            return $this->failureRedirect($errorCode);
        }

        $profile = $googleUser->toArray();
        $googleId = trim((string) $googleUser->getId());
        $email = trim((string) ($googleUser->getEmail() ?? ''));
        $name = trim((string) ($googleUser->getName() ?? ''));
        $isVerified = filter_var(
            $profile['email_verified'] ?? $profile['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        $result = $this->googleAuthService->authenticateOrRegister(
            $googleId,
            $email,
            (bool) $isVerified,
            $name !== '' ? $name : null,
        );

        if (!$result->success) {
            return $this->failureRedirect((string) ($result->data['error'] ?? 'google_auth_failed'));
        }

        $token = (string) ($result->data['token'] ?? '');
        $refreshToken = (string) ($result->data['refreshToken'] ?? '');
        $role = mb_strtoupper((string) ($result->data['user']['role'] ?? ''));
        $target = $role === 'ADMIN' ? '/admin/dashboard' : '/user/dashboard';

        $response = new RedirectResponse($target);
        if ($token !== '') {
            $response->headers->setCookie(new Cookie(
                'access_token',
                $token,
                time() + 900,
                '/',
                null,
                false,
                false,
                false,
                'lax',
            ));
        }
        if ($refreshToken !== '') {
            $response->headers->setCookie(new Cookie(
                'refresh_token',
                $refreshToken,
                time() + 604800,
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

    private function failureRedirect(string $error): RedirectResponse
    {
        return new RedirectResponse('/login?mode=signin&oauth_error=' . rawurlencode($error));
    }
}
