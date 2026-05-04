<?php

declare(strict_types=1);

namespace App\Model;

final class CurrentUser
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string $id,
        private readonly string $username,
        private readonly string $roleLabel,
        private readonly array $roles,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getRoleLabel(): string
    {
        return $this->roleLabel;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
