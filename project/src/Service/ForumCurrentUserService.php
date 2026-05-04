<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Model\CurrentUser;
use Symfony\Bundle\SecurityBundle\Security;

final class ForumCurrentUserService
{
    public function __construct(private Security $security)
    {
    }

    public function requireUser(): CurrentUser
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \RuntimeException('Authenticated user is required.');
        }

        $username = $user->getProfile()?->getUsername() ?? $user->getEmail();
        $roleLabel = strtolower($user->getRole());

        return new CurrentUser($user->getId(), $username, $roleLabel, $user->getRoles());
    }

    public function isAdmin(?CurrentUser $user = null): bool
    {
        $currentUser = $user ?? $this->requireUser();

        if (strtolower($currentUser->getRoleLabel()) === 'admin') {
            return true;
        }

        return in_array('ROLE_ADMIN', $currentUser->getRoles(), true);
    }

    public function isBackofficeUser(?CurrentUser $user = null): bool
    {
        $currentUser = $user ?? $this->requireUser();
        $roleLabel = strtolower($currentUser->getRoleLabel());

        if (in_array($roleLabel, ['admin', 'therapist'], true)) {
            return true;
        }

        $roles = $currentUser->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_THERAPIST', $roles, true);
    }
}
