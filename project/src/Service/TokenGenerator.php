<?php

declare(strict_types=1);

namespace App\Service;

final class TokenGenerator
{
    public function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function generateResetCode(int $length = 6): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < $length; ++$i) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }
}
