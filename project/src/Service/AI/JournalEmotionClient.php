<?php

declare(strict_types=1);

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JournalEmotionClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $baseUrl,
        private string $route,
        private float $timeout,
        private float $threshold,
        private int $topK,
    ) {
    }

    /**
     * @return array{
     *     top_label: string,
     *     labels: list<array{label: string, score: float}>,
     *     model: ?string,
     *     threshold_used: float,
     *     top_k_used: int
     * }|null
     */
    public function analyzeContent(string $content): ?array
    {
        $trimmedContent = trim($content);
        if (!$this->enabled || $trimmedContent === '') {
            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->route, '/');
        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'text' => $trimmedContent,
                    'threshold' => $this->threshold,
                    'top_k' => $this->topK,
                    'include_all_scores' => false,
                ],
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Emotion AI request returned non-200 response.', [
                    'status_code' => $response->getStatusCode(),
                ]);

                return null;
            }

            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                return null;
            }

            $labels = $this->normalizeLabels($payload['labels'] ?? null);
            $topLabel = is_string($payload['top_label'] ?? null) ? trim((string) $payload['top_label']) : '';
            if ($topLabel === '' && $labels !== []) {
                $topLabel = $labels[0]['label'];
            }

            if ($topLabel === '') {
                return null;
            }

            $model = is_string($payload['model'] ?? null) ? trim((string) $payload['model']) : null;

            return [
                'top_label' => $topLabel,
                'labels' => $labels,
                'model' => $model !== '' ? $model : null,
                'threshold_used' => is_numeric($payload['threshold_used'] ?? null)
                    ? (float) $payload['threshold_used']
                    : $this->threshold,
                'top_k_used' => is_numeric($payload['top_k_used'] ?? null)
                    ? max(1, (int) $payload['top_k_used'])
                    : $this->topK,
            ];
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Emotion AI request failed.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @return list<array{label: string, score: float}>
     */
    private function normalizeLabels(mixed $labels): array
    {
        if (!is_array($labels)) {
            return [];
        }

        $normalizedLabels = [];
        foreach ($labels as $labelRow) {
            if (!is_array($labelRow)) {
                continue;
            }

            $label = is_string($labelRow['label'] ?? null) ? trim((string) $labelRow['label']) : '';
            if ($label === '') {
                continue;
            }

            $score = is_numeric($labelRow['score'] ?? null) ? (float) $labelRow['score'] : null;
            if ($score === null) {
                continue;
            }

            $normalizedLabels[] = [
                'label' => $label,
                'score' => $score,
            ];
        }

        return $normalizedLabels;
    }
}
