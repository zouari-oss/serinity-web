<?php

namespace App\Service\Sleep;

class SleepAdviceService
{
    /**
     * @param array{
     *     current?: array{
     *         temp?: int|float|null,
     *         humidity?: int|float|null,
     *         wind_speed?: int|float|null,
     *         desc?: string
     *     }
     * }|null $weatherData
     *
     * @return array{
     *     score: int|null,
     *     niveau: string,
     *     resume: string,
     *     conseils: array<int, string>,
     *     badge: array{
     *         label: string,
     *         class: string,
     *         emoji: string
     *     }
     * }
     */
    public function analyze(?array $weatherData): array
    {
        if (!$weatherData ) {
            return [
                'score' => null,
                'niveau' => 'Indisponible',
                'resume' => 'Impossible d’analyser la météo pour le sommeil.',
                'conseils' => [],
                'badge' => [
                    'label' => 'Indisponible',
                    'class' => 'slw-badge--neutral',
                    'emoji' => '⚪',
                ],
            ];
        }

        /** @var array<string, mixed> $current */
        $current = $weatherData['current'];

        $score = 100;

        /** @var array<int, string> $conseils */
        $conseils = [];

        $temp = $current['temp'] ?? null;
        $humidity = $current['humidity'] ?? null;
        $wind = $current['wind_speed'] ?? null;
        $desc = mb_strtolower($current['desc'] ?? '');

        if ($temp !== null) {
            if ($temp > 24) {
                $score -= 20;
                $conseils[] = 'La température est élevée : aère la chambre avant de dormir.';
            } elseif ($temp < 16) {
                $score -= 10;
                $conseils[] = 'La température est basse : adapte la couverture.';
            } else {
                $conseils[] = 'La température actuelle est favorable au sommeil.';
            }
        }

        if ($humidity !== null) {
            if ($humidity > 70) {
                $score -= 15;
                $conseils[] = 'L’humidité est élevée : pense à ventiler la chambre.';
            } elseif ($humidity < 30) {
                $score -= 10;
                $conseils[] = 'L’air est sec : un léger humidificateur peut aider.';
            } else {
                $conseils[] = 'Le niveau d’humidité est correct.';
            }
        }

        if ($wind !== null && $wind > 10) {
            $score -= 8;
            $conseils[] = 'Le vent est notable : attention au bruit si la fenêtre reste ouverte.';
        }

        if (str_contains($desc, 'orage') || str_contains($desc, 'forte pluie')) {
            $score -= 10;
            $conseils[] = 'La météo agitée peut gêner l’endormissement : privilégie un environnement calme.';
        }

        $score = max(0, min(100, $score));

        if ($score >= 85) {
            $niveau = 'Excellent';
            $badge = ['label' => 'Excellent', 'class' => 'slw-badge--excellent', 'emoji' => '🟢'];
            $resume = 'Conditions météo très favorables pour une bonne nuit.';
        } elseif ($score >= 70) {
            $niveau = 'Bon';
            $badge = ['label' => 'Bon', 'class' => 'slw-badge--good', 'emoji' => '🟡'];
            $resume = 'Conditions globalement correctes pour dormir.';
        } elseif ($score >= 50) {
            $niveau = 'Moyen';
            $badge = ['label' => 'Moyen', 'class' => 'slw-badge--medium', 'emoji' => '🟠'];
            $resume = 'Quelques paramètres météo peuvent affecter ton confort.';
        } else {
            $niveau = 'Peu favorable';
            $badge = ['label' => 'Peu favorable', 'class' => 'slw-badge--bad', 'emoji' => '🔴'];
            $resume = 'La météo risque de perturber le sommeil.';
        }

        return [
            'score' => $score,
            'niveau' => $niveau,
            'resume' => $resume,
            'conseils' => $conseils,
            'badge' => $badge,
        ];
    }
}