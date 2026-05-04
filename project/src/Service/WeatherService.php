<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class WeatherService
{
    private const ENDPOINT = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{temperature:float|int,weatherCode:int,isDay:int,localTime:string,timezone:string,weatherLabel:string}
     */
    public function getCurrentWeather(float $latitude, float $longitude): array
    {
        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,weather_code,is_day',
                    'timezone' => 'auto',
                ],
                'timeout' => 6.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                return $this->fallbackWeather();
            }

            /** @var mixed $payload */
            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                return $this->fallbackWeather();
            }

            $current = is_array($payload['current'] ?? null) ? $payload['current'] : [];
            $timezone = trim((string) ($payload['timezone'] ?? 'UTC'));
            if ($timezone === '') {
                $timezone = 'UTC';
            }

            return [
                'temperature' => round((float) ($current['temperature_2m'] ?? 22), 1),
                'weatherCode' => (int) ($current['weather_code'] ?? 0),
                'isDay' => (int) ($current['is_day'] ?? 1),
                'localTime' => $this->resolveLocalTime(
                    is_string($current['time'] ?? null) ? $current['time'] : null,
                    $timezone,
                ),
                'timezone' => $this->formatTimezoneLabel($timezone),
                'weatherLabel' => $this->formatWeatherLabel((int) ($current['weather_code'] ?? 0)),
            ];
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Open-Meteo request failed.', [
                'exception' => $exception,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return $this->fallbackWeather();
        }
    }

    private function resolveLocalTime(?string $currentTime, string $timezone): string
    {
        try {
            if (is_string($currentTime) && trim($currentTime) !== '') {
                return (new \DateTimeImmutable($currentTime))->format('H:i');
            }

            return (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('H:i');
        } catch (\Throwable) {
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('H:i');
        }
    }

    /**
     * @return array{temperature:int,weatherCode:int,isDay:int,localTime:string,timezone:string,weatherLabel:string}
     */
    private function fallbackWeather(): array
    {
        $timezone = 'UTC';

        return [
            'temperature' => 22,
            'weatherCode' => 0,
            'isDay' => 1,
            'localTime' => (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('H:i'),
            'timezone' => $timezone,
            'weatherLabel' => 'Clear sky',
        ];
    }

    private function formatTimezoneLabel(string $timezone): string
    {
        if (!str_contains($timezone, '/')) {
            return $timezone;
        }

        $parts = explode('/', $timezone);
        $label = (string) end($parts);

        return str_replace('_', ' ', $label);
    }

    private function formatWeatherLabel(int $weatherCode): string
    {
        return match (true) {
            $weatherCode === 0 => 'Clear sky',
            in_array($weatherCode, [1, 2, 3], true) => 'Partly cloudy',
            in_array($weatherCode, [45, 48], true) => 'Foggy',
            in_array($weatherCode, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true) => 'Rainy',
            in_array($weatherCode, [71, 73, 75, 77, 85, 86], true) => 'Snowy',
            in_array($weatherCode, [95, 96, 99], true) => 'Stormy',
            default => 'Mild weather',
        };
    }
}
