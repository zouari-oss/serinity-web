<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
    ) {
    }

    public function translate(string $text, string $targetLanguage): string
    {
        if (trim($text) === '') {
            return '';
        }

        if ($this->apiKey === '') {
            return sprintf('[translation disabled] %s', $text);
        }
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey;

        $prompt = sprintf(
            "Translate the following text to %s. Only return the translated text:\n\n%s",
            $targetLanguage,
            $text
        );

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt,
                    ]],
                ]],
            ],
        ]);

        $status = $response->getStatusCode();
        $body = $response->getContent(false);

        if ($status < 200 || $status >= 300) {
            // try to extract an error message
            try {
                $err = json_decode($body, true);
                $message = $err['error']['message'] ?? $body;
            } catch (\Throwable $e) {
                $message = $body;
            }

            throw new \RuntimeException(sprintf('Translation API error (%d): %s', $status, $message));
        }

        $data = $response->toArray(false);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? $text;
    }
}
