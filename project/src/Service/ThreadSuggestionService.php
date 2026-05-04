<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Repository\ForumThreadRepository;
use App\Repository\PostInteractionRepository;

class ThreadSuggestionService
{
    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly PostInteractionRepository $interactionRepository,
    ) {
    }

    /**
     * @return array{thread: ForumThread|null, categoryScores: list<array{categoryId: int, score: int}>}
     */
    public function buildSuggestion(string $userId): array
    {
        $categoryScores = $this->interactionRepository->findCategoryScoresForUser($userId);

        foreach ($categoryScores as $scoreRow) {
            if (($scoreRow['score'] ?? 0) <= 0) {
                continue;
            }

            $suggested = $this->threadRepository->findOneSuggestedInCategory($userId, (int) $scoreRow['categoryId']);
            if ($suggested instanceof ForumThread) {
                return [
                    'thread' => $suggested,
                    'categoryScores' => $categoryScores,
                ];
            }
        }

        return [
            'thread' => $this->threadRepository->findOneSuggestedAnyCategory($userId),
            'categoryScores' => $categoryScores,
        ];
    }
}