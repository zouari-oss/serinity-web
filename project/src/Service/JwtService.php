<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final readonly class JwtService
{
    public function __construct(private string $appSecret)
    {
    }

    public function createAccessToken(User $user, int $ttlSeconds = 900): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedPayload, $this->appSecret, true);

        return sprintf('%s.%s.%s', $encodedHeader, $encodedPayload, $this->base64UrlEncode($signature));
    }

    /** @return array<string, mixed>|null */
    public function parseAndValidate(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $this->appSecret, true));

        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);
        if (!is_array($decoded) || !isset($decoded['exp']) || !is_numeric($decoded['exp'])) {
            return null;
        }

        if ((int) $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
