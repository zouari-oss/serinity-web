<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;

final class FaceLoginRateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
    }

    public function isLimited(string $key): bool
    {
        $cacheKey = $this->cacheKey($key);
        $item = $this->cachePool->getItem($cacheKey);
        $state = $this->normalizeState($item->isHit() ? $item->get() : null);

        return (int) $state['count'] >= $this->maxAttempts;
    }

    public function recordFailure(string $key): void
    {
        $cacheKey = $this->cacheKey($key);
        $item = $this->cachePool->getItem($cacheKey);
        $state = $this->normalizeState($item->isHit() ? $item->get() : null);
        $state['count'] = (int) $state['count'] + 1;
        $item->set($state);
        $item->expiresAfter($this->windowSeconds);
        $this->cachePool->save($item);
    }

    /**
     * @param array<string,mixed>|mixed $state
     * @return array{count:int,resetAt:int}
     */
    private function normalizeState(mixed $state): array
    {
        if (!is_array($state) || !isset($state['count'], $state['resetAt'])) {
            return [
                'count' => 0,
                'resetAt' => time() + $this->windowSeconds,
            ];
        }

        if ((int) $state['resetAt'] <= time()) {
            return [
                'count' => 0,
                'resetAt' => time() + $this->windowSeconds,
            ];
        }

        return [
            'count' => (int) $state['count'],
            'resetAt' => (int) $state['resetAt'],
        ];
    }

    public function reset(string $key): void
    {
        $this->cachePool->deleteItem($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return 'face_login_rl_' . hash('sha256', $key);
    }
}
