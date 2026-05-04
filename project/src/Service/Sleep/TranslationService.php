<?php

namespace App\Service\Sleep;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TranslationService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function translate(string $text, string $targetLang, string $sourceLang = 'fr'): string
    {
        $text = trim($text);

        if ($text === '') {
            return $text;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://translate.googleapis.com/translate_a/single',
                [
                    'query' => [
                        'client' => 'gtx',
                        'sl'     => $sourceLang,
                        'tl'     => $targetLang,
                        'dt'     => 't',
                        'q'      => mb_substr($text, 0, 500),
                    ],
                    'timeout' => 8,
                ]
            );

            /** @var array<int, mixed> $data */
            $data = $response->toArray(false);

            // Google retourne [[[translated, original, ...], ...], ...]
            $translated = '';
            foreach (($data[0] ?? []) as $chunk) {
                /** @var array<int, mixed> $chunk */
                $translated .= (string) ($chunk[0] ?? '');
            }

            return trim($translated) !== '' ? trim($translated) : $text;
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /**
     * @param array<int, string> $texts
     * @return array<int, string>
     */
    public function translateBatch(array $texts, string $targetLang, string $sourceLang = 'fr'): array
    {
        /** @var array<int, ResponseInterface> $responses */
        $responses = [];

        /** @var array<int, string> $results */
        $results = [];

        foreach ($texts as $index => $text) {
            $text = trim((string) $text);

            if ($text === '' || mb_strlen($text) < 2) {
                $results[$index] = $text;
                continue;
            }

            $responses[$index] = $this->httpClient->request(
                'GET',
                'https://translate.googleapis.com/translate_a/single',
                [
                    'query' => [
                        'client' => 'gtx',
                        'sl'     => $sourceLang,
                        'tl'     => $targetLang,
                        'dt'     => 't',
                        'q'      => mb_substr($text, 0, 500),
                    ],
                    'timeout' => 8,
                ]
            );
        }

        foreach ($responses as $index => $response) {
            try {
                /** @var array<int, mixed> $data */
                $data = $response->toArray(false);

                $translated = '';
                foreach (($data[0] ?? []) as $chunk) {
                    /** @var array<int, mixed> $chunk */
                    $translated .= (string) ($chunk[0] ?? '');
                }

                $results[$index] = trim($translated) !== '' ? trim($translated) : $texts[$index];
            } catch (\Throwable $e) {
                $results[$index] = $texts[$index];
            }
        }

        ksort($results);

        return array_values($results);
    }
}