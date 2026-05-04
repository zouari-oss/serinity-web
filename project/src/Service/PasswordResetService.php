<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenGenerator $tokenGenerator,
        private MailerService $mailerService,
        private SessionService $sessionService,
        private AuditLogService $auditLogService,
        private CacheInterface $cache,
        private int $codeTtlMinutes,
        private int $rateLimitAttempts,
        private int $rateLimitWindowSeconds,
        private int $maxResendAttempts,
    ) {
    }

    public function requestResetCode(string $email, ?string $ipAddress = null, bool $resend = false): ServiceResult
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($normalizedEmail === '') {
            return ServiceResult::failure('Email address is required.', [
                'error' => 'email_required',
            ]);
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if (!$user instanceof User) {
            return ServiceResult::failure('Email address not found.', [
                'error' => 'email_not_found',
            ]);
        }

        if (!$this->consumeRequestAllowance($normalizedEmail, $ipAddress)) {
            return ServiceResult::failure('Too many reset requests. Please try again later.', [
                'error' => 'rate_limited',
            ]);
        }

        if (!$this->consumeResendAllowance($normalizedEmail, $resend)) {
            return ServiceResult::failure('You reached the maximum number of resends. Redirecting to login.', [
                'error' => 'resend_limit_reached',
                'redirect' => '/login',
            ]);
        }

        $code = $this->tokenGenerator->generateNumericCode(6);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', $this->codeTtlMinutes));
        $this->storeResetCodeState($normalizedEmail, $code, $expiresAt);

        $recipientName = $user->getProfile()?->getUsername() ?: 'there';
        $this->mailerService->sendTemplateHtmlEmail(
            to: $user->getEmail(),
            subject: 'Your Serinity password reset code',
            template: 'emails/reset_password_code.html.twig',
            context: [
                'recipientName' => $recipientName,
                'code' => $code,
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
            ],
            plainText: sprintf(
                'Hello %s, your Serinity password reset code is %s. It expires in %d minutes.',
                $recipientName,
                $code,
                $this->codeTtlMinutes,
            ),
        );

        return ServiceResult::success('Reset code sent successfully.');
    }

    public function verifyResetCode(string $email, string $code): ServiceResult
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user instanceof User) {
            return $this->invalidOrExpiredCodeResult();
        }

        if (!$this->isCodeValid($this->normalizeEmail($email), $code, new \DateTimeImmutable())) {
            return $this->invalidOrExpiredCodeResult();
        }

        return ServiceResult::success('Reset code verified successfully.');
    }

    public function resetPassword(string $email, string $code, string $newPassword): ServiceResult
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user instanceof User) {
            return $this->invalidOrExpiredCodeResult();
        }

        $now = new \DateTimeImmutable();
        $normalizedEmail = $this->normalizeEmail($email);
        if (!$this->isCodeValid($normalizedEmail, $code, $now)) {
            return $this->invalidOrExpiredCodeResult();
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setUpdatedAt($now);
        $this->clearResetCodeState($normalizedEmail);

        $this->sessionService->revokeActiveSessions($user);
        $auditSession = $this->sessionService->createSession($user);
        $this->auditLogService->log($auditSession, AuditAction::PASSWORD_CHANGED);
        $this->entityManager->flush();

        return ServiceResult::success('Password updated successfully.');
    }

    private function consumeRequestAllowance(string $email, ?string $ipAddress): bool
    {
        $cacheKey = 'password_reset_rate_limit_' . hash('sha256', $email . '|' . ($ipAddress ?? 'unknown'));
        $now = time();
        $windowStart = $now - $this->rateLimitWindowSeconds;

        $attempts = $this->cache->get($cacheKey, static fn (ItemInterface $item): array => []);
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $attempts = array_values(array_filter($attempts, static fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp >= $windowStart));

        if (count($attempts) >= $this->rateLimitAttempts) {
            return false;
        }

        $attempts[] = $now;
        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($attempts): array {
            $item->expiresAfter($this->rateLimitWindowSeconds);

            return $attempts;
        });

        return true;
    }

    private function invalidOrExpiredCodeResult(): ServiceResult
    {
        return ServiceResult::failure('The code is invalid or has expired.', [
            'error' => 'invalid_or_expired_code',
        ]);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function resendAttemptsKey(string $email): string
    {
        return 'password_reset_resend_attempts_' . hash('sha256', $email);
    }

    private function resetCodeCacheKey(string $email): string
    {
        return 'password_reset_code_' . hash('sha256', $email);
    }

    private function storeResetCodeState(string $email, string $code, \DateTimeImmutable $expiresAt): void
    {
        $key = $this->resetCodeCacheKey($email);
        $state = [
            'codeHash' => password_hash($code, PASSWORD_BCRYPT),
            'expiresAt' => $expiresAt->getTimestamp(),
        ];

        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($state): array {
            $item->expiresAfter($this->codeTtlMinutes * 60);

            return $state;
        });
    }

    private function isCodeValid(string $email, string $code, \DateTimeImmutable $now): bool
    {
        $state = $this->cache->get($this->resetCodeCacheKey($email), static fn (ItemInterface $item): array => []);
        if (!is_array($state)) {
            return false;
        }

        $codeHash = $state['codeHash'] ?? null;
        $expiresAt = $state['expiresAt'] ?? null;

        if (!is_string($codeHash) || !is_int($expiresAt)) {
            return false;
        }

        if ($expiresAt <= $now->getTimestamp()) {
            $this->clearResetCodeState($email);

            return false;
        }

        return password_verify($code, $codeHash);
    }

    private function clearResetCodeState(string $email): void
    {
        $this->cache->delete($this->resetCodeCacheKey($email));
    }

    private function consumeResendAllowance(string $email, bool $resend): bool
    {
        $key = $this->resendAttemptsKey($email);

        if (!$resend) {
            $this->cache->delete($key);

            return true;
        }

        $attempts = $this->cache->get($key, static fn (ItemInterface $item): int => 0);
        if (!is_int($attempts)) {
            $attempts = 0;
        }

        if ($attempts >= $this->maxResendAttempts) {
            return false;
        }

        $nextAttempts = $attempts + 1;
        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($nextAttempts): int {
            $item->expiresAfter($this->codeTtlMinutes * 60);

            return $nextAttempts;
        });

        return true;
    }
}
