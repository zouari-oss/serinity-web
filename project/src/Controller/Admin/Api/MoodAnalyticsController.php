<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Api\AbstractApiController;
use App\Dto\Mood\MoodHistoryFilterRequest;
use App\Dto\Mood\MoodSummaryRequest;
use App\Service\Admin\AdminMoodAnalyticsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/mood', name: 'api_admin_mood_')]
#[IsGranted('ROLE_ADMIN')]
final class MoodAnalyticsController extends AbstractApiController
{
    public function __construct(
        private readonly AdminMoodAnalyticsService $adminMoodAnalyticsService,
    ) {
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new MoodSummaryRequest();
        $dto->days = $this->queryInt($request, 'days', 7, 1, 90);
        $dto->fromDate = $this->queryString($request, 'fromDate');
        $dto->toDate = $this->queryString($request, 'toDate');

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        return $this->json([
            'success' => true,
            'data' => $this->adminMoodAnalyticsService->getSummary($dto),
        ]);
    }

    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new MoodHistoryFilterRequest();
        $dto->search = $this->queryString($request, 'search');
        $dto->momentType = $this->queryUppercaseString($request, 'momentType');
        $dto->fromDate = $this->queryString($request, 'fromDate');
        $dto->toDate = $this->queryString($request, 'toDate');
        $dto->level = $this->queryNullableInt($request, 'level', 1, 5);
        $dto->page = $this->queryInt($request, 'page', 1, 1);
        $dto->limit = $this->queryInt($request, 'limit', 20, 1, 100);

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        return $this->json([
            'success' => true,
            'data' => $this->adminMoodAnalyticsService->getHistory($dto),
        ]);
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function queryUppercaseString(Request $request, string $key): ?string
    {
        $value = $this->queryString($request, $key);

        return $value === null ? null : strtoupper($value);
    }

    private function queryInt(
        Request $request,
        string $key,
        int $default,
        int $min,
        ?int $max = null
    ): int {
        $value = $request->query->get($key);
        if (!is_scalar($value) || !is_numeric((string) $value)) {
            return $default;
        }

        $normalized = max($min, (int) $value);
        if ($max !== null) {
            $normalized = min($max, $normalized);
        }

        return $normalized;
    }

    private function queryNullableInt(Request $request, string $key, int $min, ?int $max = null): ?int
    {
        $value = $request->query->get($key);
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !is_numeric((string) $value)) {
            return null;
        }

        $normalized = max($min, (int) $value);
        if ($max !== null) {
            $normalized = min($max, $normalized);
        }

        return $normalized;
    }
}
