<?php

namespace App\Service\Sleep;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $apiKey,
        private float $defaultLat,
        private float $defaultLon,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSleepWidgetData(
        ?float $lat = null,
        ?float $lon = null,
        string|int $userKey = 'guest'
    ): ?array {
        $lat = $lat ?? $this->defaultLat;
        $lon = $lon ?? $this->defaultLon;

        $cacheKey = 'sleep_weather_' . md5($userKey . '_' . $lat . '_' . $lon);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($lat, $lon): ?array {
            $item->expiresAfter(600);

            try {
                $currentResponse = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                    'query' => [
                        'lat'   => $lat,
                        'lon'   => $lon,
                        'appid' => $this->apiKey,
                        'units' => 'metric',
                        'lang'  => 'fr',
                    ],
                    'timeout' => 10,
                ]);

                $forecastResponse = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                    'query' => [
                        'lat'   => $lat,
                        'lon'   => $lon,
                        'appid' => $this->apiKey,
                        'units' => 'metric',
                        'lang'  => 'fr',
                    ],
                    'timeout' => 10,
                ]);

                /** @var array<string, mixed> $currentData */
                $currentData = $currentResponse->toArray(false);

                /** @var array<string, mixed> $forecastData */
                $forecastData = $forecastResponse->toArray(false);

                if (!isset($currentData['main']) || !isset($forecastData['list'])) {
                    return null;
                }

                /** @var array<int, array<string, mixed>> $forecastList */
                $forecastList = is_array($forecastData['list']) ? $forecastData['list'] : [];

                return [
                    'location' => [
                        'city' => $forecastData['city']['name'] ?? null,
                        'country' => $forecastData['city']['country'] ?? null,
                        'lat' => $lat,
                        'lon' => $lon,
                    ],
                    'current' => [
                        'temp'       => round((float)($currentData['main']['temp'] ?? 0), 1),
                        'feels_like' => round((float)($currentData['main']['feels_like'] ?? 0), 1),
                        'humidity'   => (int)($currentData['main']['humidity'] ?? 0),
                        'pressure'   => (int)($currentData['main']['pressure'] ?? 0),
                        'wind_speed' => round((float)($currentData['wind']['speed'] ?? 0), 1),
                        'desc'       => ucfirst((string)($currentData['weather'][0]['description'] ?? '')),
                        'icon_url'   => isset($currentData['weather'][0]['icon'])
                            ? sprintf('https://openweathermap.org/img/wn/%s@2x.png', $currentData['weather'][0]['icon'])
                            : null,
                    ],
                    'forecast' => array_map(function (array $item): array {
                        return [
                            'dt' => $item['dt'] ?? null,
                            'temp' => round((float)($item['main']['temp'] ?? 0), 1),
                            'feels_like' => round((float)($item['main']['feels_like'] ?? 0), 1),
                            'humidity' => (int)($item['main']['humidity'] ?? 0),
                            'pressure' => (int)($item['main']['pressure'] ?? 0),
                            'wind_speed' => round((float)($item['wind']['speed'] ?? 0), 1),
                            'pop' => isset($item['pop']) ? (int)round((float)$item['pop'] * 100) : 0,
                            'desc' => ucfirst((string)($item['weather'][0]['description'] ?? '')),
                            'icon_url' => isset($item['weather'][0]['icon'])
                                ? sprintf('https://openweathermap.org/img/wn/%s@2x.png', $item['weather'][0]['icon'])
                                : null,
                        ];
                    }, array_slice($forecastList, 0, 6)),
                    'daily' => $this->buildDailyPreview($forecastList),
                ];
            } catch (\Throwable $e) {
                return null;
            }
        });
    }

    /**
     * @param array<int, array<string, mixed>> $forecastList
     * @return array<int, array<string, mixed>>
     */
    private function buildDailyPreview(array $forecastList): array
    {
        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        foreach ($forecastList as $item) {
            if (!isset($item['dt'])) {
                continue;
            }

            $dateKey = date('Y-m-d', (int)$item['dt']);

            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'dt' => $item['dt'],
                    'temp_min' => (float)($item['main']['temp_min'] ?? 0),
                    'temp_max' => (float)($item['main']['temp_max'] ?? 0),
                    'pop' => 0,
                    'desc' => ucfirst((string)($item['weather'][0]['description'] ?? '')),
                    'icon_url' => isset($item['weather'][0]['icon'])
                        ? sprintf('https://openweathermap.org/img/wn/%s@2x.png', $item['weather'][0]['icon'])
                        : null,
                ];
            }

            $grouped[$dateKey]['temp_min'] = min((float)$grouped[$dateKey]['temp_min'], (float)($item['main']['temp_min'] ?? 0));
            $grouped[$dateKey]['temp_max'] = max((float)$grouped[$dateKey]['temp_max'], (float)($item['main']['temp_max'] ?? 0));
            $grouped[$dateKey]['pop'] = max((int)$grouped[$dateKey]['pop'], isset($item['pop']) ? (int)round((float)$item['pop'] * 100) : 0);
        }

        return array_values(array_map(function (array $day): array {
            return [
                'dt' => $day['dt'],
                'temp_min' => round((float)$day['temp_min'], 1),
                'temp_max' => round((float)$day['temp_max'], 1),
                'pop' => $day['pop'],
                'desc' => $day['desc'],
                'icon_url' => $day['icon_url'],
            ];
        }, array_slice($grouped, 0, 3, true)));
    }
}