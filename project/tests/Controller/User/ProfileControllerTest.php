<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use PHPUnit\Framework\TestCase;

final class ProfileControllerTest extends TestCase
{
    public function testProfileControllerExists(): void
    {
        self::assertTrue(class_exists(\App\Controller\User\ProfileController::class));
    }
}
