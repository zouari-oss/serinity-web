<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class SignInRequest
{
    #[Assert\NotBlank]
    public string $usernameOrEmail = '';

    #[Assert\NotBlank]
    public string $password = '';
}
