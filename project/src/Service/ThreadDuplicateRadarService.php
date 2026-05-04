<?php

namespace App\Service;

use App\Entity\ForumThread;
use App\Repository\ForumThreadRepository;

class ThreadDuplicateRadarService
{
    public function __construct(private readonly ForumThreadRepository $threadRepository)
    {
    }

    /**
     * @return list<array{
     *   id: int,
     *   title: string,
     *   excerpt: string,
     *   category: string,
     *   status: string,
     *   createdAt: string,
     *   ageDays: int,
     *   score: float,
     *   scorePercent: int,
     *   canRevive: bool
     * }>
     */
    public function findNearDuplicates(string $currentUserId, string $title, string $content, int $limit = 5): array
    {
        $title = trim($title);
        $content = trim($content);

        if ($title === '' || $content === '') {
            return [];
        }

        // include the user's own threads so reposts trigger the radar
        $candidates = $this->threadRepository->findDuplicateRadarCandidates(null, 60);
        if ($candidates === []) {
            return [];
        }

        $draftTitleTokens = $this->tokenize($title);
        $draftContentTokens = $this->tokenize($content);

        $lexical = [];
        foreach ($candidates as $thread) {
            $score = $this->lexicalScore($draftTitleTokens, $draftContentTokens, $thread);
            if ($score < 0.24) {
                continue;
            }

            $lexical[] = [
                'thread' => $thread,
                'lexical' => $score,
                'score' => $score,
            ];
        }

        if ($lexical === []) {
            return [];
        }

        usort($lexical, static fn (array $a, array $b): int => $b['lexical'] <=> $a['lexical']);
        $shortlist = array_slice($lexical, 0, 10);

        // purely lexical scoring; no external embeddings
        usort($shortlist, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $threshold = 0.44;
        $duplicates = [];

        foreach ($shortlist as $item) {
            $score = (float) $item['score'];
            if ($score < $threshold) {
                continue;
            }

            /** @var ForumThread $thread */
            $thread = $item['thread'];
            $createdAt = $thread->getCreatedAt();
            $ageDays = (int) max(0, $createdAt->diff(new \DateTimeImmutable())->days);

            $duplicates[] = [
                'id' => (int) $thread->getId(),
                'title' => (string) $thread->getTitle(),
                'excerpt' => $this->excerpt((string) $thread->getContent(), 180),
                'category' => (string) ($thread->getCategory()?->getName() ?? 'Unknown'),
                'status' => $thread->getStatus()->value,
                'createdAt' => $createdAt->format('Y-m-d H:i'),
                'ageDays' => $ageDays,
                'score' => $score,
                'scorePercent' => (int) round($score * 100),
                'canRevive' => $thread->getStatus()->value === 'archived' || $ageDays >= 30,
            ];

            if (count($duplicates) >= $limit) {
                break;
            }
        }

        return $duplicates;
    }

    /**
     * @param list<string> $draftTitleTokens
     * @param list<string> $draftContentTokens
     */
    private function lexicalScore(array $draftTitleTokens, array $draftContentTokens, ForumThread $candidate): float
    {
        $candidateTitle = (string) $candidate->getTitle();
        $candidateContent = (string) $candidate->getContent();

        $candidateTitleTokens = $this->tokenize($candidateTitle);
        $candidateContentTokens = $this->tokenize($candidateContent);

        $titleJaccard = $this->jaccard($draftTitleTokens, $candidateTitleTokens);
        $contentJaccard = $this->jaccard($draftContentTokens, $candidateContentTokens);

        similar_text(mb_strtolower(implode(' ', $draftTitleTokens)), mb_strtolower(implode(' ', $candidateTitleTokens)), $titlePercent);
        $titleFuzzy = $titlePercent / 100;

        return (0.5 * $titleJaccard) + (0.25 * $contentJaccard) + (0.25 * $titleFuzzy);
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $setA = array_values(array_unique($a));
        $setB = array_values(array_unique($b));

        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    

    private function excerpt(string $text, int $maxLength): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength).'...';
    }
}
