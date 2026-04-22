<?php

declare(strict_types=1);

namespace App\Security\Jwt;

use App\Exception\DisabledAccountException;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly UserRepository $userRepository,
        private readonly RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();
        if (preg_match('#^/api/auth/(login|signin|register|signup|refresh|logout|forgot-password|verify-reset-code|reset-password|reset/send|reset/confirm|verify-email|verify-email/resend|face/login|google/connect|google/callback|2fa/check)$#', $path) === 1) {
            return false;
        }

        return $this->extractToken($request) !== null
            && (
                str_starts_with($path, '/api/')
                || str_starts_with($path, '/admin')
                || str_starts_with($path, '/user')
                || str_starts_with($path, '/sommeil')
                || str_starts_with($path, '/reve')
                || $path === '/dashboard'
            );
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $token = $this->extractToken($request);
        if ($token === null || $token === '') {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $payload = $this->jwtService->parseAndValidate($token);

        if ($payload === null || !isset($payload['sub']) || !is_string($payload['sub'])) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token.');
        }

        return new SelfValidatingPassport(new UserBadge($payload['sub'], function (string $id) {
            $user = $this->userRepository->find($id);
            if ($user === null) {
                throw new CustomUserMessageAuthenticationException('User not found.');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (
            str_starts_with($request->getPathInfo(), '/admin')
            || str_starts_with($request->getPathInfo(), '/user')
            || str_starts_with($request->getPathInfo(), '/sommeil')
            || str_starts_with($request->getPathInfo(), '/reve')
            || $request->getPathInfo() === '/dashboard'
        ) {
            $response = new RedirectResponse($this->router->generate('ac_ui_login'));
            $response->headers->clearCookie('access_token', '/');

            return $response;
        }

        if ($exception instanceof DisabledAccountException) {
            return new JsonResponse([
                'success' => false,
                'error' => 'account_disabled',
                'message' => 'Your account is disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): ?string
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        $cookieToken = $request->cookies->get('access_token');
        if (is_string($cookieToken) && $cookieToken !== '') {
            return $cookieToken;
        }

        return null;
    }
}
