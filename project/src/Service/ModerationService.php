<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ModerationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $toxicityApiUrl,
        private readonly float $toxicityTimeout,
    ) {
    }

    public function isToxic(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $toxicity = $this->checkToxicityApi($text);
        if ($toxicity !== null) {
            return $toxicity;
        }

        return $this->checkProfanity($text);
    }

    private function checkToxicityApi(string $text): ?bool
    {
        $baseUrl = trim($this->toxicityApiUrl);
        if ($baseUrl === '') {
            return null;
        }

        $url = rtrim($baseUrl, '/');
        if (!str_ends_with($url, '/predict')) {
            $url .= '/predict';
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => ['text' => $text],
                'timeout' => $this->toxicityTimeout,
            ]);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                return null;
            }

            if (array_key_exists('is_toxic', $payload)) {
                return (bool) $payload['is_toxic'];
            }

            if (array_key_exists('label', $payload)) {
                return $payload['label'] === 'toxic';
            }

            if (array_key_exists('toxic_probability', $payload) && array_key_exists('threshold', $payload)) {
                return (float) $payload['toxic_probability'] >= (float) $payload['threshold'];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function checkProfanity(string $text): bool
    {
        try {
            $response = $this->httpClient->request('GET', 'https://www.purgomalum.com/service/containsprofanity', [
                'query' => ['text' => $text],
                'timeout' => $this->toxicityTimeout,
            ]);

            return trim($response->getContent()) === 'true';
        } catch (\Throwable) {
            return false;
        }
    }
}
