<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final readonly class ApiAwareEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse|RedirectResponse
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Authentication is required.',
                ],
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->router->generate('ac_ui_login'));
    }
}
