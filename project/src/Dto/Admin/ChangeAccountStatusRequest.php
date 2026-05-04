<?php

declare(strict_types=1);

namespace App\Dto\Admin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for changing user account status.
 */
final readonly class ChangeAccountStatusRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Account status is required')]
        #[Assert\Choice(choices: ['ACTIVE', 'DISABLED', 'BANNED'], message: 'Invalid account status. Must be ACTIVE, DISABLED, or BANNED')]
        public string $accountStatus,
    ) {
    }
}
