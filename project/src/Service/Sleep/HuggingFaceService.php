<?php

namespace App\Service\Sleep;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HuggingFaceService
{
    private const MODEL = 'cardiffnlp/twitter-xlm-roberta-base-sentiment';
    private const ENDPOINT = 'https://router.huggingface.co/hf-inference/models/' . self::MODEL;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    /**
     * @return array{
     *     sentiment: string,
     *     confiance: int,
     *     tous: array<int, array{label: string, score: int}>,
     *     phrase: string,
     *     humeur_originale: string,
     *     humeur_normalisee: string,
     *     source: string,
     *     erreur?: string
     * }
     */
    public function analyzeHumeur(string $humeur): array
    {
        $humeurNormalisee = $this->normalizeHumeur($humeur);
        $phrase = "Je me suis réveillé en me sentant {$humeurNormalisee} après mon rêve cette nuit.";

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($this->apiKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $phrase,
                    'options' => [
                        'wait_for_model' => true,
                        'use_cache' => false,
                    ],
                ],
                'timeout' => 100,
                'verify_peer' => false,
                'verify_host' => false,
            ]);

            $statusCode = $response->getStatusCode();
            $raw = $response->getContent(false);

            if ($statusCode >= 400) {
                throw new \RuntimeException("Hugging Face HTTP {$statusCode} : {$raw}");
            }

            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            /** @var mixed $data */


            /** @var array<int, array<string, mixed>> $results */
            $results = [];

            if (isset($data[0]) && is_array($data[0])) {
                $results = $data[0];
            } elseif (isset($data['label'], $data['score'])) {
                $results = [$data];
            }

            if (empty($results)) {
                throw new \RuntimeException('Réponse Hugging Face vide ou invalide : ' . $raw);
            }

            /** @var array<int, array{label?: string, score?: float|int}> $results */
            usort($results, fn(array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

            $top = $results[0] ?? ['label' => 'neutral', 'score' => 0.5];

            return [
                'sentiment' => $this->mapSentiment((string) ($top['label'] ?? 'neutral')),
                'confiance' => (int) round(((float) ($top['score'] ?? 0)) * 100),
                'tous' => array_map(fn(array $r): array => [
                    'label' => $this->mapSentiment((string) ($r['label'] ?? 'neutral')),
                    'score' => (int) round(((float) ($r['score'] ?? 0)) * 100),
                    ], $results),
                'phrase' => $phrase,
                'humeur_originale' => $humeur,
                'humeur_normalisee' => $humeurNormalisee,
                'source' => 'huggingface',
            ];
        } catch (\Throwable $e) {
            return [
                'sentiment' => 'neutre',
                'confiance' => 0,
                'tous' => [],
                'phrase' => $phrase,
                'humeur_originale' => $humeur,
                'humeur_normalisee' => $humeurNormalisee,
                'source' => 'fallback_error',
                'erreur' => $e->getMessage(),
            ];
        }
    }

    private function normalizeHumeur(string $humeur): string
    {
        return match (trim($humeur)) {
            '😄 Joyeux' => 'heureux et joyeux',
            '😢 Triste' => 'triste et mélancolique',
            '😨 Effrayé' => 'effrayé et anxieux',
            '😌 Serein' => 'calme et serein',
            '😐 Neutre' => 'neutre',
            default => trim($this->removeEmoji($humeur)) ?: 'neutre',
        };
    }

    private function removeEmoji(string $text): string
    {
        return preg_replace('/[^\p{L}\p{N}\s\'-]/u', '', $text) ?? '';
    }

    private function mapSentiment(string $label): string
    {
        return match (strtolower(trim($label))) {
            'positive', 'label_2' => 'positif',
            'negative', 'label_0' => 'négatif',
            default => 'neutre',
        };
    }
}