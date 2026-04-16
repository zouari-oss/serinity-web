<?php

declare(strict_types=1);

namespace App\Dto\Auth;

final class FaceEnrollRequest
{
    public ?string $image = null;
    /** @var array<int, mixed> */
    public array $tensor = [];
}
