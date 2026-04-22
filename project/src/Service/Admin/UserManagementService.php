<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Admin\ChangeAccountStatusRequest;
use App\Dto\Admin\CreateUserRequest;
use App\Dto\Admin\UpdateUserRequest;
use App\Dto\Admin\UserFilterRequest;
use App\Dto\Common\ServiceResult;
use App\Entity\AuthSession;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Enum\AuditAction;
use App\Enum\PresenceStatus;
use App\Enum\UserRole;
use App\Repository\AuthSessionRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for admin user management operations.
 * Wraps UserRepository and provides business logic for CRUD operations.
 */
final readonly class UserManagementService
{
    public function __construct(
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository,
        private AuthSessionRepository $authSessionRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private \App\Service\TokenGenerator $tokenGenerator,
        private \App\Service\AuditLogService $auditLogService,
        private Security $security,
    ) {
    }

    /**
     * Get paginated users with optional filters.
     */
    public function getUsersPaginated(UserFilterRequest $request): array
    {
        return $this->userRepository->findPaginatedNonAdmin(
            $request->page,
            $request->limit,
            $request->toFilters()
        );
    }

    public function createUser(CreateUserRequest $request): ServiceResult
    {
        if ($request->password !== $request->confirmPassword) {
            return ServiceResult::failure('Passwords do not match.');
        }

        if ($this->userRepository->findByEmail($request->email) !== null) {
            return ServiceResult::failure('Email is already used.');
        }

        $role = UserRole::tryFrom($request->role);
        if ($role === null) {
            return ServiceResult::failure('Invalid role.');
        }

        $status = AccountStatus::tryFrom($request->accountStatus);
        if ($status === null) {
            return ServiceResult::failure('Invalid account status.');
        }

        $now = new \DateTimeImmutable();
        $user = (new User())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setEmail(mb_strtolower(trim($request->email)))
            ->setRole($role->value)
            ->setPresenceStatus(PresenceStatus::ONLINE->value)
            ->setAccountStatus($status->value)
            ->setFaceRecognitionEnabled(false)
            ->setTwoFactorEnabled(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        $profile = (new Profile())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setUsername($this->generateUsername($user->getEmail()))
            ->setUser($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->logAdminAction(AuditAction::USER_SIGN_UP);
        $this->entityManager->flush();

        return ServiceResult::success('User created successfully.', ['user' => $user]);
    }

    /**
     * Update user details.
     */
    public function updateUser(string $id, UpdateUserRequest $request): ServiceResult
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return ServiceResult::failure('User not found.');
        }

        // Update email
        if ($user->getEmail() !== $request->email) {
            $existingUser = $this->userRepository->findByEmail($request->email);
            if ($existingUser !== null && $existingUser->getId() !== $id) {
                return ServiceResult::failure('Email is already in use by another user.');
            }
            $user->setEmail($request->email);
        }

        // Update role
        $role = UserRole::tryFrom($request->role);
        if ($role === null) {
            return ServiceResult::failure('Invalid role.');
        }
        $previousRole = $user->getRole();
        $user->setRole($role->value);

        // Update account status if provided
        if ($request->accountStatus !== null) {
            $status = AccountStatus::tryFrom($request->accountStatus);
            if ($status === null) {
                return ServiceResult::failure('Invalid account status.');
            }
            $user->setAccountStatus($status->value);
        }

        // Update password if provided
        if ($request->password !== null) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $request->password);
            $user->setPassword($hashedPassword);
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->logAdminAction(AuditAction::USER_UPDATED);
        if ($previousRole !== $role->value) {
            $this->logAdminAction(AuditAction::ROLE_CHANGED);
        }
        $this->entityManager->flush();

        return ServiceResult::success('User updated successfully.', ['user' => $user]);
    }

    /**
     * Delete user from the system.
     */
    public function deleteUser(string $id): ServiceResult
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return ServiceResult::failure('User not found.');
        }

        $this->entityManager->remove($user);
        $this->logAdminAction(AuditAction::USER_DELETED);
        $this->entityManager->flush();

        return ServiceResult::success('User deleted successfully.');
    }

    /**
     * Change user account status (ACTIVE <-> DISABLED).
     */
    public function changeAccountStatus(string $id, ChangeAccountStatusRequest $request): ServiceResult
    {
        $user = $this->userRepository->find($id);
        if ($user === null) {
            return ServiceResult::failure('User not found.');
        }

        $status = AccountStatus::tryFrom($request->accountStatus);
        if ($status === null) {
            return ServiceResult::failure('Invalid account status.');
        }

        $user->setAccountStatus($status->value);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->logAdminAction(AuditAction::USER_UPDATED);
        $this->entityManager->flush();

        return ServiceResult::success('Account status updated successfully.', ['user' => $user]);
    }

    /**
     * Get user statistics.
     */
    public function getUserStatistics(): array
    {
        return [
            'total' => $this->userRepository->countUsers(),
            'active' => $this->userRepository->countByAccountStatus(AccountStatus::ACTIVE),
            'disabled' => $this->userRepository->countByAccountStatus(AccountStatus::DISABLED),
            'admins' => $this->userRepository->countByRole(UserRole::ADMIN),
            'therapists' => $this->userRepository->countByRole(UserRole::THERAPIST),
            'patients' => $this->userRepository->countByRole(UserRole::PATIENT),
        ];
    }

    private function logAdminAction(AuditAction $action): void
    {
        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof User) {
            return;
        }

        $activeSessions = $this->authSessionRepository->findActiveForUser($adminUser);
        if ($activeSessions === []) {
            return;
        }

        /** @var AuthSession $session */
        $session = $activeSessions[0];
        $this->auditLogService->log($session, $action);
    }

    private function generateUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '_', strtolower(strtok($email, '@') ?: 'user'));
        $username = $base;
        $i = 1;

        while ($this->profileRepository->findOneBy(['username' => $username]) !== null) {
            ++$i;
            $username = sprintf('%s_%d', $base, $i);
        }

        return $username;
    }
}
