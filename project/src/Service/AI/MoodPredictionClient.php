<?php

declare(strict_types=1);

namespace App\Service\AI;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class MoodPredictionClient
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
     * @param array{
     *     userId?: string|null,
     *     weekStart: string,
     *     weekEnd: string,
     *     entries: list<array{
     *         entryDate: string,
     *         momentType: string,
     *         moodLevel: int|float,
     *         emotions?: list<string>,
     *         influences?: list<string>
     *     }>
     * } $payload
     *
     * @return array{
     *     success: bool,
     *     model: string,
     *     generatedAt: string,
     *     inputEntries: int,
     *     predictedNextWeekScore: float,
     *     nextWeekAverage: float,
     *     trend: string,
     *     confidence: int,
     *     label: string,
     *     dailyForecast: list<array{date: string, predictedMoodLevel: float, label: string}>,
     *     insights: list<string>,
     *     recommendations: list<string>,
     *     warning: string|null
     * }|null
     */
    public function predictNextWeek(array $payload): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        if (trim($this->baseUrl) === '' || trim($this->predictionRoute) === '' || trim($this->apiKey) === '') {
            $this->logger->warning('Mood ML API is enabled but not fully configured.');

            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($this->predictionRoute, '/');

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'X-Internal-Api-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $this->logger->warning('Mood ML API returned non-200 response.', [
                    'status_code' => $statusCode,
                    'body' => $response->getContent(false),
                ]);

                return null;
            }

            $data = $response->toArray(false);

            if (!is_array($data) || ($data['success'] ?? false) !== true) {
                $this->logger->warning('Mood ML API returned invalid payload.', [
                    'payload' => $data,
                ]);

                return null;
            }

            return $this->normalizePrediction($data);
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Mood ML API request failed.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     success: bool,
     *     model: string,
     *     generatedAt: string,
     *     inputEntries: int,
     *     predictedNextWeekScore: float,
     *     nextWeekAverage: float,
     *     trend: string,
     *     confidence: int,
     *     label: string,
     *     dailyForecast: list<array{date: string, predictedMoodLevel: float, label: string}>,
     *     insights: list<string>,
     *     recommendations: list<string>,
     *     warning: string|null
     * }
     */
    private function normalizePrediction(array $data): array
    {
        return [
            'success' => true,
            'model' => $this->stringValue($data['model'] ?? null, 'serinity-v5-baseline-with-trend'),
            'generatedAt' => $this->stringValue($data['generatedAt'] ?? null, ''),
            'inputEntries' => $this->intValue($data['inputEntries'] ?? null),
            'predictedNextWeekScore' => $this->floatValue($data['predictedNextWeekScore'] ?? null),
            'nextWeekAverage' => $this->floatValue($data['nextWeekAverage'] ?? null),
            'trend' => $this->stringValue($data['trend'] ?? null, 'stable'),
            'confidence' => $this->intValue($data['confidence'] ?? null),
            'label' => $this->stringValue($data['label'] ?? null, 'watch'),
            'dailyForecast' => $this->normalizeDailyForecast($data['dailyForecast'] ?? null),
            'insights' => $this->normalizeStringList($data['insights'] ?? null),
            'recommendations' => $this->normalizeStringList($data['recommendations'] ?? null),
            'warning' => is_string($data['warning'] ?? null) && trim((string) $data['warning']) !== ''
                ? trim((string) $data['warning'])
                : null,
        ];
    }

    /**
     * @return list<array{date: string, predictedMoodLevel: float, label: string}>
     */
    private function normalizeDailyForecast(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $forecast = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $this->stringValue($row['date'] ?? null, '');
            if ($date === '') {
                continue;
            }

            $forecast[] = [
                'date' => $date,
                'predictedMoodLevel' => $this->floatValue($row['predictedMoodLevel'] ?? null),
                'label' => $this->stringValue($row['label'] ?? null, 'watch'),
            ];
        }

        return $forecast;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private function stringValue(mixed $value, string $default): string
    {
        if (!is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value !== '' ? $value : $default;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}