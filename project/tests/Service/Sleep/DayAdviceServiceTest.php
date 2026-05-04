<?php

namespace App\Tests\Service\Sleep;

use App\Service\Sleep\DayAdviceService;
use PHPUnit\Framework\TestCase;
final class DayAdviceServiceTest extends TestCase
{
    private DayAdviceService $service;

    protected function setUp(): void
    {
        $this->service = new DayAdviceService();
    }

    public function testPositiveMood(): void
    {
        $result = $this->service->getAdvice(
            ['sentiment' => 'positif', 'confiance' => 80],
            ['humeur' => '😄 Joyeux', 'type_reve' => 'Normal']
        );

        self::assertSame('success', $result['classe']);
        self::assertSame('😊', $result['emoji']);
        self::assertNotEmpty($result['conseils']);
    }

    public function testNegativeMood(): void
    {
        $result = $this->service->getAdvice(
            ['sentiment' => 'négatif', 'confiance' => 90],
            ['humeur' => '😨 Effrayé', 'type_reve' => 'Normal']
        );

        self::assertSame('danger', $result['classe']);
    }

    public function testNightmareAddsAdvice(): void
    {
        $result = $this->service->getAdvice(
            ['sentiment' => 'négatif', 'confiance' => 90],
            ['humeur' => '😨 Effrayé', 'type_reve' => 'Cauchemar']
        );

        self::assertStringContainsString('Cauchemar', implode(' ', $result['conseils']));
    }

    public function testEmptyData(): void
    {
        $result = $this->service->getAdvice([], []);

        self::assertSame('secondary', $result['classe']);
    }
}