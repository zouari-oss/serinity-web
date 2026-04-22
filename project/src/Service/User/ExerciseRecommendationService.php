<?php

declare(strict_types=1);

namespace App\Service\User;

final readonly class ExerciseRecommendationService
{
    /**
     * @param array{temperature:float|int,weatherCode:int,isDay:int,localTime:string,timezone:string,weatherLabel?:string} $weather
     * @param list<array<string,mixed>> $catalogRows
     * @return array{
     *     exerciseId:int|null,
     *     momentLabel:string,
     *     title:string,
     *     duration:string,
     *     level:string,
     *     whyHint:string,
     *     badges:list<string>,
     *     weatherLabel:string,
     *     bestTime:string,
     *     context:string,
     *     exerciseType:string,
     *     meditationFocus:string
     * }
     */
    public function recommend(array $weather, array $catalogRows, string $fatigue = 'moderate'): array
    {
        $hour = $this->extractHour((string) ($weather['localTime'] ?? '12:00'));
        $temperature = (float) ($weather['temperature'] ?? 22);
        $weatherCode = (int) ($weather['weatherCode'] ?? 0);
        $weatherLabel = (string) ($weather['weatherLabel'] ?? 'Mild weather');
        $momentLabel = $this->resolveMomentLabel($hour);
        $context = $this->resolveContext($hour, $temperature, $weatherCode);
        $selected = $this->selectExercise($catalogRows, $context, $fatigue);

        if ($selected === null) {
            return [
                'exerciseId' => null,
                'momentLabel' => $momentLabel,
                'title' => $this->buildDisplayTitle('Breathing Reset', $context, $fatigue),
                'duration' => '8 min',
                'level' => 'Level 1',
                'whyHint' => $this->buildWhyHint($context, $fatigue),
                'badges' => $this->buildBadges($momentLabel, $context, $fatigue, $weatherLabel),
                'weatherLabel' => $weatherLabel,
                'bestTime' => $this->buildBestTime($context, $fatigue),
                'context' => $context,
                'exerciseType' => 'RESPIRATION',
                'meditationFocus' => $this->buildMeditationFocus($context, $fatigue),
            ];
        }

        /** @var array<string,mixed> $exercise */
        $exercise = $selected['exercice'];

        return [
            'exerciseId' => (int) ($exercise['id'] ?? 0),
            'momentLabel' => $momentLabel,
            'title' => $this->buildDisplayTitle((string) ($exercise['title'] ?? 'Breathing Reset'), $context, $fatigue),
            'duration' => sprintf('%d min', max(1, (int) ($exercise['durationMinutes'] ?? 8))),
            'level' => sprintf('Level %d', max(1, (int) ($exercise['level'] ?? 1))),
            'whyHint' => $this->buildWhyHint($context, $fatigue),
            'badges' => $this->buildBadges($momentLabel, $context, $fatigue, $weatherLabel),
            'weatherLabel' => $weatherLabel,
            'bestTime' => $this->buildBestTime($context, $fatigue),
            'context' => $context,
            'exerciseType' => strtoupper((string) ($exercise['type'] ?? 'RESPIRATION')),
            'meditationFocus' => $this->buildMeditationFocus($context, $fatigue),
        ];
    }

    private function extractHour(string $localTime): int
    {
        $hour = (int) strtok($localTime, ':');

        return max(0, min(23, $hour));
    }

    private function resolveMomentLabel(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => 'Morning',
            $hour >= 12 && $hour < 18 => 'Afternoon',
            default => 'Evening',
        };
    }

    private function resolveContext(int $hour, float $temperature, int $weatherCode): string
    {
        if ($hour >= 18 || $hour < 5) {
            return 'evening';
        }

        if ($this->isRainyOrSevere($weatherCode)) {
            return 'rainy';
        }

        if ($temperature >= 29) {
            return 'hot';
        }

        if ($this->isClearOrFair($weatherCode)) {
            return 'clear_day';
        }

        return 'calm_day';
    }

    /**
     * @param list<array<string,mixed>> $catalogRows
     * @return array<string,mixed>|null
     */
    private function selectExercise(array $catalogRows, string $context, string $fatigue): ?array
    {
        if ($catalogRows === []) {
            return null;
        }

        $lastRecentId = $this->findMostRecentExerciseId($catalogRows);
        $lowRecentEngagement = !$this->hasRecentEngagement($catalogRows);
        $scored = [];

        foreach ($catalogRows as $item) {
            $exercise = is_array($item['exercice'] ?? null) ? $item['exercice'] : [];
            $exerciseId = (int) ($exercise['id'] ?? 0);
            $score = $this->scoreExercise($exercise, $context, $fatigue, $lowRecentEngagement);

            if ($exerciseId > 0 && $exerciseId === $lastRecentId) {
                $score -= 4.0;
            }

            $score -= $this->recentPenalty($item);
            $score += $this->rotationBonus($exerciseId, (string) ($exercise['title'] ?? ''));

            $scored[] = [
                'item' => $item,
                'score' => $score,
            ];
        }

        usort($scored, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        });

        return $scored[0]['item'] ?? null;
    }

    /**
     * @param array<string,mixed> $exercise
     */
    private function scoreExercise(array $exercise, string $context, string $fatigue, bool $lowRecentEngagement): float
    {
        $title = mb_strtolower((string) ($exercise['title'] ?? ''));
        $type = mb_strtolower((string) ($exercise['type'] ?? ''));
        $description = mb_strtolower((string) ($exercise['description'] ?? ''));
        $haystack = $title . ' ' . $type . ' ' . $description;
        $duration = (int) ($exercise['durationMinutes'] ?? 0);
        $level = (int) ($exercise['level'] ?? 0);

        $score = 0.0;

        if ($this->containsAny($haystack, ['breath', 'breathing', 'calm', 'relax', 'body scan'])) {
            $score += 2.25;
        }
        if ($this->containsAny($haystack, ['focus', 'energ', 'mindful', 'scan'])) {
            $score += 1.5;
        }
        if ($this->containsAny($haystack, ['reframe', 'cbt', 'thought'])) {
            $score += 1.0;
        }
        if ($duration > 0 && $duration <= 10) {
            $score += 0.75;
        }
        if ($level > 0 && $level <= 2) {
            $score += 0.75;
        }

        if ($lowRecentEngagement) {
            if ($duration <= 10) {
                $score += 1.5;
            }
            if ($level <= 2) {
                $score += 1.0;
            }
        }

        $score += match ($fatigue) {
            'high' => $this->fatigueHighScore($haystack, $duration, $level),
            'low' => $this->fatigueLowScore($haystack, $duration, $level),
            default => $this->fatigueModerateScore($haystack, $duration, $level),
        };

        return $score + match ($context) {
            'evening' => $this->containsAny($haystack, ['breath', 'breathing', 'calm', 'relax', 'body scan']) ? 3.0 : 0.0,
            'rainy' => $this->containsAny($haystack, ['breath', 'breathing', 'calm', 'relax', 'stretch']) ? 2.25 : 0.0,
            'hot' => ($duration <= 10 ? 1.75 : -0.5) + ($level <= 1 ? 1.25 : -0.5),
            'clear_day' => $this->containsAny($haystack, ['focus', 'energ', 'mindful', 'scan']) ? 2.5 : 0.0,
            default => $this->containsAny($haystack, ['focus', 'scan', 'reframe']) ? 1.25 : 0.0,
        };
    }

    private function fatigueHighScore(string $haystack, int $duration, int $level): float
    {
        $score = 0.0;

        if ($this->containsAny($haystack, ['breath', 'breathing', 'calm', 'relax', 'body scan'])) {
            $score += 3.25;
        }
        if ($duration <= 10) {
            $score += 1.75;
        }
        if ($level <= 1) {
            $score += 1.75;
        }
        if ($duration > 15) {
            $score -= 1.5;
        }
        if ($level >= 3) {
            $score -= 2.0;
        }

        return $score;
    }

    private function fatigueModerateScore(string $haystack, int $duration, int $level): float
    {
        $score = 0.0;

        if ($this->containsAny($haystack, ['focus', 'scan', 'mindful', 'reframe'])) {
            $score += 1.75;
        }
        if ($duration >= 8 && $duration <= 15) {
            $score += 1.0;
        }
        if ($level >= 1 && $level <= 2) {
            $score += 0.75;
        }

        return $score;
    }

    private function fatigueLowScore(string $haystack, int $duration, int $level): float
    {
        $score = 0.0;

        if ($this->containsAny($haystack, ['focus', 'energ', 'mindful', 'scan'])) {
            $score += 2.75;
        }
        if ($duration >= 8 && $duration <= 18) {
            $score += 0.75;
        }
        if ($level >= 2) {
            $score += 0.75;
        }

        return $score;
    }

    /**
     * @param list<array<string,mixed>> $catalogRows
     */
    private function hasRecentEngagement(array $catalogRows): bool
    {
        foreach ($catalogRows as $item) {
            $activityAt = $this->extractActivityDateTime($item);
            if (!$activityAt instanceof \DateTimeImmutable) {
                continue;
            }

            $hours = (time() - $activityAt->getTimestamp()) / 3600;
            if ($hours <= 168) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $catalogRows
     */
    private function findMostRecentExerciseId(array $catalogRows): int
    {
        $recentId = 0;
        $recentAt = null;

        foreach ($catalogRows as $item) {
            $activityAt = $this->extractActivityDateTime($item);
            if (!$activityAt instanceof \DateTimeImmutable) {
                continue;
            }

            if (!$recentAt instanceof \DateTimeImmutable || $activityAt > $recentAt) {
                $recentAt = $activityAt;
                $recentId = (int) (($item['exercice']['id'] ?? 0));
            }
        }

        return $recentId;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function recentPenalty(array $item): float
    {
        $activityAt = $this->extractActivityDateTime($item);
        if (!$activityAt instanceof \DateTimeImmutable) {
            return 0.0;
        }

        $hours = (time() - $activityAt->getTimestamp()) / 3600;
        if ($hours <= 24) {
            return 2.5;
        }
        if ($hours <= 72) {
            return 1.25;
        }

        return 0.0;
    }

    /**
     * @param array<string,mixed> $item
     */
    private function extractActivityDateTime(array $item): ?\DateTimeImmutable
    {
        foreach (['completedAt', 'startedAt', 'assignedAt'] as $field) {
            $value = $item[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function rotationBonus(int $exerciseId, string $title): float
    {
        $seed = $exerciseId > 0 ? (string) $exerciseId : $title;
        $hash = crc32(date('Y-m-d-H') . '-' . $seed);

        return ($hash % 100) / 1000;
    }

    private function buildDisplayTitle(string $title, string $context, string $fatigue): string
    {
        if ($title === '') {
            return 'Breathing Reset';
        }

        $prefix = match (true) {
            $fatigue === 'high' && $context === 'evening' => 'Evening',
            $fatigue === 'high' => 'Gentle',
            $context === 'rainy' => 'Indoor',
            $context === 'hot' => 'Light',
            $context === 'clear_day' && $fatigue === 'low' => 'Focus',
            $context === 'clear_day' => 'Daytime',
            default => '',
        };

        if ($prefix === '' || str_starts_with($title, $prefix . ' ')) {
            return $title;
        }

        return $prefix . ' ' . $title;
    }

    private function buildWhyHint(string $context, string $fatigue): string
    {
        return match (true) {
            $fatigue === 'high' && $context === 'evening' => 'You seem ready for a softer evening plan, so this keeps the effort light and grounding.',
            $fatigue === 'high' => 'Your energy looks low right now, so a gentle session is the best way to stay consistent without pushing too hard.',
            $context === 'rainy' => 'The weather points to an indoor, steady-paced session that helps you reset without overloading your attention.',
            $context === 'hot' => 'Warmer conditions make a shorter and lighter session feel more supportive right now.',
            $context === 'clear_day' && $fatigue === 'low' => 'You have enough energy for something a little more uplifting, and the daytime conditions support that.',
            $context === 'clear_day' => 'The current weather and time of day make this a good moment to refocus and build gentle momentum.',
            default => 'This plan balances your current rhythm, the weather outside, and your recent exercise pattern.',
        };
    }

    /**
     * @return list<string>
     */
    private function buildBadges(string $momentLabel, string $context, string $fatigue, string $weatherLabel): array
    {
        $badges = [
            $momentLabel,
            match ($fatigue) {
                'high' => 'Gentle',
                'low' => 'Energizing',
                default => 'Balanced',
            },
            match ($context) {
                'rainy', 'hot' => 'Indoor',
                'evening' => 'Relax',
                'clear_day' => $weatherLabel,
                default => 'Steady',
            },
        ];

        return array_values(array_unique(array_filter($badges, static fn(string $badge): bool => $badge !== '')));
    }

    private function buildBestTime(string $context, string $fatigue): string
    {
        return match (true) {
            $fatigue === 'high' && $context === 'evening' => 'Tonight',
            $fatigue === 'high' => 'After a short break',
            $context === 'clear_day' && $fatigue === 'low' => 'Now',
            $context === 'evening' => 'This evening',
            default => 'Now',
        };
    }

    private function buildMeditationFocus(string $context, string $fatigue): string
    {
        return match (true) {
            $fatigue === 'high' && $context === 'evening' => 'sleep relaxation breathing',
            $fatigue === 'high' => 'gentle breathing relaxation',
            $context === 'rainy' => 'indoor calm breathing meditation',
            $context === 'hot' => 'light reset breathing meditation',
            $context === 'clear_day' && $fatigue === 'low' => 'focus energy meditation',
            default => 'mindful reset meditation',
        };
    }

    private function isRainyOrSevere(int $weatherCode): bool
    {
        return in_array($weatherCode, [
            45, 48, 51, 53, 55, 56, 57,
            61, 63, 65, 66, 67, 71, 73, 75, 77,
            80, 81, 82, 85, 86, 95, 96, 99,
        ], true);
    }

    private function isClearOrFair(int $weatherCode): bool
    {
        return in_array($weatherCode, [0, 1, 2, 3], true);
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
