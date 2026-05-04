<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AmbientSoundService
{
    /**
     * @var list<string>
     */
    private const BASE_URLS = [
        'https://de1.api.radio-browser.info',
        'https://fr1.api.radio-browser.info',
        'https://at1.api.radio-browser.info',
        'https://all.api.radio-browser.info',
    ];

    private const FALLBACK_AUDIO_URL = 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3';

    /**
     * @var list<string>
     */
    private const INCLUDED_WORDS = ['piano', 'meditation', 'calm', 'sleep', 'relax', 'nature', 'zen', 'soft', 'ambient'];

    /**
     * @var list<string>
     */
    private const HARD_BLOCKED_WORDS = [
        'techno',
        'trance',
        'dubstep',
        'hardstyle',
        'edm',
        'club',
        'party',
    ];

    /**
     * @var list<string>
     */
    private const SOFT_BLOCKED_WORDS = [
        'electro',
        'electronic',
        'house',
        'deep house',
        'dj',
        'beat',
        'remix',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array{audioUrl:string,title:string,type:string}
     */
    public function getAmbientSound(array $context = []): array
    {
        $soundType = $this->resolveSoundType($context);
        $queries = $this->buildSearchQueries($context);

        foreach ($queries as $query) {
            foreach (self::BASE_URLS as $baseUrl) {
                $station = $this->searchStations($baseUrl, $query, $soundType);
                if ($station !== null) {
                    return $station;
                }
            }
        }

        $this->logger->warning('No suitable ambient radio station found. Falling back to default audio.', [
            'queries' => $queries,
            'type' => $soundType,
            'fallbackUrl' => self::FALLBACK_AUDIO_URL,
        ]);

        return [
            'audioUrl' => self::FALLBACK_AUDIO_URL,
            'title' => $this->resolveFallbackTitle($soundType),
            'type' => $soundType,
        ];
    }

    /**
     * @return array{audioUrl:string,title:string,type:string}|null
     */
    private function searchStations(string $baseUrl, string $query, string $soundType): ?array
    {
        $queryTerms = $this->splitTerms($query);

        try {
            $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/json/stations/search', [
                'headers' => [
                    'User-Agent' => 'Serinity/1.0',
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'name' => $query,
                    'hidebroken' => 'true',
                    'limit' => 50,
                    'order' => 'clicktrend',
                    'reverse' => 'true',
                ],
                'timeout' => 8.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Radio Browser request returned a non-200 response.', [
                    'baseUrl' => $baseUrl,
                    'statusCode' => $response->getStatusCode(),
                ]);

                return null;
            }

            /** @var mixed $payload */
            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                $this->logger->warning('Radio Browser response was not a station list.', [
                    'baseUrl' => $baseUrl,
                ]);

                return null;
            }

            $this->logger->info('Radio Browser search returned stations.', [
                'baseUrl' => $baseUrl,
                'query' => $query,
                'count' => count($payload),
            ]);

            $station = $this->selectBestStation($payload, $queryTerms, $soundType, $baseUrl, $query);
            if ($station === null) {
                $this->logger->warning('Radio Browser mirror returned no suitable relaxing station.', [
                    'baseUrl' => $baseUrl,
                    'query' => $query,
                ]);
            }

            return $station;
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Radio Browser request failed.', [
                'baseUrl' => $baseUrl,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $stations
     * @param list<string> $queryTerms
     * @return array{audioUrl:string,title:string,type:string}|null
     */
    private function selectBestStation(array $stations, array $queryTerms, string $soundType, string $baseUrl, string $query): ?array
    {
        $bestStation = null;
        $bestScore = PHP_INT_MIN;
        $bestTags = '';

        foreach ($stations as $station) {
            if (!is_array($station)) {
                continue;
            }

            $audioUrl = $this->resolveStationUrl($station);
            if ($audioUrl === null) {
                continue;
            }

            $metadata = strtolower(trim(implode(' ', array_filter([
                (string) ($station['name'] ?? ''),
                (string) ($station['tags'] ?? ''),
                (string) ($station['favicon'] ?? ''),
                (string) ($station['homepage'] ?? ''),
                (string) ($station['url'] ?? ''),
                (string) ($station['url_resolved'] ?? ''),
                (string) ($station['codec'] ?? ''),
                (string) ($station['language'] ?? ''),
                (string) ($station['country'] ?? ''),
                (string) ($station['state'] ?? ''),
            ]))));

            if ($metadata === '' || $this->containsHardBlockedWord($metadata)) {
                continue;
            }

            $score = $this->scoreStation($metadata, $station, $queryTerms);
            if ($score <= 0 || $score <= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $bestStation = [
                'audioUrl' => $audioUrl,
                'title' => $this->resolveStationTitle($station, $soundType),
                'type' => $soundType,
            ];
            $bestTags = (string) ($station['tags'] ?? '');
        }

        if ($bestStation !== null) {
            $this->logger->info('Ambient radio station selected.', [
                'baseUrl' => $baseUrl,
                'title' => $bestStation['title'],
                'tags' => $bestTags,
                'type' => $soundType,
                'audioUrl' => $bestStation['audioUrl'],
                'query' => $query,
                'score' => $bestScore,
            ]);
        }

        return $bestStation;
    }

    /**
     * @param array<string,mixed> $station
     * @param list<string> $queryTerms
     */
    private function scoreStation(string $metadata, array $station, array $queryTerms): int
    {
        $score = 0;

        foreach ($queryTerms as $term) {
            if (str_contains($metadata, $term)) {
                $score += 10;
            }
        }

        foreach (self::INCLUDED_WORDS as $word) {
            if (str_contains($metadata, $word)) {
                $score += 5;
            }
        }

        foreach (self::SOFT_BLOCKED_WORDS as $word) {
            if (str_contains($metadata, $word)) {
                $score -= 12;
            }
        }

        $votes = (int) ($station['votes'] ?? 0);
        $clickCount = (int) ($station['clickcount'] ?? 0);
        $bitrate = (int) ($station['bitrate'] ?? 0);

        return $score
            + min(10, (int) floor($votes / 20))
            + min(5, (int) floor($clickCount / 50))
            + min(4, (int) floor($bitrate / 64));
    }

    private function containsHardBlockedWord(string $metadata): bool
    {
        foreach (self::HARD_BLOCKED_WORDS as $word) {
            if (str_contains($metadata, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $station
     */
    private function resolveStationUrl(array $station): ?string
    {
        foreach (['url_resolved', 'url'] as $field) {
            $value = trim((string) ($station[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $station
     */
    private function resolveStationTitle(array $station, string $soundType): string
    {
        $title = trim((string) ($station['name'] ?? ''));

        return $title !== '' ? $title : $this->resolveFallbackTitle($soundType);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function resolveSoundType(array $context): string
    {
        $fatigue = strtolower(trim((string) ($context['fatigue'] ?? '')));
        $moment = strtolower(trim((string) ($context['moment'] ?? '')));
        $exerciseType = strtolower(trim((string) ($context['exerciseType'] ?? '')));

        return match (true) {
            $fatigue === 'high' => 'meditation',
            $moment === 'evening' || $moment === 'night' => 'piano',
            str_contains($exerciseType, 'respiration')
                || str_contains($exerciseType, 'breath')
                || str_contains($exerciseType, 'relax') => 'ambient',
            default => 'nature',
        };
    }

    /**
     * @param array<string,mixed> $context
     * @return list<string>
     */
    private function buildSearchQueries(array $context): array
    {
        $fatigue = strtolower(trim((string) ($context['fatigue'] ?? '')));
        $moment = strtolower(trim((string) ($context['moment'] ?? '')));

        $primaryQuery = match (true) {
            $fatigue === 'high' => 'sleep piano meditation',
            $moment === 'evening' || $moment === 'night' => 'calm piano ambient',
            default => 'nature rain meditation',
        };

        return array_values(array_unique([
            $primaryQuery,
            'calm piano',
            'meditation piano',
            'relax piano',
            'ambient meditation',
            'nature relax',
            'sleep calm',
        ]));
    }

    /**
     * @return list<string>
     */
    private function splitTerms(string $query): array
    {
        return array_values(array_filter(
            array_map('trim', explode(' ', strtolower($query))),
            static fn(string $term): bool => $term !== ''
        ));
    }

    private function resolveFallbackTitle(string $soundType): string
    {
        return match ($soundType) {
            'meditation' => 'Calm meditation fallback',
            'piano' => 'Quiet piano fallback',
            'ambient' => 'Ambient relaxation fallback',
            default => 'Nature relaxation fallback',
        };
    }
}
