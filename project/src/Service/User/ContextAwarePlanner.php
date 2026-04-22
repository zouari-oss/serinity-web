<?php

declare(strict_types=1);

namespace App\Service\User;

final readonly class ContextAwarePlanner
{
    public function __construct(
        private ExerciseRecommendationService $exerciseRecommendationService,
        private FatigueResolver $fatigueResolver,
    ) {
    }

    /**
     * @param array{temperature:float|int,weatherCode:int,isDay:int,localTime:string,timezone:string,weatherLabel?:string} $weather
     * @param list<array<string,mixed>> $catalogRows
     * @return array{
     *     title:string,
     *     fatigue:array{value:string,label:string},
     *     recommendation:array{
     *         exerciseId:int|null,
     *         momentLabel:string,
     *         title:string,
     *         duration:string,
     *         level:string,
     *         whyHint:string,
     *         badges:list<string>,
     *         weatherLabel:string,
     *         bestTime:string,
     *         context:string,
     *         exerciseType:string,
     *         meditationFocus:string
     *     },
     *     support:array{title:string,detail:string},
     *     meditation:array{title:string,detail:string},
     *     videoIntro:string,
     *     youtubeQuery:string
     * }
     */
    public function build(array $weather, array $catalogRows, string $fatigue): array
    {
        $resolvedFatigue = $this->fatigueResolver->resolve($fatigue);
        $recommendation = $this->exerciseRecommendationService->recommend($weather, $catalogRows, $resolvedFatigue);
        $context = (string) $recommendation['context'];

        return [
            'title' => 'Your plan for now',
            'fatigue' => [
                'value' => $resolvedFatigue,
                'label' => $this->fatigueResolver->label($resolvedFatigue),
            ],
            'recommendation' => $recommendation,
            'support' => $this->buildSupport($context, $resolvedFatigue),
            'meditation' => $this->buildMeditation($context, $resolvedFatigue, (string) $recommendation['meditationFocus']),
            'videoIntro' => 'A few guided options matched to the plan you need right now.',
            'youtubeQuery' => $this->buildYoutubeQuery($recommendation, $resolvedFatigue),
        ];
    }

    /**
     * @return array{title:string,detail:string}
     */
    private function buildSupport(string $context, string $fatigue): array
    {
        return match (true) {
            $fatigue === FatigueResolver::HIGH && $context === 'evening' => [
                'title' => 'Keep tonight very light',
                'detail' => 'A short indoor session is enough. Focus on ease, slower breathing, and giving your mind a clear signal that the day can wind down.',
            ],
            $fatigue === FatigueResolver::HIGH => [
                'title' => 'Stay gentle with yourself',
                'detail' => 'Choose a shorter session and keep the pace soft. A little consistency will help more than trying to do too much at once.',
            ],
            $context === 'rainy' || $context === 'hot' => [
                'title' => 'Indoor plan works best',
                'detail' => 'Today looks better for an indoor reset. Keeping things simple will make it easier to start and finish the session comfortably.',
            ],
            $fatigue === FatigueResolver::LOW && $context === 'clear_day' => [
                'title' => 'You have room for momentum',
                'detail' => 'Your current energy and the weather both support a more uplifting plan, while still keeping the effort practical and manageable.',
            ],
            default => [
                'title' => 'Aim for steady progress',
                'detail' => 'This plan is designed to feel realistic right now, so you can build rhythm without turning the session into another source of pressure.',
            ],
        };
    }

    /**
     * @return array{title:string,detail:string}
     */
    private function buildMeditation(string $context, string $fatigue, string $focus): array
    {
        $title = match (true) {
            $fatigue === FatigueResolver::HIGH && $context === 'evening' => 'Sleep-friendly breathing',
            $fatigue === FatigueResolver::HIGH => 'Gentle reset meditation',
            $context === 'rainy' => 'Grounding indoor meditation',
            $context === 'hot' => 'Cooling breath break',
            $fatigue === FatigueResolver::LOW && $context === 'clear_day' => 'Focus primer',
            default => 'Mindful reset',
        };

        $detail = match (true) {
            $fatigue === FatigueResolver::HIGH && $context === 'evening' => 'Follow the exercise with 4 to 6 slow breathing cycles and keep your attention on longer exhales.',
            $fatigue === FatigueResolver::HIGH => 'A brief guided breathing track can help you settle before or after the exercise without draining the energy you have left.',
            $context === 'rainy' => 'Pick a calm guided practice that keeps your attention indoors and away from scattered distractions.',
            $context === 'hot' => 'Choose a shorter meditation with slow nasal breathing to help your body ease into a lighter pace.',
            $fatigue === FatigueResolver::LOW && $context === 'clear_day' => 'A short focus meditation can help you turn your available energy into a cleaner, more intentional session.',
            default => sprintf('If you want extra support, add a short %s track after the exercise.', str_replace('_', ' ', $focus)),
        };

        return [
            'title' => $title,
            'detail' => $detail,
        ];
    }

    /**
     * @param array{
     *     momentLabel:string,
     *     context:string,
     *     exerciseType:string,
     *     meditationFocus:string
     * } $recommendation
     */
    private function buildYoutubeQuery(array $recommendation, string $fatigue): string
    {
        $parts = [
            strtolower((string) $recommendation['momentLabel']),
            match ($fatigue) {
                FatigueResolver::HIGH => 'low energy',
                FatigueResolver::LOW => 'energy boost',
                default => 'balanced energy',
            },
            strtolower(str_replace('_', ' ', (string) $recommendation['exerciseType'])),
            strtolower(str_replace('_', ' ', (string) $recommendation['meditationFocus'])),
        ];

        if (in_array((string) $recommendation['context'], ['rainy', 'hot'], true)) {
            $parts[] = 'indoor';
        }

        return trim(implode(' ', array_unique(array_filter($parts, static fn(string $part): bool => $part !== ''))));
    }
}
