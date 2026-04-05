<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class DisabledAccountException extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('Your account has been disabled. Please contact support for assistance.');
    }
}
