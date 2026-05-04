<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SummarizationService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function summarize(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        try {
            $response = $this->httpClient->request('POST', 'http://localhost:5000/summarize', [
                'json' => [
                    'text' => $text,
                    'max_length' => 80,
                    'min_length' => 20,
                ],
            ]);
            $data = $response->toArray(false);

            return $data['summary'] ?? mb_substr($text, 0, 240).'...';
        } catch (\Throwable) {
            return mb_substr($text, 0, 240).'...';
        }
    }
}
