<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountStatus: string
{
    case ACTIVE = 'ACTIVE';
    case DISABLED = 'DISABLED';
    case BANNED = 'BANNED';
}
