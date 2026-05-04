<?php

namespace App\Tests\Service\Sleep;

use App\Service\Sleep\SleepAdviceService;
use PHPUnit\Framework\TestCase;
final class SleepAdviceServiceTest extends TestCase
{
    private SleepAdviceService $service;

    protected function setUp(): void
    {
        $this->service = new SleepAdviceService();
    }

    public function testNullWeather(): void
    {
        $result = $this->service->analyze(null);

        self::assertSame('Indisponible', $result['niveau']);
    }

    public function testGoodWeather(): void
    {
        $result = $this->service->analyze([
            'current' => [
                'temp' => 20,
                'humidity' => 50,
                'wind_speed' => 3,
                'desc' => 'clear sky',
            ]
        ]);

        self::assertSame('Excellent', $result['niveau']);
        self::assertSame(100, $result['score']);
    }

    public function testBadWeather(): void
    {
        $result = $this->service->analyze([
            'current' => [
                'temp' => 30,
                'humidity' => 90,
                'wind_speed' => 20,
                'desc' => 'storm',
            ]
        ]);

        self::assertLessThan(100, $result['score']);
        self::assertNotEmpty($result['conseils']);
    }
}