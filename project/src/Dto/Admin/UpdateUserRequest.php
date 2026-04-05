<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for updating user details by admin.
 */
final readonly class UpdateUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $email,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(choices: ['ADMIN', 'THERAPIST', 'PATIENT'], message: 'Invalid role')]
        public string $role,

        #[Assert\Choice(choices: ['ACTIVE', 'DISABLED'], message: 'Invalid account status')]
        public ?string $accountStatus = null,

        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
        public ?string $password = null,
    ) {
    }
}
