<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Auth\RefreshRequest;
use App\Dto\Auth\ResetPasswordConfirmRequest;
use App\Dto\Auth\ResetPasswordSendRequest;
use App\Dto\Auth\SignInRequest;
use App\Dto\Auth\SignUpRequest;
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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AccessControlService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ProfileRepository $profileRepository,
        private AuthSessionRepository $authSessionRepository,
        private SessionService $sessionService,
        private AuditLogService $auditLogService,
        private ResetPasswordService $resetPasswordService,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
    ) {
    }

    public function signUp(SignUpRequest $request): ServiceResult
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
        if ($role === UserRole::ADMIN) {
            return ServiceResult::failure('Admin role cannot be assigned from signup.');
        }

        $now = new \DateTimeImmutable();
        $user = (new User())
            ->setId(Uuid::v4()->toRfc4122())
            ->setEmail(mb_strtolower(trim($request->email)))
            ->setRole($role->value)
            ->setPresenceStatus(PresenceStatus::ONLINE->value)
            ->setAccountStatus(AccountStatus::ACTIVE->value)
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $user->setPassword($this->passwordHasher->hashPassword($user, $request->password));

        $profile = (new Profile())
            ->setId(Uuid::v4()->toRfc4122())
            ->setUsername($this->generateUsername($user->getEmail()))
            ->setUser($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_SIGN_UP);

        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return ServiceResult::success('User registered successfully.', $this->sessionPayload($user, $profile, $session->getRefreshToken()));
    }

    public function signIn(SignInRequest $request): ServiceResult
    {
        $user = str_contains($request->usernameOrEmail, '@')
            ? $this->userRepository->findByEmail($request->usernameOrEmail)
            : $this->profileRepository->findUserByUsername($request->usernameOrEmail);

        if (!$user instanceof User) {
            return ServiceResult::failure('User not found.');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $request->password)) {
            return ServiceResult::failure('Incorrect password.');
        }

        $this->sessionService->revokeActiveSessions($user);
        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_LOGIN);
        $this->entityManager->flush();

        $profile = $this->profileRepository->findOneBy(['user' => $user]);

        return ServiceResult::success('User signed in successfully.', $this->sessionPayload($user, $profile, $session->getRefreshToken()));
    }

    public function refresh(RefreshRequest $request): ServiceResult
    {
        $current = $this->authSessionRepository->findValidByRefreshToken($request->refreshToken);
        if ($current === null) {
            return ServiceResult::failure('Invalid or expired refresh token.');
        }

        $current->setRevoked(true);
        $newSession = $this->sessionService->createSession($current->getUser());
        $this->auditLogService->log($newSession, AuditAction::TOKEN_REFRESH);
        $this->entityManager->flush();

        $profile = $this->profileRepository->findOneBy(['user' => $current->getUser()]);

        return ServiceResult::success('Token refreshed successfully.', $this->sessionPayload($current->getUser(), $profile, $newSession->getRefreshToken()));
    }

    public function logout(string $refreshToken): ServiceResult
    {
        $session = $this->authSessionRepository->findValidByRefreshToken($refreshToken);
        if ($session === null) {
            return ServiceResult::failure('Session already expired or invalid.');
        }

        $session->setRevoked(true);
        $this->auditLogService->log($session, AuditAction::USER_LOGOUT);
        $this->entityManager->flush();

        return ServiceResult::success('Logged out successfully.');
    }

    public function sendResetCode(ResetPasswordSendRequest $request): ServiceResult
    {
        $user = $this->userRepository->findByEmail($request->email);
        if ($user === null) {
            return ServiceResult::failure('User does not exist.');
        }

        $profile = $this->profileRepository->findOneBy(['user' => $user]);
        $code = $this->resetPasswordService->issueCode($user->getEmail());

        $mail = (new Email())
            ->to($user->getEmail())
            ->subject('Serinity reset code')
            ->text(sprintf('Hello %s, your reset code is: %s', $profile?->getUsername() ?? 'user', $code));

        $this->mailer->send($mail);

        return ServiceResult::success('Reset code sent successfully.');
    }

    public function confirmResetCode(ResetPasswordConfirmRequest $request): ServiceResult
    {
        if (!$this->resetPasswordService->matches($request->email, $request->code)) {
            return ServiceResult::failure('Code expired or invalid.');
        }

        $user = $this->userRepository->findByEmail($request->email);
        if ($user === null) {
            return ServiceResult::failure('User does not exist.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $request->newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->resetPasswordService->clear($request->email);

        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::PASSWORD_CHANGED);
        $this->entityManager->flush();

        return ServiceResult::success('Password updated successfully.');
    }

    private function sessionPayload(User $user, ?Profile $profile, string $refreshToken): array
    {
        return [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'presenceStatus' => $user->getPresenceStatus(),
                'accountStatus' => $user->getAccountStatus(),
                'faceRecognitionEnabled' => $user->isFaceRecognitionEnabled(),
                'username' => $profile?->getUsername(),
            ],
            'refreshToken' => $refreshToken,
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
