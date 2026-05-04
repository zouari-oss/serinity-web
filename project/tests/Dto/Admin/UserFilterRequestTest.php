<?php

declare(strict_types=1);

namespace App\Tests\Dto\Admin;

use App\Dto\Admin\UserFilterRequest;
use PHPUnit\Framework\TestCase;

final class UserFilterRequestTest extends TestCase
{
    public function testToFiltersIncludesRiskLevelWhenProvided(): void
    {
        $dto = new UserFilterRequest(
            page: 1,
            limit: 20,
            email: 'john@example.com',
            role: 'PATIENT',
            accountStatus: 'ACTIVE',
            riskLevel: 'DANGER',
        );

        self::assertSame([
            'email' => 'john@example.com',
            'role' => 'PATIENT',
            'accountStatus' => 'ACTIVE',
            'riskLevel' => 'DANGER',
        ], $dto->toFilters());
    }
}

