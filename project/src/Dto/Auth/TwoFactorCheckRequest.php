<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class TwoFactorCheckRequest
{
    #[Assert\NotBlank]
    public string $challengeId = '';

    #[Assert\NotBlank]
    public string $code = '';
}
