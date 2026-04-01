<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case THERAPIST = 'THERAPIST';
    case PATIENT = 'PATIENT';

    public function toSymfonyRole(): string
    {
        return 'ROLE_' . $this->value;
    }
}
