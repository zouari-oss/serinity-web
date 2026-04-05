<?php

declare(strict_types=1);

namespace App\Exception;

final class AdminOperationException extends \RuntimeException
{
    public static function invalidStateTransition(string $message): self
    {
        return new self($message);
    }
}
