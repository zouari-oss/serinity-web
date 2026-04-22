<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateUserRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            message: 'Password must include uppercase, lowercase, number, and special character.',
        )]
        public string $password,

        #[Assert\NotBlank(message: 'Confirm password is required')]
        public string $confirmPassword,

        #[Assert\NotBlank(message: 'Role is required')]
        #[Assert\Choice(choices: ['ADMIN', 'THERAPIST', 'PATIENT'], message: 'Invalid role')]
        public string $role,

        #[Assert\Choice(choices: ['ACTIVE', 'DISABLED', 'BANNED'], message: 'Invalid account status')]
        public string $accountStatus = 'ACTIVE',
    ) {
    }
}
