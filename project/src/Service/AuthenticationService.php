<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Common\ServiceResult;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class AuthenticationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository,
        private AuthSessionRepository $authSessionRepository,
        private SessionService $sessionService,
        private AuditLogService $auditLogService,
        private UserPasswordHasherInterface $passwordHasher,
        private JwtService $jwtService,
        private TokenGenerator $tokenGenerator,
    ) {
    }

    public function register(RegisterRequest $request): ServiceResult
    {
        if ($request->password !== $request->confirmPassword) {
            return ServiceResult::failure('Passwords do not match.');
        }

        if ($this->userRepository->findByEmail($request->email) !== null) {
            return ServiceResult::failure('User already exists.');
        }

        $role = UserRole::tryFrom($request->role);
        if ($role === null) {
            return ServiceResult::failure('Invalid role.');
        }
        if ($role === UserRole::ADMIN) {
            return ServiceResult::failure('Admin role cannot be assigned from signup.');
        }

        $now = new \DateTimeImmutable();
        $user = (new User())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setEmail(mb_strtolower(trim($request->email)))
            ->setRole($role->value)
            ->setPresenceStatus(PresenceStatus::ONLINE->value)
            ->setAccountStatus(AccountStatus::ACTIVE->value)
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        $profile = (new Profile())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setUsername($this->generateUsername($user->getEmail()))
            ->setUser($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_SIGN_UP);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return ServiceResult::success('Registration successful.', $this->buildAuthPayload($user, $session->getRefreshToken()));
    }

    public function login(LoginRequest $request): ServiceResult
    {
        $user = str_contains($request->usernameOrEmail, '@')
            ? $this->userRepository->findByEmail($request->usernameOrEmail)
            : $this->profileRepository->findUserByUsername($request->usernameOrEmail);

        if (!$user instanceof User) {
            return ServiceResult::failure('Invalid credentials.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $request->password)) {
            return ServiceResult::failure('Invalid credentials.');
        }

        $this->sessionService->revokeActiveSessions($user);
        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_LOGIN);
        $this->entityManager->flush();

        return ServiceResult::success('Login successful.', $this->buildAuthPayload($user, $session->getRefreshToken(), $request->rememberMe));
    }

    public function logout(string $refreshToken): ServiceResult
    {
        $session = $this->authSessionRepository->findValidByRefreshToken($refreshToken);
        if ($session === null) {
            return ServiceResult::failure('Invalid or expired refresh token.');
        }

        $session->setRevoked(true);
        $this->auditLogService->log($session, AuditAction::USER_LOGOUT);
        $this->entityManager->flush();

        return ServiceResult::success('Logout successful.');
    }

    public function me(User $user): array
    {
        $profile = $this->profileRepository->findOneBy(['user' => $user]);

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'role' => $user->getRole(),
            'username' => $profile?->getUsername(),
            'accountStatus' => $user->getAccountStatus(),
            'presenceStatus' => $user->getPresenceStatus(),
            'faceRecognitionEnabled' => $user->isFaceRecognitionEnabled(),
        ];
    }

    private function buildAuthPayload(User $user, string $refreshToken, bool $rememberMe = false): array
    {
        $ttl = $rememberMe ? 86400 : 900;

        return [
            'token' => $this->jwtService->createAccessToken($user, $ttl),
            'tokenType' => 'Bearer',
            'expiresIn' => $ttl,
            'refreshToken' => $refreshToken,
            'user' => $this->me($user),
        ];
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
