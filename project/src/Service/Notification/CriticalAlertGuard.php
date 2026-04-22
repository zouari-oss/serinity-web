<?php

declare(strict_types=1);

namespace App\Service\Notification;

use Psr\Cache\CacheItemPoolInterface;

final readonly class CriticalAlertGuard
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    public function shouldSend(string $userId, int $cooldownSeconds): bool
    {
        $item = $this->cache->getItem($this->key($userId));

        if ($item->isHit()) {
            return false;
        }

        $item->set(true);
        $item->expiresAfter($cooldownSeconds);
        $this->cache->save($item);

        return true;
    }

    public function clear(string $userId): void
    {
        $this->cache->deleteItem($this->key($userId));
    }

    private function key(string $userId): string
    {
        return 'critical_alert_sent_user_' . $userId;
    }
}
