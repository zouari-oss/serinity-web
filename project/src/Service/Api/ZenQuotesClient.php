<?php

declare(strict_types=1);

namespace App\Service\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ZenQuotesClient
{
    private const ENDPOINT = 'https://zenquotes.io/api/random';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{text:string,author:string}|null
     */
    public function fetchRandomQuote(): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'timeout' => 5.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            /** @var mixed $payload */
            $payload = $response->toArray(false);
            if (!is_array($payload) || $payload === [] || !is_array($payload[0])) {
                return null;
            }

            $quote = trim((string) ($payload[0]['q'] ?? ''));
            $author = trim((string) ($payload[0]['a'] ?? ''));

            if ($quote === '' || $author === '') {
                return null;
            }

            return [
                'text' => $quote,
                'author' => $author,
            ];
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('ZenQuotes request failed.', [
                'exception' => $exception,
            ]);

            return null;
        }
    }
}

