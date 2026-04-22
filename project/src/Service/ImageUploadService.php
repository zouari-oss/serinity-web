<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ImageUploadService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $imageRequestUrl,
        private string $imageApiKey,
    ) {
    }

    public function uploadProfileImage(UploadedFile $file): string
    {
        $binary = file_get_contents($file->getPathname());
        if ($binary === false) {
            throw new \RuntimeException('Unable to read uploaded image.');
        }

        return $this->uploadBase64Image(base64_encode($binary));
    }

    public function uploadBase64Image(string $base64Image): string
    {
        $normalizedSource = trim($base64Image);
        if (str_starts_with($normalizedSource, 'data:image/')) {
            $parts = explode(',', $normalizedSource, 2);
            $normalizedSource = $parts[1] ?? '';
        }
        if ($normalizedSource === '') {
            throw new \RuntimeException('Image payload is empty.');
        }

        $response = $this->httpClient->request('POST', $this->imageRequestUrl, [
            'body' => [
                'key' => $this->imageApiKey,
                'source' => $normalizedSource,
                'format' => 'json',
            ],
        ]);

        $payload = $response->toArray(false);
        $imageUrl = $payload['image']['url']
            ?? $payload['data']['display_url']
            ?? $payload['data']['url']
            ?? $payload['url']
            ?? null;

        if (!is_string($imageUrl) || $imageUrl === '') {
            throw new \RuntimeException('Image upload failed.');
        }

        return $imageUrl;
    }
}
