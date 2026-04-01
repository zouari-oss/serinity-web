<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case USER_SIGN_UP = 'USER_SIGN_UP';
    case USER_LOGIN = 'USER_LOGIN';
    case USER_FACE_LOGIN = 'USER_FACE_LOGIN';
    case USER_LOGOUT = 'USER_LOGOUT';
    case USER_LOGIN_FAILED = 'USER_LOGIN_FAILED';
    case TOKEN_REFRESH = 'TOKEN_REFRESH';
    case SESSION_REVOKED = 'SESSION_REVOKED';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case USER_UPDATED = 'USER_UPDATED';
    case USER_DELETED = 'USER_DELETED';
    case ROLE_CHANGED = 'ROLE_CHANGED';
}
