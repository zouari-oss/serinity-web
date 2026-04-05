<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for filtering users in admin panel.
 */
final readonly class UserFilterRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $page = 1,

        #[Assert\Positive]
        #[Assert\LessThanOrEqual(100)]
        public int $limit = 20,

        #[Assert\Email(message: 'Invalid email format')]
        public ?string $email = null,

        #[Assert\Choice(choices: ['ADMIN', 'THERAPIST', 'PATIENT'], message: 'Invalid role')]
        public ?string $role = null,

        #[Assert\Choice(choices: ['ACTIVE', 'DISABLED'], message: 'Invalid account status')]
        public ?string $accountStatus = null,
    ) {
    }

    /**
     * Convert to repository filter array.
     */
    public function toFilters(): array
    {
        $filters = [];

        if ($this->email !== null) {
            $filters['email'] = $this->email;
        }

        if ($this->role !== null) {
            $filters['role'] = $this->role;
        }

        if ($this->accountStatus !== null) {
            $filters['accountStatus'] = $this->accountStatus;
        }

        return $filters;
    }
}
