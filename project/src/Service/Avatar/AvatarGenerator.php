<?php

declare(strict_types=1);

namespace App\Service\Avatar;

use App\Service\ImageUploadService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AvatarGenerator
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ImageUploadService $imageUploadService,
        private string $img2imgApiUrl,
        private int $timeoutSeconds,
        private string $defaultPrompt,
        private string $defaultNegativePrompt,
        private float $defaultStrength,
    ) {
    }

    public function generateFromProfileImageUrl(string $imageUrl): string
    {
        $normalizedImageUrl = $this->normalizeSourceImageUrl($imageUrl);

        try {
            $response = $this->httpClient->request('POST', $this->img2imgApiUrl, [
                'json' => [
                    'image_url' => $normalizedImageUrl,
                    'prompt' => $this->defaultPrompt,
                    'negative_prompt' => $this->defaultNegativePrompt,
                    'strength' => $this->defaultStrength,
                ],
                'timeout' => $this->timeoutSeconds,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Avatar service is currently unavailable.');
            }

            $payload = $response->toArray(false);
            $image = $payload['image'] ?? null;
            if (is_string($image) && trim($image) !== '') {
                return $this->imageUploadService->uploadBase64Image($image);
            }

            $hostedImageUrl = $payload['image_url']
                ?? $payload['url']
                ?? null;
            if (!is_string($hostedImageUrl) || trim($hostedImageUrl) === '') {
                throw new \RuntimeException('Avatar generation did not return an image URL.');
            }

            return $this->normalizeSourceImageUrl($hostedImageUrl);
        } catch (TransportExceptionInterface) {
            throw new \RuntimeException('Avatar generation timed out or failed to connect.');
        } catch (DecodingExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface) {
            throw new \RuntimeException('Avatar generation failed due to an invalid upstream response.');
        }
    }

    private function normalizeSourceImageUrl(string $imageUrl): string
    {
        $url = trim($imageUrl);
        if ($url === '') {
            throw new \InvalidArgumentException('Profile image URL is required.');
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new \InvalidArgumentException('Profile image URL is malformed.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only HTTP and HTTPS profile image URLs are allowed.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            throw new \InvalidArgumentException('Profile image URL host is missing.');
        }

        foreach ($this->resolveHostIps($host) as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new \InvalidArgumentException('Profile image URL host is not allowed.');
            }
        }

        return $url;
    }

    /**
     * @return list<string>
     */
    private function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $resolved = [];
        $ipv4 = gethostbynamel($host);
        if (is_array($ipv4)) {
            $resolved = array_merge($resolved, $ipv4);
        }

        $records = dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ipv6 = $record['ipv6'] ?? null;
                if (is_string($ipv6) && $ipv6 !== '') {
                    $resolved[] = $ipv6;
                }
            }
        }

        $resolved = array_values(array_unique($resolved));
        if ($resolved === []) {
            throw new \InvalidArgumentException('Unable to resolve profile image URL host.');
        }

        return $resolved;
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

}
