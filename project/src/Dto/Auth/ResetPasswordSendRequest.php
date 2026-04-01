<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class ResetPasswordSendRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';
}
