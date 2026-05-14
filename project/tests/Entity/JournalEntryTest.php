<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\JournalEntry;
use PHPUnit\Framework\TestCase;

final class JournalEntryTest extends TestCase
{
    public function testGetTopEmotionLabelReturnsTopLabelFromAiTags(): void
    {
        $entry = new JournalEntry();
        $entry->setAiTags(json_encode([
            'top_label' => 'sadness',
            'labels' => [
                ['label' => 'joy', 'score' => 0.2],
            ],
        ], JSON_THROW_ON_ERROR));

        self::assertSame('sadness', $entry->getTopEmotionLabel());
    }
}
