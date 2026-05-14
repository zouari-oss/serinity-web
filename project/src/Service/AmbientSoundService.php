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

    /**
     * Soft classical piano is the only fallback we keep when no suitable radio
     * station is found.
     */
    private const FALLBACK_AUDIO_URL = 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3';

    /**
     * The service always leans toward calm piano, even if the caller provides
     * broader session context.
     *
     * @var list<string>
     */
    private const SEARCH_QUERIES = [
        'soft classical piano',
        'calm piano',
        'relaxing piano',
        'piano meditation',
        'classical piano radio',
        'peaceful piano',
        'instrumental piano',
        'sleep piano',
    ];

    /**
     * @var list<string>
     */
    private const POSITIVE_KEYWORDS = [
        'piano',
        'classical',
        'calm',
        'soft',
        'relaxing',
        'relaxation',
        'meditation',
        'instrumental',
        'peaceful',
        'sleep',
        'ambient',
    ];

    /**
     * Stations with these keywords are rejected outright.
     *
     * @var list<string>
     */
    private const NEGATIVE_KEYWORDS = [
        'techno',
        'edm',
        'house',
        'trance',
        'dubstep',
        'hardstyle',
        'rock',
        'metal',
        'rap',
        'hip hop',
        'pop',
        'dance',
        'club',
        'party',
        'remix',
        'beat',
        'dj',
    ];

    private const MIN_ACCEPTABLE_SCORE = 40;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array{audioUrl:string,url:string,title:string,type:string,query:string,source:string}
     */
    public function getAmbientSound(array $context = []): array
    {
        $normalizedContext = $this->normalizeContext($context);
        $bestCandidate = null;
        $bestScore = PHP_INT_MIN;

        $this->logger->info('Piano ambient recommendation started.', [
            'context' => $normalizedContext,
            'queries' => self::SEARCH_QUERIES,
        ]);

        foreach (self::SEARCH_QUERIES as $query) {
            foreach (self::BASE_URLS as $baseUrl) {
                $candidate = $this->searchStations($baseUrl, $query);
                if ($candidate === null) {
                    continue;
                }

                if (($candidate['score'] ?? PHP_INT_MIN) > $bestScore) {
                    $bestCandidate = $candidate;
                    $bestScore = (int) $candidate['score'];
                }
            }
        }

        if ($bestCandidate !== null && $bestScore >= self::MIN_ACCEPTABLE_SCORE) {
            unset($bestCandidate['score']);

            return $bestCandidate;
        }

        $this->logger->warning('No suitable piano station found. Using fallback audio.', [
            'context' => $normalizedContext,
            'bestScore' => $bestScore,
            'fallbackUrl' => self::FALLBACK_AUDIO_URL,
        ]);

        return $this->buildFallbackResult();
    }

    /**
     * @return array<string,string>
     */
    private function normalizeContext(array $context): array
    {
        return [
            'exerciseType' => strtolower(trim((string) ($context['exerciseType'] ?? ''))),
            'exerciseTheme' => strtolower(trim((string) ($context['exerciseTheme'] ?? $context['theme'] ?? ''))),
            'fatigue' => strtolower(trim((string) ($context['fatigue'] ?? ''))),
            'moment' => strtolower(trim((string) ($context['moment'] ?? ''))),
            'weather' => strtolower(trim((string) ($context['weather'] ?? $context['weatherLabel'] ?? ''))),
        ];
    }

    /**
     * @return array{audioUrl:string,url:string,title:string,type:string,query:string,source:string,score:int}|null
     */
    private function searchStations(string $baseUrl, string $query): ?array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/json/stations/search', [
                'headers' => [
                    'User-Agent' => 'Serinity/1.0',
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'name' => $query,
                    'hidebroken' => 'true',
                    'limit' => 40,
                    'order' => 'clicktrend',
                    'reverse' => 'true',
                ],
                'timeout' => 8.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Radio Browser returned a non-200 response.', [
                    'baseUrl' => $baseUrl,
                    'query' => $query,
                    'statusCode' => $response->getStatusCode(),
                ]);

                return null;
            }

            /** @var mixed $payload */
            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                return null;
            }

            return $this->selectBestStation($payload, $baseUrl, $query);
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('Piano station lookup failed for a Radio Browser mirror.', [
                'baseUrl' => $baseUrl,
                'query' => $query,
                'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * @param list<array<string,mixed>> $stations
     * @return array{audioUrl:string,url:string,title:string,type:string,query:string,source:string,score:int}|null
     */
    private function selectBestStation(array $stations, string $baseUrl, string $query): ?array
    {
        $bestStation = null;
        $bestScore = PHP_INT_MIN;

        foreach ($stations as $station) {
            if (!is_array($station)) {
                continue;
            }

            $audioUrl = $this->resolveStationUrl($station);
            if ($audioUrl === null) {
                continue;
            }

            $metadata = $this->buildMetadata($station);
            if ($metadata === '' || $this->containsAny($metadata, self::NEGATIVE_KEYWORDS)) {
                continue;
            }

            $score = $this->scoreStation($station, $metadata);
            if ($score <= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $title = $this->resolveStationTitle($station);
            $bestStation = [
                'audioUrl' => $audioUrl,
                'url' => $audioUrl,
                'title' => $title,
                'type' => 'piano',
                'query' => $query,
                'source' => $baseUrl,
                'score' => $score,
            ];
        }

        if ($bestStation !== null) {
            $this->logger->info('Piano station candidate selected from mirror.', [
                'query' => $query,
                'source' => $baseUrl,
                'title' => $bestStation['title'],
                'score' => $bestScore,
            ]);
        }

        return $bestStation;
    }

    /**
     * Behavioral scoring is intentionally simple: piano is heavily preferred,
     * classical piano is the strongest match, and noisy genres are filtered out
     * before scoring.
     *
     * @param array<string,mixed> $station
     */
    private function scoreStation(array $station, string $metadata): int
    {
        $score = 0;

        $score += $this->countMatches($metadata, self::POSITIVE_KEYWORDS) * 6;

        if (str_contains($metadata, 'piano')) {
            $score += 40;
        }

        if (str_contains($metadata, 'classical')) {
            $score += 22;
        }

        if (str_contains($metadata, 'piano') && str_contains($metadata, 'classical')) {
            $score += 30;
        }

        if (str_contains($metadata, 'meditation') || str_contains($metadata, 'relax')) {
            $score += 10;
        }

        if (str_contains($metadata, 'instrumental') || str_contains($metadata, 'peaceful') || str_contains($metadata, 'sleep')) {
            $score += 8;
        }

        $name = strtolower(trim((string) ($station['name'] ?? '')));
        $tags = strtolower(trim((string) ($station['tags'] ?? '')));

        if (str_contains($name, 'piano')) {
            $score += 18;
        }
        if (str_contains($name, 'classical')) {
            $score += 10;
        }
        if (str_contains($tags, 'piano')) {
            $score += 12;
        }
        if (str_contains($tags, 'classical')) {
            $score += 8;
        }

        $votes = max(0, (int) ($station['votes'] ?? 0));
        $clickCount = max(0, (int) ($station['clickcount'] ?? 0));
        $bitrate = max(0, (int) ($station['bitrate'] ?? 0));

        $score += min(10, (int) floor($votes / 20));
        $score += min(8, (int) floor($clickCount / 40));

        if ($bitrate >= 32 && $bitrate <= 192) {
            $score += 4;
        }

        return $score;
    }

    /**
     * @param array<string,mixed> $station
     */
    private function buildMetadata(array $station): string
    {
        return strtolower(trim(implode(' ', array_filter([
            (string) ($station['name'] ?? ''),
            (string) ($station['tags'] ?? ''),
            (string) ($station['homepage'] ?? ''),
            (string) ($station['country'] ?? ''),
            (string) ($station['state'] ?? ''),
            (string) ($station['language'] ?? ''),
            (string) ($station['codec'] ?? ''),
        ]))));
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
    private function resolveStationTitle(array $station): string
    {
        $title = trim((string) ($station['name'] ?? ''));

        return $title !== '' ? $title : 'Soft classical piano radio';
    }

    /**
     * @return array{audioUrl:string,url:string,title:string,type:string,query:string,source:string}
     */
    private function buildFallbackResult(): array
    {
        return [
            'audioUrl' => self::FALLBACK_AUDIO_URL,
            'url' => self::FALLBACK_AUDIO_URL,
            'title' => 'Soft classical piano fallback',
            'type' => 'piano',
            'query' => 'soft classical piano',
            'source' => 'soundhelix',
        ];
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $needles
     */
    private function countMatches(string $haystack, array $needles): int
    {
        $matches = 0;
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                ++$matches;
            }
        }

        return $matches;
    }
}
