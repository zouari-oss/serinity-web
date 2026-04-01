<?php

declare(strict_types=1);

namespace App\Enum;

enum PresenceStatus: string
{
    case ONLINE = 'ONLINE';
    case OFFLINE = 'OFFLINE';
}
