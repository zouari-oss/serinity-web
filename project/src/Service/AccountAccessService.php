<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Exception\BannedAccountException;
use App\Exception\DisabledAccountException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AccountAccessService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $banDurationMinutes,
    ) {
    }

    public function checkLoginEligibility(User $user): ?ServiceResult
    {
        $status = $user->getAccountStatus();
        if ($status === AccountStatus::ACTIVE->value) {
            return null;
        }

        if ($status === AccountStatus::BANNED->value) {
            if ($this->liftBanIfExpired($user)) {
                return null;
            }

            $remainingSeconds = $this->remainingBanSeconds($user);

            return ServiceResult::failure('Your account is temporarily banned. Please try again later.', [
                'error' => 'account_banned',
                'remainingSeconds' => $remainingSeconds,
            ]);
        }

        return ServiceResult::failure('Your account has been disabled. Please contact support for assistance.', [
            'error' => 'account_disabled',
        ]);
    }

    public function assertCanAuthenticate(User $user): void
    {
        $status = $user->getAccountStatus();
        if ($status === AccountStatus::ACTIVE->value) {
            return;
        }

        if ($status === AccountStatus::BANNED->value) {
            if ($this->liftBanIfExpired($user)) {
                return;
            }

            throw new BannedAccountException($this->remainingBanSeconds($user));
        }

        throw new DisabledAccountException();
    }

    private function liftBanIfExpired(User $user): bool
    {
        if ($user->getAccountStatus() !== AccountStatus::BANNED->value) {
            return false;
        }

        $now = new \DateTimeImmutable();
        if ($this->banEndsAt($user) > $now) {
            return false;
        }

        $user
            ->setAccountStatus(AccountStatus::ACTIVE->value)
            ->setUpdatedAt($now);
        $this->entityManager->flush();

        return true;
    }

    private function remainingBanSeconds(User $user): int
    {
        $remaining = $this->banEndsAt($user)->getTimestamp() - time();

        return max(1, $remaining);
    }

    private function banEndsAt(User $user): \DateTimeImmutable
    {
        return $user->getUpdatedAt()->modify(sprintf('+%d minutes', $this->banDurationMinutes));
    }
}
