<?php

declare(strict_types=1);

namespace App\Service\User;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const REQUEST_TIMEOUT_SECONDS = 30.0;
    private const REQUEST_MAX_DURATION_SECONDS = 30.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $model,
    ) {
    }

    /**
     * @param array<string,mixed> $report
     * @return array{
     *     summary:string,
     *     strengths:list<string>,
     *     improvements:list<string>,
     *     recommendations:list<string>,
     *     plan7Days:list<string>,
     *     nutritionSupport:array{
     *         focus:string,
     *         foods:list<string>,
     *         dishes:list<string>,
     *         note:string
     *     },
     *     tone:string,
     *     source:string
     * }|null
     */
    public function generateCoachInsight(array $report): ?array
    {
        $this->logger->info('Gemini coach client invoked.', [
            'model' => $this->model,
            'has_api_key' => trim($this->apiKey) !== '',
        ]);

        if (trim($this->apiKey) === '' || trim($this->model) === '') {
            $this->logger->warning('Gemini coach client skipped because configuration is incomplete.', [
                'has_api_key' => trim($this->apiKey) !== '',
                'has_model' => trim($this->model) !== '',
            ]);

            return null;
        }

        $requestOptions = [
            'query' => ['key' => $this->apiKey],
            'json' => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->buildPrompt($report)],
                        ],
                    ],
                ],
            ],
            'timeout' => self::REQUEST_TIMEOUT_SECONDS,
            'max_duration' => self::REQUEST_MAX_DURATION_SECONDS,
        ];

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $this->logger->info('Gemini coach HTTP request is about to be sent.', [
                    'model' => $this->model,
                    'attempt' => $attempt,
                    'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                    'max_duration' => self::REQUEST_MAX_DURATION_SECONDS,
                ]);

                $response = $this->httpClient->request('POST', sprintf(self::BASE_URL, rawurlencode($this->model)), $requestOptions);

                $statusCode = $response->getStatusCode();
                $rawBody = $response->getContent(false);
                $this->logger->info('Gemini coach HTTP response received.', [
                    'model' => $this->model,
                    'attempt' => $attempt,
                    'status_code' => $statusCode,
                    'raw_body' => $rawBody,
                ]);

                if ($statusCode !== 200) {
                    $this->logger->warning('Gemini coach request returned a non-200 response.', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                if (trim($rawBody) === '') {
                    $this->logger->warning('Gemini coach response body was empty.', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                    ]);

                    return null;
                }

                try {
                    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    $this->logger->warning('Gemini coach HTTP response contained invalid JSON.', [
                        'exception_class' => $exception::class,
                        'exception_message' => $exception->getMessage(),
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                if (!is_array($payload)) {
                    $this->logger->warning('Gemini coach HTTP response JSON was not an object.', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                $candidateText = $this->extractCandidateText($payload);
                if ($candidateText === '') {
                    $this->logger->warning('Gemini coach response did not contain candidate text.', [
                        'attempt' => $attempt,
                        'status_code' => $statusCode,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                try {
                    $coachPayload = json_decode($candidateText, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    $this->logger->warning('Gemini coach candidate text contained invalid JSON.', [
                        'exception_class' => $exception::class,
                        'exception_message' => $exception->getMessage(),
                        'attempt' => $attempt,
                        'candidate_text' => $candidateText,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                $insight = $this->normalizeInsight($coachPayload);
                if ($insight === null) {
                    $this->logger->warning('Gemini coach response JSON did not match the expected insight structure.', [
                        'attempt' => $attempt,
                        'candidate_text' => $candidateText,
                        'raw_body' => $rawBody,
                    ]);

                    return null;
                }

                if ($attempt > 1) {
                    $this->logger->info('Gemini coach retry succeeded.', [
                        'model' => $this->model,
                        'attempt' => $attempt,
                    ]);
                }

                return $insight;
            } catch (TransportExceptionInterface $exception) {
                $message = $exception->getMessage();
                $isTimeout = $this->isTimeoutException($message);

                $this->logger->warning($isTimeout ? 'Gemini coach request timed out.' : 'Gemini transport failed. Verify PHP curl extension and network access.', [
                    'exception_class' => $exception::class,
                    'exception_message' => $message,
                    'model' => $this->model,
                    'attempt' => $attempt,
                    'curl_loaded' => extension_loaded('curl'),
                    'likely_fopen_transport' => str_contains(strtolower($message), 'fopen') || !extension_loaded('curl'),
                ]);

                if ($isTimeout && $attempt === 1) {
                    $this->logger->info('Retrying Gemini coach request after timeout.', [
                        'model' => $this->model,
                        'next_attempt' => 2,
                    ]);
                    usleep(300000);

                    continue;
                }

                if ($isTimeout && $attempt === 2) {
                    $this->logger->warning('Gemini coach retry failed after timeout.', [
                        'model' => $this->model,
                        'attempt' => $attempt,
                    ]);
                }

                return null;
            } catch (\Throwable $exception) {
                $this->logger->warning('Gemini coach request failed unexpectedly.', [
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'model' => $this->model,
                    'attempt' => $attempt,
                ]);

                return null;
            }
        }

        return null;
    }

    public function generateCoachChatReply(string $prompt): ?string
    {
        $this->logger->info('Gemini coach chat client invoked.', [
            'model' => $this->model,
            'has_api_key' => trim($this->apiKey) !== '',
        ]);

        if (trim($this->apiKey) === '' || trim($this->model) === '' || trim($prompt) === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', sprintf(self::BASE_URL, rawurlencode($this->model)), [
                'query' => ['key' => $this->apiKey],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ],
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'max_duration' => self::REQUEST_MAX_DURATION_SECONDS,
            ]);

            $statusCode = $response->getStatusCode();
            $rawBody = $response->getContent(false);
            if ($statusCode !== 200 || trim($rawBody) === '') {
                $this->logger->warning('Gemini coach chat request returned an unusable response.', [
                    'status_code' => $statusCode,
                    'raw_body' => $rawBody,
                ]);

                return null;
            }

            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                return null;
            }

            $reply = $this->extractCandidateText($payload);

            return $reply !== '' ? $reply : null;
        } catch (\Throwable $exception) {
            $this->logger->warning('Gemini coach chat request failed.', [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'model' => $this->model,
            ]);

            return null;
        }
    }

    /** @param array<string,mixed> $report */
    private function buildPrompt(array $report): string
    {
        $reportJson = json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a calm, supportive wellness coach inside a mindfulness and exercise application called "Serinity".

Your role is NOT to generate random advice.
You must interpret real user activity data and provide meaningful, realistic, and personalized coaching.

You are helping a beginner or low-consistency user build a gentle and sustainable routine.

----------------------------------------
USER PERFORMANCE DATA
----------------------------------------
{$reportJson}

----------------------------------------
COACHING OBJECTIVES
----------------------------------------

1. Write a short, human, and motivating summary (2-3 sentences)
- Acknowledge progress, even if small
- Be encouraging but NOT exaggerated
- Avoid generic phrases like "great job" without context

2. Identify 2 strengths
- Based on real patterns (time, type, consistency)
- Be specific and grounded

3. Identify 2 improvement areas
- Frame them gently
- Focus on realistic progression
- Avoid negative or judgmental tone

4. Provide 3 personalized recommendations
Each recommendation must:
- be actionable
- be specific (time, type, or context)
- reflect the user's habits (evening, breathing, low activity, etc.)
- avoid vague phrases like "explore more"

5. Generate a realistic 7-day plan

VERY IMPORTANT RULES:
- Each day must include a SMALL but REAL action
- No "just open the app"
- No "no action needed"
- Keep actions simple but meaningful
- Vary durations (1-3 minutes max)
- Vary activity types (breathing, stretch, pause, body awareness)
- Adapt to a beginner level
- Build gentle progression (not intensity)

Examples of good actions:
- "Complete a 2-minute breathing reset in the evening"
- "Add a 1-minute body awareness pause in the afternoon"
- "Repeat your favorite exercise once today"
- "Try a short stretch after sitting for a long period"

Avoid:
- repetition of identical durations every day
- overly passive suggestions
- unrealistic plans

6. Add a brief "Nutrition support" section
This is light wellness support, not medical guidance.
It must:
- focus on food-first suggestions
- fit the user's activity pattern, such as low energy, inconsistent activity, evening stress, short sessions, recovery needs, or calm/sleep-oriented routines
- suggest simple foods and practical dish ideas
- avoid diagnosis, deficiency claims, supplement prescriptions, doses, or medical treatment language
- avoid saying the user "needs" a vitamin, mineral, supplement, or treatment
- use gentle conditional wording when mentioning nutrients

Suitable themes may include:
- iron-rich foods
- B12-rich foods
- folate-rich foods
- magnesium-rich foods
- vitamin D discussion only as a soft suggestion if fatigue remains persistent

Suitable foods include:
- eggs
- lentils
- spinach
- chickpeas
- yogurt
- nuts
- salmon
- oats
- bananas
- leafy greens

Suitable dishes include:
- lentil and spinach soup
- yogurt with oats and banana
- egg and avocado toast
- salmon with vegetables
- chickpea salad

Suitable note examples:
- "If tiredness stays high over time, iron, B12, folate, or vitamin D may be worth discussing with a healthcare professional."
- "Focus on regular meals and energy-supporting foods before considering supplements."
- "A balanced routine with nourishing meals may support energy and recovery."

----------------------------------------
OUTPUT FORMAT (STRICT JSON ONLY)
----------------------------------------

Return ONLY valid JSON with this exact structure:

{
  "summary": "string",
  "strengths": ["string", "string"],
  "improvements": ["string", "string"],
  "recommendations": ["string", "string", "string"],
  "plan7Days": ["string", "string", "string", "string", "string", "string", "string"],
  "nutritionSupport": {
    "focus": "string",
    "foods": ["string", "string", "string"],
    "dishes": ["string", "string"],
    "note": "string"
  },
  "tone": "supportive"
}

Do not add explanations, markdown, or extra text.
Only return valid JSON.
PROMPT;
    }

    /** @param array<string,mixed> $payload */
    private function extractCandidateText(array $payload): string
    {
        $text = '';
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }
        }

        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text);
    }

    /**
     * @return array{
     *     summary:string,
     *     strengths:list<string>,
     *     improvements:list<string>,
     *     recommendations:list<string>,
     *     plan7Days:list<string>,
     *     nutritionSupport:array{
     *         focus:string,
     *         foods:list<string>,
     *         dishes:list<string>,
     *         note:string
     *     },
     *     tone:string,
     *     source:string
     * }|null
     */
    private function normalizeInsight(mixed $payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $summary = $this->stringValue($payload['summary'] ?? null);
        $strengths = $this->stringList($payload['strengths'] ?? null, 2, 4);
        $improvements = $this->stringList($payload['improvements'] ?? null, 2, 4);
        $recommendations = $this->stringList($payload['recommendations'] ?? null, 3, 5);
        $plan7Days = $this->stringList($payload['plan7Days'] ?? null, 7, 7);
        $nutritionSupport = $this->nutritionSupport($payload['nutritionSupport'] ?? null);

        if ($summary === '' || count($strengths) < 2 || count($improvements) < 2 || count($recommendations) < 3 || count($plan7Days) !== 7 || $nutritionSupport === null) {
            return null;
        }

        return [
            'summary' => $summary,
            'strengths' => $strengths,
            'improvements' => $improvements,
            'recommendations' => $recommendations,
            'plan7Days' => $plan7Days,
            'nutritionSupport' => $nutritionSupport,
            'tone' => 'supportive',
            'source' => 'ai',
        ];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function isTimeoutException(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'idle timeout');
    }

    /** @return list<string> */
    private function stringList(mixed $value, int $minimum, int $maximum): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        $items = array_slice(array_values(array_unique($items)), 0, $maximum);

        return count($items) >= $minimum ? $items : [];
    }

    /**
     * @return array{
     *     focus:string,
     *     foods:list<string>,
     *     dishes:list<string>,
     *     note:string
     * }|null
     */
    private function nutritionSupport(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $focus = $this->stringValue($value['focus'] ?? null);
        $foods = $this->stringList($value['foods'] ?? null, 3, 5);
        $dishes = $this->stringList($value['dishes'] ?? null, 2, 4);
        $note = $this->stringValue($value['note'] ?? null);

        if ($focus === '' || count($foods) < 3 || count($dishes) < 2 || $note === '') {
            return null;
        }

        return [
            'focus' => $focus,
            'foods' => $foods,
            'dishes' => $dishes,
            'note' => $note,
        ];
    }
}
