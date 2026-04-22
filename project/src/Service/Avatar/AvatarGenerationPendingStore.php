<?php

declare(strict_types=1);

namespace App\Service\Avatar;

use Psr\Cache\CacheItemPoolInterface;

final readonly class AvatarGenerationPendingStore
{
    public function __construct(
        private CacheItemPoolInterface $cachePool,
        private int $ttlSeconds,
    ) {
    }

    public function markPending(string $userId): void
    {
        $item = $this->cachePool->getItem($this->cacheKey($userId));
        $item->set(true);
        $item->expiresAfter($this->ttlSeconds);
        $this->cachePool->save($item);
    }

    public function isPending(string $userId): bool
    {
        $item = $this->cachePool->getItem($this->cacheKey($userId));

        return $item->isHit() && $item->get() === true;
    }

    public function clear(string $userId): void
    {
        $this->cachePool->deleteItem($this->cacheKey($userId));
    }

    private function cacheKey(string $userId): string
    {
        return 'avatar_generation_pending_' . hash('sha256', $userId);
    }
}
