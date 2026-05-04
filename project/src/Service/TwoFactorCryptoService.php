<?php

declare(strict_types=1);

namespace App\Service;

final readonly class TwoFactorCryptoService
{
    private const OPENSSL_CIPHER = 'aes-256-gcm';

    public function __construct(private string $appSecret)
    {
    }

    public function encryptSecret(string $secret): string
    {
        if ($this->canUseSodium()) {
            $nonce = random_bytes((int) \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($secret, $nonce, $this->secretKey());

            return 's1:' . base64_encode($nonce . $ciphertext);
        }

        if ($this->canUseOpenSsl()) {
            $ivLength = openssl_cipher_iv_length(self::OPENSSL_CIPHER);
            if ($ivLength === false || $ivLength <= 0) {
                throw new \RuntimeException('Invalid OpenSSL IV length for two-factor secret encryption.');
            }

            $iv = random_bytes($ivLength);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $secret,
                self::OPENSSL_CIPHER,
                $this->secretKey(),
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16,
            );
            if (!is_string($ciphertext) || $ciphertext === '' || !is_string($tag) || strlen($tag) !== 16) {
                throw new \RuntimeException('Unable to encrypt two-factor secret.');
            }

            return 'o1:' . base64_encode($iv . $tag . $ciphertext);
        }

        throw new \RuntimeException('No supported encryption backend is available for two-factor secret storage.');
    }

    public function decryptSecret(string $encryptedSecret): ?string
    {
        if (str_starts_with($encryptedSecret, 'o1:')) {
            return $this->decryptOpenSslPayload(substr($encryptedSecret, 3));
        }

        if (str_starts_with($encryptedSecret, 's1:')) {
            return $this->decryptSodiumPayload(substr($encryptedSecret, 3));
        }

        // Backward compatibility with pre-versioned sodium payloads.
        return $this->decryptSodiumPayload($encryptedSecret);
    }

    private function decryptSodiumPayload(string $encodedPayload): ?string
    {
        if (!$this->canUseSodium()) {
            return null;
        }

        $raw = base64_decode($encodedPayload, true);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $nonceLength = (int) \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($raw) <= $nonceLength) {
            return null;
        }

        $nonce = substr($raw, 0, $nonceLength);
        $ciphertext = substr($raw, $nonceLength);
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->secretKey());

        return is_string($decrypted) && $decrypted !== '' ? $decrypted : null;
    }

    private function decryptOpenSslPayload(string $encodedPayload): ?string
    {
        if (!$this->canUseOpenSsl()) {
            return null;
        }

        $raw = base64_decode($encodedPayload, true);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $ivLength = openssl_cipher_iv_length(self::OPENSSL_CIPHER);
        if ($ivLength === false || $ivLength <= 0 || strlen($raw) <= $ivLength + 16) {
            return null;
        }

        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, 16);
        $ciphertext = substr($raw, $ivLength + 16);
        $decrypted = openssl_decrypt(
            $ciphertext,
            self::OPENSSL_CIPHER,
            $this->secretKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        return is_string($decrypted) && $decrypted !== '' ? $decrypted : null;
    }

    private function secretKey(): string
    {
        return hash('sha256', $this->appSecret . '|totp-secret', true);
    }

    private function canUseSodium(): bool
    {
        return defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')
            && function_exists('sodium_crypto_secretbox')
            && function_exists('sodium_crypto_secretbox_open');
    }

    private function canUseOpenSsl(): bool
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }
}
