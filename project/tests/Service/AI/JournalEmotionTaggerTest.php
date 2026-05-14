<?php

declare(strict_types=1);

namespace App\Tests\Service\AI;

use App\Entity\JournalEntry;
use App\Service\AI\JournalEmotionClient;
use App\Service\AI\JournalEmotionTagger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JournalEmotionTaggerTest extends TestCase
{
    public function testApplyStoresAiEmotionResultOnJournalEntry(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'top_label' => 'anxiety',
                'labels' => [
                    ['label' => 'anxiety', 'score' => 0.91],
                    ['label' => 'sadness', 'score' => 0.44],
                ],
                'model' => 'journal-emotion-model-v1',
                'threshold_used' => 0.35,
                'top_k_used' => 2,
            ], JSON_THROW_ON_ERROR), [
                'http_code' => 200,
            ]),
        ]);

        $client = new JournalEmotionClient(
            $httpClient,
            new NullLogger(),
            true,
            'https://example.test',
            '/predict',
            2.0,
            0.35,
            2
        );

        $tagger = new JournalEmotionTagger($client);

        $entry = new JournalEntry();
        $entry->setContent('I feel anxious but I am trying to stay calm today.');

        self::assertTrue($tagger->apply($entry));
        self::assertSame('anxiety', $entry->getTopEmotionLabel());
        self::assertSame('journal-emotion-model-v1', $entry->getAiModelVersion());
        self::assertInstanceOf(\DateTimeImmutable::class, $entry->getAiGeneratedAt());
        self::assertNotNull($entry->getDecodedAiTags());
    }
}
