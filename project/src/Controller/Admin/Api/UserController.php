<?php

declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Dto\Admin\ChangeAccountStatusRequest;
use App\Dto\Admin\UpdateUserRequest;
use App\Dto\Admin\UserFilterRequest;
use App\Service\Admin\UserManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/users', name: 'api_admin_users_')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

    /**
     * Get paginated list of users with optional filters.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filterRequest = new UserFilterRequest(
            page: max(1, (int) $request->query->get('page', 1)),
            limit: min(100, max(1, (int) $request->query->get('limit', 20))),
            email: $request->query->get('email'),
            role: $request->query->get('role'),
            accountStatus: $request->query->get('accountStatus'),
        );

        $result = $this->userManagementService->getUsersPaginated($filterRequest);

        // Serialize users without sensitive data
        $users = array_map(fn ($user) => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'accountStatus' => $user->getAccountStatus(),
            'presenceStatus' => $user->getPresenceStatus(),
            'createdAt' => $user->getCreatedAt()->format('c'),
            'profile' => $user->getProfile() ? [
                'username' => $user->getProfile()->getUsername(),
                'firstName' => $user->getProfile()->getFirstName(),
                'lastName' => $user->getProfile()->getLastName(),
            ] : null,
        ], $result['users']);

        return $this->json([
            'success' => true,
            'data' => [
                'users' => $users,
                'pagination' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'limit' => $result['limit'],
                    'totalPages' => $result['totalPages'],
                ],
            ],
        ]);
    }

    /**
     * Update user details.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateUserRequest $request
    ): JsonResponse {
        $result = $this->userManagementService->updateUser($id, $request);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_USER_UPDATE_FAILED',
                    'message' => $result->message,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => $result->message,
        ]);
    }

    /**
     * Delete user.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $result = $this->userManagementService->deleteUser($id);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_USER_DELETE_FAILED',
                    'message' => $result->message,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => $result->message,
        ]);
    }

    /**
     * Change user account status.
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
    public function changeStatus(
        string $id,
        #[MapRequestPayload] ChangeAccountStatusRequest $request
    ): JsonResponse {
        $result = $this->userManagementService->changeAccountStatus($id, $request);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => [
                    'code' => 'ADMIN_USER_STATUS_UPDATE_FAILED',
                    'message' => $result->message,
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => $result->message,
            'data' => [
                'accountStatus' => $result->data['user']->getAccountStatus(),
            ],
        ]);
    }
}
