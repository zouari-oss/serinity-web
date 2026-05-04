<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Api\ZenQuotesClient;

final readonly class QuoteService
{
    private const FALLBACK_TEXT = 'Take a moment to breathe and refocus.';
    private const FALLBACK_AUTHOR = 'Serinity';

    public function __construct(
        private ZenQuotesClient $zenQuotesClient,
    ) {
    }

    /**
     * @return array{text:string,author:string}
     */
    public function getRandomQuote(): array
    {
        $quote = $this->zenQuotesClient->fetchRandomQuote();
        if ($quote === null) {
            return $this->fallbackQuote();
        }

        $text = trim((string) ($quote['text'] ?? ''));
        $author = trim((string) ($quote['author'] ?? ''));

        if ($text === '') {
            $text = self::FALLBACK_TEXT;
        }

        if ($author === '' || strcasecmp($author, 'unknown') === 0) {
            $author = self::FALLBACK_AUTHOR;
        }

        return [
            'text' => $text,
            'author' => $author,
        ];
    }

    /**
     * @return array{text:string,author:string}
     */
    private function fallbackQuote(): array
    {
        return [
            'text' => self::FALLBACK_TEXT,
            'author' => self::FALLBACK_AUTHOR,
        ];
    }
}
