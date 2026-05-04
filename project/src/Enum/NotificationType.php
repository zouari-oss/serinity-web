<?php

namespace App\Enum;

enum NotificationType: string
{
    case LIKE = 'like';
    case DISLIKE = 'dislike';
    case FOLLOW = 'follow';
    case COMMENT = 'comment';
}
