<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;

final readonly class CoachChatService
{
    public function __construct(
        private CoachInsightService $coachInsightService,
        private GeminiClient $geminiClient,
    ) {
    }

    /**
     * @param array<string,mixed>|null $coachContext
     * @return array{reply:string, source:string}
     */
    public function reply(User $user, string $message, ?array $coachContext = null): array
    {
        $coach = $this->normalizeCoachContext($coachContext) ?? $this->coachInsightService->getInsight($user);
        $prompt = $this->buildPrompt($message, $coach['report'], $coach['insight']);
        $reply = $this->geminiClient->generateCoachChatReply($prompt);

        if ($reply !== null) {
            return [
                'reply' => $this->trimReply($reply),
                'source' => 'gemini',
            ];
        }

        return [
            'reply' => $this->localFallbackReply($message, $coach['report'], $coach['insight']),
            'source' => 'local_fallback',
        ];
    }

    /**
     * @param array<string,mixed>|null $coachContext
     * @return array{report:array<string,mixed>, insight:array<string,mixed>}|null
     */
    private function normalizeCoachContext(?array $coachContext): ?array
    {
        if (!is_array($coachContext['report'] ?? null) || !is_array($coachContext['insight'] ?? null)) {
            return null;
        }

        return [
            'report' => $coachContext['report'],
            'insight' => $coachContext['insight'],
        ];
    }

    /**
     * @param array<string,mixed> $report
     * @param array<string,mixed> $insight
     */
    private function buildPrompt(string $message, array $report, array $insight): string
    {
        $contextJson = json_encode([
            'report' => $report,
            'insight' => $insight,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are the AI Coach Assistant inside Serinity.

Stay focused on the user's exercise coaching context only:
- exercise report
- recommendations
- 7-day plan
- strengths
- improvement points
- nutrition support
- gentle exercise suggestions

Do not behave like a general chatbot.
Do not give diagnosis, prescriptions, supplement doses, or medical treatment.
Keep the answer short, supportive, and practical.
If the question is outside the coaching context, briefly redirect back to exercise and wellness support.

CURRENT COACHING CONTEXT
{$contextJson}

USER QUESTION
{$message}

Return only the assistant reply text. Do not use markdown tables.
PROMPT;
    }

    /**
     * @param array<string,mixed> $report
     * @param array<string,mixed> $insight
     */
    private function localFallbackReply(string $message, array $report, array $insight): string
    {
        $message = strtolower($message);
        $summary = $this->stringValue($insight['summary'] ?? null, 'Your coaching report is ready.');
        $engagement = (int) ($report['engagementScore'] ?? 0);
        $streak = (int) ($report['streakDays'] ?? 0);
        $completion = (int) ($report['completionRate'] ?? 0);

        if (str_contains($message, 'nutrition') || str_contains($message, 'food') || str_contains($message, 'meal')) {
            $nutrition = is_array($insight['nutritionSupport'] ?? null) ? $insight['nutritionSupport'] : [];
            $focus = $this->stringValue($nutrition['focus'] ?? null, 'Focus on simple, steady meals that support energy.');
            $foods = $this->listText($nutrition['foods'] ?? null);

            return sprintf('%s A simple next step is to include one of these foods today: %s.', $focus, $foods !== '' ? $foods : 'oats, eggs, or leafy greens');
        }

        if (str_contains($message, 'plan') || str_contains($message, 'week') || str_contains($message, 'day')) {
            $plan = $this->listValue($insight['plan7Days'] ?? null);
            $firstStep = $plan[0] ?? 'Start with one short breathing or grounding session today.';

            return sprintf('For today, keep it simple: %s Small completed actions are the goal.', $firstStep);
        }

        if (str_contains($message, 'recommend') || str_contains($message, 'suggest') || str_contains($message, 'exercise')) {
            $recommendations = $this->listValue($insight['recommendations'] ?? null);
            $recommendation = $recommendations[0] ?? 'Choose a short familiar exercise and complete it without pressure.';

            return sprintf('%s With an engagement score of %d/100, a gentle repeatable action is best.', $recommendation, $engagement);
        }

        if (str_contains($message, 'strength') || str_contains($message, 'good')) {
            $strengths = $this->listValue($insight['strengths'] ?? null);
            $strength = $strengths[0] ?? sprintf('You have a %d-day streak to build from.', $streak);

            return sprintf('One strength to keep using: %s', $strength);
        }

        if (str_contains($message, 'improve') || str_contains($message, 'better') || str_contains($message, 'progress')) {
            $improvements = $this->listValue($insight['improvements'] ?? null);
            $improvement = $improvements[0] ?? sprintf('Your completion rate is %d%%, so shorter sessions can help you stay consistent.', $completion);

            return sprintf('%s Try one small session today rather than aiming for intensity.', $improvement);
        }

        return sprintf('%s A helpful next step is to choose one short exercise from your plan and complete it today.', $summary);
    }

    private function trimReply(string $reply): string
    {
        $reply = trim(strip_tags($reply));
        $reply = preg_replace('/\s+/', ' ', $reply) ?? $reply;

        return mb_substr($reply, 0, 900);
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    private function listText(mixed $value): string
    {
        $items = array_slice($this->listValue($value), 0, 3);

        return implode(', ', $items);
    }

    /** @return list<string> */
    private function listValue(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn(mixed $item): string => is_string($item) ? trim($item) : '', $value),
            static fn(string $item): bool => $item !== '',
        ));
    }
}
