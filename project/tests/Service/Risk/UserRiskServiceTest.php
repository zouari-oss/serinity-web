<?php

declare(strict_types=1);

namespace App\Tests\Service\Risk;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Service\Risk\UserRiskService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class UserRiskServiceTest extends TestCase
{
    public function testEvaluateAndStoreMapsDangerPrediction(): void
    {
        $user = $this->buildUser();
        $service = $this->buildService([
            'prediction' => 2,
            'confidence' => 0.91,
        ]);

        $result = $service->evaluateAndStore($user, true);

        self::assertSame('DANGER', $result['level']);
        self::assertSame(2, $result['prediction']);
        self::assertSame(0.91, $result['confidence']);
        self::assertNull($result['error']);
        self::assertSame('DANGER', $user->getRiskLevel());
    }

    public function testEvaluateAndStoreUsesHybridConfidenceRule(): void
    {
        $user = $this->buildUser();
        $service = $this->buildService([
            'prediction' => 0,
            'confidence' => 0.72,
        ]);

        $result = $service->evaluateAndStore($user, true);

        self::assertSame('MEDIUM', $result['level']);
        self::assertSame(0, $result['prediction']);
        self::assertSame(0.72, $result['confidence']);
    }

    public function testEvaluateAndStoreFallsBackToMediumOnApiFailure(): void
    {
        $user = $this->buildUser();
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(new \RuntimeException('network_down'));

        $service = $this->buildService(client: $client);
        $result = $service->evaluateAndStore($user, true);

        self::assertSame('MEDIUM', $result['level']);
        self::assertSame('risk_api_unavailable', $result['error']);
        self::assertSame('MEDIUM', $user->getRiskLevel());
        self::assertNull($user->getRiskPrediction());
    }

    /**
     * @param array{prediction?: int, confidence?: float}|null $apiPayload
     */
    private function buildService(?array $apiPayload = null, ?HttpClientInterface $client = null): UserRiskService
    {
        if (!$client instanceof HttpClientInterface) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn(200);
            $response->method('toArray')->willReturn([
                'prediction' => $apiPayload['prediction'] ?? 1,
                'confidence' => $apiPayload['confidence'] ?? 0.87,
            ]);

            $client = $this->createMock(HttpClientInterface::class);
            $client->method('request')->willReturn($response);
        }

        $auditRepo = $this->createMock(AuditLogRepository::class);
        $auditRepo->method('findRecentForUser')->willReturn([]);

        $sessionRepo = $this->createMock(AuthSessionRepository::class);
        $sessionRepo->method('findRecentForUser')->willReturn([]);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/auth/login'));

        return new UserRiskService(
            $client,
            new ArrayAdapter(),
            $requestStack,
            $sessionRepo,
            $auditRepo,
            new NullLogger(),
            'https://user-risk-detection-api.vercel.app/api/v1/predict',
            5.0,
            1800,
        );
    }

    private function buildUser(): User
    {
        return (new User())
            ->setId('user-risk-test')
            ->setEmail('risk@example.com')
            ->setPassword('secret')
            ->setRole('PATIENT')
            ->setPresenceStatus('ONLINE')
            ->setAccountStatus('ACTIVE')
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
    }
}

