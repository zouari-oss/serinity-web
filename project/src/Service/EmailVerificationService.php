<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TokenGenerator $tokenGenerator,
        private MailerService $mailerService,
        private CacheInterface $cache,
        private int $codeTtlMinutes,
        private int $verifyRateLimitAttempts,
        private int $verifyRateLimitWindowSeconds,
        private int $maxResendAttempts,
    ) {
    }

    public function sendCodeForUser(User $user, bool $resend = false): ServiceResult
    {
        if ($user->getAccountStatus() === AccountStatus::ACTIVE->value) {
            return ServiceResult::success('Email already verified.');
        }

        $email = $this->normalizeEmail($user->getEmail());
        if (!$this->consumeResendAllowance($email, $resend)) {
            return ServiceResult::failure('You reached the maximum number of resends. Please sign in or register again.', [
                'error' => 'resend_limit_reached',
                'redirect' => '/login',
            ]);
        }

        $code = $this->tokenGenerator->generateNumericCode(6);
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', $this->codeTtlMinutes));
        $this->storeCodeState($email, $code, $expiresAt);

        $recipientName = $user->getProfile()?->getUsername() ?: 'there';
        $this->mailerService->sendTemplateHtmlEmail(
            to: $user->getEmail(),
            subject: 'Verify your Serinity email',
            template: 'emails/verify_email_code.html.twig',
            context: [
                'recipientName' => $recipientName,
                'code' => $code,
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
            ],
            plainText: sprintf(
                'Hello %s, your Serinity verification code is %s. It expires in %d minutes.',
                $recipientName,
                $code,
                $this->codeTtlMinutes,
            ),
        );

        return ServiceResult::success('Verification code sent successfully.', [
            'expiresIn' => $this->codeTtlMinutes * 60,
        ]);
    }

    public function resendCode(string $email, bool $resend): ServiceResult
    {
        if (!$resend) {
            return ServiceResult::failure('Invalid resend request.', [
                'error' => 'invalid_resend_request',
            ]);
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user instanceof User) {
            return ServiceResult::success('Verification code sent successfully.', [
                'expiresIn' => $this->codeTtlMinutes * 60,
            ]);
        }

        return $this->sendCodeForUser($user, true);
    }

    public function verifyCode(string $email, string $code): ServiceResult
    {
        $normalizedEmail = $this->normalizeEmail($email);
        if ($this->isVerificationLimited($normalizedEmail)) {
            return ServiceResult::failure('Too many invalid verification attempts. Please try again later.', [
                'error' => 'rate_limited',
            ]);
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if (!$user instanceof User) {
            $this->recordVerificationFailure($normalizedEmail);

            return $this->invalidOrExpiredCodeResult();
        }

        if ($user->getAccountStatus() === AccountStatus::ACTIVE->value) {
            return ServiceResult::failure('Email already verified. Please sign in.', [
                'error' => 'email_already_verified',
            ]);
        }

        if (!$this->isCodeValid($normalizedEmail, $code, new \DateTimeImmutable())) {
            $this->recordVerificationFailure($normalizedEmail);

            return $this->invalidOrExpiredCodeResult();
        }

        $this->clearCodeState($normalizedEmail);
        $this->resetVerificationFailures($normalizedEmail);
        $user
            ->setAccountStatus(AccountStatus::ACTIVE->value)
            ->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return ServiceResult::success('Email verified successfully.');
    }

    private function invalidOrExpiredCodeResult(): ServiceResult
    {
        return ServiceResult::failure('The verification code is invalid or has expired.', [
            'error' => 'invalid_or_expired_code',
        ]);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function codeStateKey(string $email): string
    {
        return 'email_verification_code_' . hash('sha256', $email);
    }

    private function resendAttemptsKey(string $email): string
    {
        return 'email_verification_resend_attempts_' . hash('sha256', $email);
    }

    private function verifyAttemptsKey(string $email): string
    {
        return 'email_verification_verify_attempts_' . hash('sha256', $email);
    }

    private function storeCodeState(string $email, string $code, \DateTimeImmutable $expiresAt): void
    {
        $key = $this->codeStateKey($email);
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
        $state = $this->cache->get($this->codeStateKey($email), static fn (ItemInterface $item): array => []);
        if (!is_array($state)) {
            return false;
        }

        $codeHash = $state['codeHash'] ?? null;
        $expiresAt = $state['expiresAt'] ?? null;

        if (!is_string($codeHash) || !is_int($expiresAt)) {
            return false;
        }

        if ($expiresAt <= $now->getTimestamp()) {
            $this->clearCodeState($email);

            return false;
        }

        return password_verify($code, $codeHash);
    }

    private function clearCodeState(string $email): void
    {
        $this->cache->delete($this->codeStateKey($email));
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

    private function isVerificationLimited(string $email): bool
    {
        $attempts = $this->cache->get($this->verifyAttemptsKey($email), static fn (ItemInterface $item): int => 0);

        return is_int($attempts) && $attempts >= $this->verifyRateLimitAttempts;
    }

    private function recordVerificationFailure(string $email): void
    {
        $key = $this->verifyAttemptsKey($email);
        $attempts = $this->cache->get($key, static fn (ItemInterface $item): int => 0);
        if (!is_int($attempts)) {
            $attempts = 0;
        }

        $this->cache->delete($key);
        $this->cache->get($key, function (ItemInterface $item) use ($attempts): int {
            $item->expiresAfter($this->verifyRateLimitWindowSeconds);

            return $attempts + 1;
        });
    }

    private function resetVerificationFailures(string $email): void
    {
        $this->cache->delete($this->verifyAttemptsKey($email));
    }
}
