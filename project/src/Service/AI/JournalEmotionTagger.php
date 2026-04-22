<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Entity\JournalEntry;

final readonly class JournalEmotionTagger
{
    public function __construct(
        private JournalEmotionClient $journalEmotionClient,
    ) {
    }

    public function apply(JournalEntry $journalEntry): bool
    {
        $result = $this->journalEmotionClient->analyzeContent($journalEntry->getContent());
        if ($result === null) {
            return false;
        }

        try {
            $aiTags = json_encode([
                'top_label' => $result['top_label'],
                'labels' => $result['labels'],
                'threshold_used' => $result['threshold_used'],
                'top_k_used' => $result['top_k_used'],
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        $journalEntry->setAiTags($aiTags);

        $modelVersion = is_string($result['model'] ?? null) ? trim((string) $result['model']) : '';
        $journalEntry->setAiModelVersion($modelVersion !== '' ? mb_substr($modelVersion, 0, 32) : null);

        $journalEntry->setAiGeneratedAt(new \DateTimeImmutable());

        return true;
    }
}
