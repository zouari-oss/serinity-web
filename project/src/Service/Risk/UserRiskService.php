<?php

declare(strict_types=1);

namespace App\Service\Risk;

use App\Entity\AuthSession;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class UserRiskService
{
    private const RISK_SAFE = 'SAFE';
    private const RISK_MEDIUM = 'MEDIUM';
    private const RISK_DANGER = 'DANGER';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cachePool,
        private RequestStack $requestStack,
        private AuthSessionRepository $authSessionRepository,
        private AuditLogRepository $auditLogRepository,
        private LoggerInterface $logger,
        private string $riskDetectionUrl,
        private float $timeoutSeconds,
        private int $cacheTtlSeconds,
    ) {
    }

    /**
     * @return array{
     *   level: string|null,
     *   prediction: int|null,
     *   confidence: float|null,
     *   evaluatedAt: \DateTimeImmutable,
     *   error: string|null
     * }
     */
    public function evaluateAndStore(User $user, bool $forceRefresh = false): array
    {
        if (!$forceRefresh && !$this->shouldRefresh($user)) {
            return $this->fromUser($user);
        }

        $cacheKey = sprintf('user_risk_%s', preg_replace('/[^a-zA-Z0-9_-]/', '', $user->getId()));
        $cachedItem = $this->cachePool->getItem($cacheKey);

        if (!$forceRefresh && $cachedItem->isHit()) {
            $cached = $cachedItem->get();
            if (is_array($cached)) {
                return $this->applyAndReturn($user, $cached);
            }
        }

        try {
            $response = $this->httpClient->request('POST', $this->riskDetectionUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => $this->buildPayload($user),
                'timeout' => $this->timeoutSeconds,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('risk_api_non_200');
            }

            $payload = $response->toArray(false);
            if (!is_numeric($payload['risk_label'] ?? null)) {
                $this->logger->debug('Risk API invalid payload', [
                    'user_id' => $user->getId(),
                    'response' => $payload,
                ]);
                throw new \RuntimeException('risk_api_invalid_payload');
            }

            $prediction = (int) $payload['risk_label'];
            $confidence = is_numeric($payload['confidence'] ?? null)
                ? max(0.0, min(1.0, round((float) $payload['confidence'], 4)))
                : 0.0;

            $result = [
                'level' => $this->mapRiskLevel($prediction, $confidence),
                'prediction' => $prediction,
                'confidence' => $confidence,
                'evaluatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'error' => null,
            ];

            $cachedItem->set($result);
            $cachedItem->expiresAfter($this->cacheTtlSeconds);
            $this->cachePool->save($cachedItem);

            return $this->applyAndReturn($user, $result);
        } catch (\Throwable $exception) {
            $this->logger->error('User risk evaluation failed.', [
                'user_id' => $user->getId(),
                'error' => $exception->getMessage(),
            ]);

            return $this->applyAndReturn($user, [
                'level' => self::RISK_MEDIUM,
                'prediction' => null,
                'confidence' => null,
                'evaluatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'error' => 'risk_api_unavailable',
            ]);
        }
    }

    private function shouldRefresh(User $user): bool
    {
        return true;
    }

    /**
     * @return array{
     *   level: string|null,
     *   prediction: int|null,
     *   confidence: float|null,
     *   evaluatedAt: \DateTimeImmutable,
     *   error: string|null
     * }
     */
    private function fromUser(User $user): array
    {
        return [
            'level' => $user->getRiskLevel(),
            'prediction' => null,
            'confidence' => null,
            'evaluatedAt' => new \DateTimeImmutable(),
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array{
     *   level: string|null,
     *   prediction: int|null,
     *   confidence: float|null,
     *   evaluatedAt: \DateTimeImmutable,
     *   error: string|null
     * }
     */
    private function applyAndReturn(User $user, array $result): array
    {
        $evaluatedAtRaw = $result['evaluatedAt'] ?? null;
        $evaluatedAt = is_string($evaluatedAtRaw)
            ? new \DateTimeImmutable($evaluatedAtRaw)
            : new \DateTimeImmutable();

        $level = $this->normalizeLevel($result['level'] ?? null);
        $prediction = is_numeric($result['prediction'] ?? null) ? (int) $result['prediction'] : null;
        $confidence = is_numeric($result['confidence'] ?? null) ? (float) $result['confidence'] : null;
        $error = is_string($result['error'] ?? null) && trim((string) $result['error']) !== ''
            ? trim((string) $result['error'])
            : null;

        $user
            ->setRiskLevel($level)
            ->setUpdatedAt(new \DateTimeImmutable());

        return [
            'level' => $level,
            'prediction' => $prediction,
            'confidence' => $confidence,
            'evaluatedAt' => $evaluatedAt,
            'error' => $error,
        ];
    }

    /**
     * @return array{
     *   session_duration: int,
     *   is_revoked: int,
     *   ip_change_count: int,
     *   device_change_count: int,
     *   location_change: int,
     *   login_hour: int,
     *   is_night_login: int,
     *   os_variation: int
     * }
     */
    private function buildPayload(User $user): array
    {
        $sessions = $this->authSessionRepository->findRecentForUser($user, 20);
        $audits = $this->auditLogRepository->findRecentForUser($user, 20);
        $request = $this->requestStack->getCurrentRequest();
        $loginDate = $request?->server->getInt('REQUEST_TIME') > 0
            ? (new \DateTimeImmutable())->setTimestamp($request->server->getInt('REQUEST_TIME'))
            : new \DateTimeImmutable();
        $loginHour = (int) $loginDate->format('G');

        $ipAddresses = [];
        $deviceFingerprints = [];
        $locations = [];
        $osNames = [];

        foreach ($audits as $audit) {
            $ipAddresses[] = trim($audit->getPrivateIpAddress());
            $deviceFingerprints[] = trim((string) ($audit->getOsName() ?? '')) . '|' . trim((string) ($audit->getHostname() ?? ''));
            $locations[] = trim((string) ($audit->getLocation() ?? ''));

            $osName = trim((string) ($audit->getOsName() ?? ''));
            if ($osName !== '') {
                $osNames[] = $osName;
            }
        }

        return [
            'session_duration' => $this->estimateSessionDurationMinutes($sessions),
            'is_revoked' => isset($sessions[0]) && $sessions[0]->isRevoked() ? 1 : 0,
            'ip_change_count' => $this->countChanges($ipAddresses),
            'device_change_count' => $this->countChanges($deviceFingerprints),
            'location_change' => count(array_unique(array_filter($locations, static fn (string $value): bool => $value !== ''))) > 1 ? 1 : 0,
            'login_hour' => $loginHour,
            'is_night_login' => ($loginHour >= 22 || $loginHour < 6) ? 1 : 0,
            'os_variation' => count(array_unique($osNames)),
        ];
    }

    /**
     * @param list<AuthSession> $sessions
     */
    private function estimateSessionDurationMinutes(array $sessions): int
    {
        if ($sessions === []) {
            return 0;
        }

        $latest = $sessions[0];
        $durationSeconds = max(0, $latest->getExpiresAt()->getTimestamp() - $latest->getCreatedAt()->getTimestamp());

        return (int) floor($durationSeconds / 60);
    }

    /**
     * @param list<string> $values
     */
    private function countChanges(array $values): int
    {
        $normalized = array_values(array_filter(
            array_map(static fn (string $value): string => trim($value), $values),
            static fn (string $value): bool => $value !== '',
        ));

        if (count($normalized) < 2) {
            return 0;
        }

        $changes = 0;
        $previous = $normalized[0];

        foreach (array_slice($normalized, 1) as $value) {
            if ($value !== $previous) {
                ++$changes;
            }

            $previous = $value;
        }

        return $changes;
    }

    private function mapRiskLevel(int $prediction, float $confidence): ?string
    {
        return match ($prediction) {
            2 => self::RISK_DANGER,
            1 => self::RISK_MEDIUM,
            0 => self::RISK_SAFE,
            default => self::RISK_MEDIUM,
        };
    }

    private function normalizeLevel(mixed $level): ?string
    {
        if ($level === null) {
            return null;
        }

        $value = mb_strtoupper(trim((string) $level));

        return match ($value) {
            self::RISK_SAFE, self::RISK_MEDIUM, self::RISK_DANGER => $value,
            default => self::RISK_MEDIUM,
        };
    }
}
