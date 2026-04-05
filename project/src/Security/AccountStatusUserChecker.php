<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\AccountStatus;
use App\Exception\DisabledAccountException;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UserChecker to enforce account status rules.
 * 
 * DISABLED users are blocked from authentication (considered "banned").
 * This integrates with Symfony's security layer to enforce status at login.
 */
final class AccountStatusUserChecker implements UserCheckerInterface
{
    /**
     * Check user account status before authentication.
     * 
     * @throws AccountStatusException if user account is DISABLED
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // DISABLED status = banned/blocked from login
        if ($user->getAccountStatus() === AccountStatus::DISABLED->value) {
            throw new DisabledAccountException();
        }
    }

    /**
     * Check user account status after authentication.
     * 
     * Currently no post-authentication checks needed.
     * Can be extended in the future for session-based restrictions.
     */
    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void
    {
        // No post-auth checks needed currently
        // Could be used for:
        // - Session-based restrictions
        // - Additional permission checks
        // - Account verification status
    }
}
