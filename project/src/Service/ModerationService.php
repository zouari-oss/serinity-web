<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ModerationService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function isToxic(string $text): bool
    {
        if (trim($text) === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://www.purgomalum.com/service/containsprofanity', [
                'query' => ['text' => $text],
            ]);

            return trim($response->getContent()) === 'true';
        } catch (TransportExceptionInterface) {
            return false;
        }
    }
}
