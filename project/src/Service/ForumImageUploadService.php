<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ForumImageUploadService
{
    public function __construct(
        private string $uploadDir,
        private HttpClientInterface $httpClient,
        private string $imgbbApiKey = '',
    ) {
    }

    public function upload(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        if ($this->imgbbApiKey !== '') {
            try {
                $path = $file->getRealPath();
                if ($path !== null && file_exists($path)) {
                    $contents = file_get_contents($path);
                    if ($contents !== false) {
                        $base64 = base64_encode($contents);

                        $response = $this->httpClient->request('POST', 'https://api.imgbb.com/1/upload', [
                            'body' => [
                                'key' => $this->imgbbApiKey,
                                'image' => $base64,
                            ],
                            'timeout' => 30,
                        ]);

                        $status = $response->getStatusCode();
                        if ($status >= 200 && $status < 300) {
                            $data = $response->toArray(false);
                            if (isset($data['data']) && is_array($data['data'])) {
                                $imgUrl = $data['data']['url'] ?? $data['data']['display_url'] ?? null;
                                if (is_string($imgUrl) && $imgUrl !== '') {
                                    return $imgUrl;
                                }
                            }
                        }
                    }
                }
            } catch (TransportExceptionInterface) {
            } catch (\Throwable) {
            }
        }

        $filename = uniqid('thread_', true).'.'.$file->guessExtension();
        $file->move($this->uploadDir, $filename);

        return '/uploads/'.$filename;
    }
}
