<?php

namespace App\Tests\Controller;

use App\Controller\ConsultationDashboardController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConsultationDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['OPENROUTER_API_KEY'] = 'test-api-key';
        $_SERVER['OPENROUTER_API_KEY'] = 'test-api-key';
    }

    /**
     * @return array{level: string, title: string, reason?: string}
     */
    private function callAnalyseEmergency(
        string $motif,
        string $description,
        MockHttpClient $httpClient
    ): array {
        $controller = new ConsultationDashboardController();
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('analyseEmergency');

        /** @var array{level: string, title: string, reason?: string} $result */
        $result = $method->invoke($controller, $motif, $description, $httpClient);

        return $result;
    }

    public function testAnalyseEmergencyReturnsValidAiResult(): void
    {
        $aiJson = [
            'level' => 'emergency',
            'title' => 'Chest pain emergency',
            'reason' => 'Chest pain can indicate a serious emergency.',
        ];

        $openRouterResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode($aiJson, JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ];

        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode($openRouterResponse, JSON_THROW_ON_ERROR),
                [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-type' => 'application/json',
                    ],
                ]
            ),
        ]);

        $result = $this->callAnalyseEmergency(
            'Chest pain',
            'Patient has strong chest pain and breathing difficulty',
            $httpClient
        );

        $this->assertSame('emergency', $result['level']);
        $this->assertSame('Chest pain emergency', $result['title']);
        $this->assertSame('Chest pain can indicate a serious emergency.', $result['reason']);
    }
}
