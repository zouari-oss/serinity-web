<?php

declare(strict_types=1);

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

final class ResendVerificationEmailRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    public bool $resend = true;
}
