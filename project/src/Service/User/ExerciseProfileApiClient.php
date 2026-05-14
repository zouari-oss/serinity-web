<?php

declare(strict_types=1);

namespace App\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ExerciseProfileApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $baseUrl,
        private string $predictionRoute,
        private string $apiKey,
        private float $timeout,
    ) {
    }

    /**
     * @param array<string, float|int> $features
     *
     * @return array{label: string, raw: array<string, mixed>}|null
     */
    public function predict(array $features): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if (trim($this->baseUrl) === '' || trim($this->predictionRoute) === '') {
            $this->logger->warning('Exercise profile ML API is enabled but not fully configured.');

            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->predictionRoute, '/');
        $payload = [
            // Flat keys help simple Flask/FastAPI handlers that read features directly.
            ...$features,
            // Nested keys keep compatibility with handlers that expect a wrapped feature object.
            'features' => $features,
            'feature_columns' => ExerciseProfileDatasetBuilder::FEATURE_COLUMNS,
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => array_filter([
                    'Accept' => 'application/json',
                    'X-Internal-Api-Key' => trim($this->apiKey) !== '' ? $this->apiKey : null,
                ]),
                'json' => $payload,
                'timeout' => $this->timeout,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Exercise profile ML API returned non-200 response.', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);

                return null;
            }

            $data = $response->toArray(false);
            if (!is_array($data)) {
                $this->logger->warning('Exercise profile ML API returned a non-array payload.');

                return null;
            }

            $label = $this->extractLabel($data);
            if ($label === null) {
                $this->logger->warning('Exercise profile ML API payload did not include a usable label.', [
                    'payload' => $data,
                ]);

                return null;
            }

            return [
                'label' => $label,
                'raw' => $data,
            ];
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Exercise profile ML API request failed.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractLabel(array $data): ?string
    {
        $candidate = $data['label']
            ?? $data['profile']
            ?? $data['prediction']
            ?? $data['predicted_profile']
            ?? null;

        if (!is_string($candidate)) {
            return null;
        }

        $candidate = strtolower(trim($candidate));

        return in_array($candidate, ['calm', 'balanced', 'active'], true) ? $candidate : null;
    }
}
