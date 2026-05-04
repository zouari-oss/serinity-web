<?php

namespace App\Enum;

enum ThreadStatus: string
{
    case OPEN = 'open';
    case LOCKED = 'locked';
    case ARCHIVED = 'archived';
    case HIDDEN = 'hidden';
}
