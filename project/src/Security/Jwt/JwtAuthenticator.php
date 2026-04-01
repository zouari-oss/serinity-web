<?php

declare(strict_types=1);

namespace App\Security\Jwt;

use App\Repository\UserRepository;
use App\Service\AccessControl\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/auth/me');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            throw new CustomUserMessageAuthenticationException('Missing bearer token.');
        }

        $token = trim(substr($header, 7));
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
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
