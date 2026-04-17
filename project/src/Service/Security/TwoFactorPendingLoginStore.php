<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;

final class TwoFactorPendingLoginStore
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
        private readonly int $ttlSeconds,
    ) {
    }

    public function create(string $userId, bool $rememberMe, string $fingerprint): array
    {
        $challengeId = $this->generateChallengeId();
        $item = $this->cachePool->getItem($this->cacheKey($challengeId));
        $item->set([
            'userId' => $userId,
            'rememberMe' => $rememberMe,
            'fingerprint' => $fingerprint,
            'createdAt' => time(),
        ]);
        $item->expiresAfter($this->ttlSeconds);
        $this->cachePool->save($item);

        return [
            'challengeId' => $challengeId,
            'expiresIn' => $this->ttlSeconds,
        ];
    }

    /** @return array{userId:string,rememberMe:bool,fingerprint:string,createdAt:int}|null */
    public function get(string $challengeId): ?array
    {
        $item = $this->cachePool->getItem($this->cacheKey($challengeId));
        if (!$item->isHit()) {
            return null;
        }

        $payload = $item->get();
        if (!is_array($payload)) {
            return null;
        }

        if (
            !isset($payload['userId'], $payload['rememberMe'], $payload['fingerprint'], $payload['createdAt'])
            || !is_string($payload['userId'])
            || !is_bool($payload['rememberMe'])
            || !is_string($payload['fingerprint'])
            || !is_int($payload['createdAt'])
        ) {
            return null;
        }

        return $payload;
    }

    public function consume(string $challengeId): ?array
    {
        $payload = $this->get($challengeId);
        if ($payload === null) {
            return null;
        }

        $this->cachePool->deleteItem($this->cacheKey($challengeId));

        return $payload;
    }

    private function cacheKey(string $challengeId): string
    {
        return 'two_factor_challenge_' . hash('sha256', $challengeId);
    }

    private function generateChallengeId(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
}
