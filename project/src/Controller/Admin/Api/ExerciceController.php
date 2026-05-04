<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Api\AbstractApiController;
use App\Dto\Exercice\AssignExerciceRequest;
use App\Dto\Exercice\ExerciceUpsertRequest;
use App\Entity\User;
use App\Service\Admin\AdminExerciceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/exercice', name: 'api_admin_exercice_')]
#[IsGranted('ROLE_ADMIN')]
final class ExerciceController extends AbstractApiController
{
    public function __construct(
        private readonly AdminExerciceService $adminExerciceService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $search = $this->queryString($request, 'search');
        $type = $this->queryString($request, 'type');
        $active = $this->queryNullableBool($request, 'active');

        return $this->json([
            'success' => true,
            'data' => [
                'items' => $this->adminExerciceService->listExercices($search, $type, $active),
                'summary' => $this->adminExerciceService->summary(),
            ],
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new ExerciceUpsertRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->adminExerciceService->createExercice($dto);

        return $this->json($result->toArray(), $result->success ? 201 : 400);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new ExerciceUpsertRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->adminExerciceService->updateExercice($id, $dto);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $result = $this->adminExerciceService->deleteExercice($id);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/assign', name: 'assign', methods: ['POST'])]
    public function assign(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new AssignExerciceRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            return $this->json(['success' => false, 'message' => 'Malformed JSON payload.'], 400);
        }
        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $result = $this->adminExerciceService->assignExercice($dto->exerciceId, $dto->userId, $admin);

        return $this->json($result->toArray(), $result->success ? 201 : 400);
    }

    #[Route('/controls', name: 'controls', methods: ['GET'])]
    public function controls(Request $request): JsonResponse
    {
        $status = $this->queryString($request, 'status');
        $role = $this->queryString($request, 'role');

        return $this->json([
            'success' => true,
            'data' => [
                'items' => $this->adminExerciceService->monitor($status, $role),
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

    private function queryNullableBool(Request $request, string $key): ?bool
    {
        $value = $request->query->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
