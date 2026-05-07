<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase
{
    public function testDashboardControllerExists(): void
    {
        self::assertTrue(class_exists(\App\Controller\User\DashboardController::class));
    }
}
