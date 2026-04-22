<?php

declare(strict_types=1);

namespace App\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class YouTubeRecommendationService
{
    private const ENDPOINT = 'https://www.googleapis.com/youtube/v3/search';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
    ) {
    }

    /**
     * @return list<array{title:string,url:string,channel:string,thumbnailUrl:string}>
     */
    public function recommend(string $query, int $limit = 3): array
    {
        if ($this->apiKey === '' || trim($query) === '') {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'key' => $this->apiKey,
                    'part' => 'snippet',
                    'q' => $query,
                    'type' => 'video',
                    'maxResults' => max(1, min(4, $limit)),
                    'safeSearch' => 'moderate',
                    'videoEmbeddable' => 'true',
                    'videoSyndicated' => 'true',
                ],
                'timeout' => 6.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            /** @var mixed $payload */
            $payload = $response->toArray(false);
            if (!is_array($payload) || !is_array($payload['items'] ?? null)) {
                return [];
            }

            $videos = [];

            foreach ($payload['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $videoId = (string) ($item['id']['videoId'] ?? '');
                $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
                $title = trim((string) ($snippet['title'] ?? ''));
                $channel = trim((string) ($snippet['channelTitle'] ?? ''));
                $thumbnailUrl = $this->resolveThumbnailUrl($snippet);

                if ($videoId === '' || $title === '') {
                    continue;
                }

                $videos[] = [
                    'title' => $title,
                    'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'channel' => $channel,
                    'thumbnailUrl' => $thumbnailUrl,
                ];
            }

            return $videos;
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('YouTube recommendation request failed.', [
                'exception' => $exception,
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * @param array<string,mixed> $snippet
     */
    private function resolveThumbnailUrl(array $snippet): string
    {
        $thumbnails = is_array($snippet['thumbnails'] ?? null) ? $snippet['thumbnails'] : [];

        foreach (['medium', 'high', 'default'] as $size) {
            $url = (string) ($thumbnails[$size]['url'] ?? '');
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }
}
