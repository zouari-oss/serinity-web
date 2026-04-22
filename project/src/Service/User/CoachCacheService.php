<?php

declare(strict_types=1);

namespace App\Service\User;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final readonly class CoachCacheService
{
    public function __construct(
        private CacheItemPoolInterface $cachePool,
        private LoggerInterface $logger,
        private string $environment,
    ) {
    }

    /** @param array<string,mixed> $report */
    public function hashReport(array $report): string
    {
        $normalized = $this->normalize($report);
        $json = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }

    /** @return array<string,mixed>|null */
    public function get(string $reportHash): ?array
    {
        $cacheKey = $this->cacheKey($reportHash);
        if ($this->environment === 'dev') {
            $this->cachePool->deleteItem($cacheKey);
            $this->logger->info('Coach cache cleared in dev mode.', [
                'key' => $cacheKey,
            ]);
        }

        $item = $this->cachePool->getItem($cacheKey);
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return is_array($value) ? $value : null;
    }

    /** @param array<string,mixed> $insight */
    public function save(string $reportHash, array $insight, int $ttlSeconds = 86400): void
    {
        $item = $this->cachePool->getItem($this->cacheKey($reportHash));
        $item->set($insight);
        $item->expiresAfter($ttlSeconds);

        $this->cachePool->save($item);
    }

    private function cacheKey(string $reportHash): string
    {
        return 'exercise_coach_' . preg_replace('/[^a-f0-9]/', '', $reportHash);
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!$this->isList($value)) {
            ksort($value);
        }

        foreach ($value as $key => $nestedValue) {
            $value[$key] = $this->normalize($nestedValue);
        }

        return $value;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
