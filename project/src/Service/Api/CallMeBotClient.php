<?php

declare(strict_types=1);

namespace App\Service\Api;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CallMeBotClient
{
    private const ENDPOINT = 'https://api.callmebot.com/whatsapp.php';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $phone,
        private string $apiKey,
    ) {
    }

    public function sendJournalSavedNotification(string $title, ?\DateTimeInterface $createdAt = null): bool
    {
        $safeTitle = trim($title) === '' ? 'Untitled' : trim($title);
        $timestamp = $createdAt ?? new \DateTimeImmutable();
        $date = $timestamp->format('Y-m-d');
        $time = $timestamp->format('H:i');

        $message = sprintf(
            "✅ Journal entry saved successfully\n\n📝 Title: %s\n📅 Date: %s\n⏰ Time: %s\n\nThanks for checking in today with Serinity.",
            $safeTitle,
            $date,
            $time,
        );

        return $this->sendMessage($message);
    }

    public function sendMessage(string $message): bool
    {
        $message = trim($message);
        $normalizedPhone = str_replace(' ', '', trim($this->phone));
        $apiKey = trim($this->apiKey);

        if ($normalizedPhone === '' || $apiKey === '' || $message === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', self::ENDPOINT, [
                'query' => [
                    'phone' => $normalizedPhone,
                    'text' => $message,
                    'apikey' => $apiKey,
                ],
                'timeout' => 8.0,
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $body = mb_strtolower(trim($response->getContent(false)));

            return $body !== '' && !str_contains($body, 'error');
        } catch (ExceptionInterface|\Throwable $exception) {
            $this->logger->warning('CallMeBot request failed.', [
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
