<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class BannedAccountException extends CustomUserMessageAccountStatusException
{
    public function __construct(?int $remainingSeconds = null)
    {
        if ($remainingSeconds === null || $remainingSeconds <= 0) {
            parent::__construct('Your account is temporarily banned. Please try again later.');

            return;
        }

        $remainingMinutes = max(1, (int) ceil($remainingSeconds / 60));
        parent::__construct(sprintf(
            'Your account is temporarily banned. Please try again in %d minute(s).',
            $remainingMinutes,
        ));
    }
}
