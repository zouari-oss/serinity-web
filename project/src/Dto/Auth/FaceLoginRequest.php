<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final class FaceLoginRequest
{
    public ?string $email = null;
    public ?string $image = null;
    /** @var array<int, mixed> */
    public array $tensor = [];
    public bool $rememberMe = false;
}
