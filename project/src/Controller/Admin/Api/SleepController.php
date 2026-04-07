<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Api\AbstractApiController;
use App\Service\Admin\AdminSleepService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/sleep', name: 'api_admin_sleep_')]
#[IsGranted('ROLE_ADMIN')]
final class SleepController extends AbstractApiController
{
    public function __construct(
        private readonly AdminSleepService $adminSleepService,
    ) {
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->adminSleepService->summary(),
        ]);
    }

    #[Route('/sessions', name: 'sessions', methods: ['GET'])]
    public function sessions(Request $request): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'items' => $this->adminSleepService->sessions(
                    $this->queryString($request, 'q'),
                    $this->queryString($request, 'quality'),
                ),
            ],
        ]);
    }

    #[Route('/dreams', name: 'dreams', methods: ['GET'])]
    public function dreams(Request $request): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'items' => $this->adminSleepService->dreams(
                    $this->queryString($request, 'q'),
                    $this->queryString($request, 'type'),
                ),
            ],
        ]);
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

