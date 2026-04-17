<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Enum\AuditAction;
use App\Enum\PresenceStatus;
use App\Enum\UserRole;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class GoogleAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository,
        private SessionService $sessionService,
        private AuditLogService $auditLogService,
        private UserPasswordHasherInterface $passwordHasher,
        private JwtService $jwtService,
        private TokenGenerator $tokenGenerator,
    ) {
    }

    public function authenticateOrRegister(string $googleId, string $email, bool $emailVerified, ?string $name = null): ServiceResult
    {
        $googleId = trim($googleId);
        $email = mb_strtolower(trim($email));

        if ($googleId === '' || $email === '') {
            return ServiceResult::failure('Google authentication payload is incomplete.', [
                'error' => 'google_auth_invalid_payload',
            ]);
        }

        if (!$emailVerified) {
            return ServiceResult::failure('Google account email must be verified.', [
                'error' => 'google_email_not_verified',
            ]);
        }

        $user = $this->userRepository->findByGoogleId($googleId);
        if ($user instanceof User) {
            return $this->loginUser($user);
        }

        $existingByEmail = $this->userRepository->findByEmail($email);
        if ($existingByEmail instanceof User) {
            $linkedGoogleId = $existingByEmail->getGoogleId();
            if ($linkedGoogleId !== null && $linkedGoogleId !== '' && $linkedGoogleId !== $googleId) {
                return ServiceResult::failure('This email is linked to another Google account.', [
                    'error' => 'google_account_link_conflict',
                ]);
            }

            $existingByEmail
                ->setGoogleId($googleId)
                ->setUpdatedAt(new \DateTimeImmutable());

            return $this->loginUser($existingByEmail);
        }

        return $this->createUserAndLogin($googleId, $email, $name);
    }

    private function loginUser(User $user): ServiceResult
    {
        $this->sessionService->revokeActiveSessions($user);
        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_LOGIN);
        $this->entityManager->flush();

        return ServiceResult::success('Login successful.', $this->buildAuthPayload($user, $session->getRefreshToken()));
    }

    private function createUserAndLogin(string $googleId, string $email, ?string $name): ServiceResult
    {
        $now = new \DateTimeImmutable();
        $user = (new User())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setEmail($email)
            ->setGoogleId($googleId)
            ->setRole(UserRole::PATIENT->value)
            ->setPresenceStatus(PresenceStatus::ONLINE->value)
            ->setAccountStatus(AccountStatus::ACTIVE->value)
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        [$firstName, $lastName] = $this->splitName($name);
        $profile = (new Profile())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setUsername($this->generateUsername($email))
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setUser($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->sessionService->revokeActiveSessions($user);
        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_SIGN_UP);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return ServiceResult::success('Registration successful.', $this->buildAuthPayload($user, $session->getRefreshToken()));
    }

    private function buildAuthPayload(User $user, string $refreshToken): array
    {
        $ttl = 900;
        $profile = $this->profileRepository->findOneBy(['user' => $user]);

        return [
            'token' => $this->jwtService->createAccessToken($user, $ttl),
            'tokenType' => 'Bearer',
            'expiresIn' => $ttl,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'role' => $user->getRole(),
                'username' => $profile?->getUsername(),
                'accountStatus' => $user->getAccountStatus(),
                'presenceStatus' => $user->getPresenceStatus(),
                'faceRecognitionEnabled' => $user->isFaceRecognitionEnabled(),
            ],
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

    /** @return array{0: ?string, 1: ?string} */
    private function splitName(?string $name): array
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        if ($parts === []) {
            return [null, null];
        }

        $firstName = $parts[0] ?? null;
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return [$firstName, $lastName];
    }
}
