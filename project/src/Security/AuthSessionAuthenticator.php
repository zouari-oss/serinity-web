<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\AuthSessionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class AuthSessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly AuthSessionRepository $authSessionRepository)
    {
    }

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return false;
        }

        return !$this->isPublicPath($request->getPathInfo());
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            throw new CustomUserMessageAuthenticationException('Invalid bearer token.');
        }

        $session = $this->authSessionRepository->findValidByRefreshToken($token);
        if ($session === null) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired session token.');
        }

        return new SelfValidatingPassport(
            new UserBadge($session->getUser()->getUserIdentifier(), static fn () => $session->getUser()),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function isPublicPath(string $path): bool
    {
        return in_array($path, [
            '/api/auth/signup',
            '/api/auth/signin',
            '/api/auth/refresh',
            '/api/auth/forgot-password',
            '/api/auth/verify-reset-code',
            '/api/auth/reset-password',
            '/api/auth/reset/send',
            '/api/auth/reset/confirm',
            '/api/auth/verify-email',
            '/api/auth/verify-email/resend',
        ], true);
    }
}
