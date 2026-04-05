<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class RefreshRequest
{
    #[Assert\NotBlank]
    public string $refreshToken = '';
}
