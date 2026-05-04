<?php

declare(strict_types=1);

namespace App\Service\User;

final class UserNavService
{
    /**
     * @return list<array{
     *     label:string,
     *     route:string,
     *     icon:string,
     *     section:string,
     *     active:bool,
     *     children?:list<array{label:string,route:string,icon:string,active:bool}>
     * }>
     */
    public function build(string $activeRoute): array
    {
        $items = [
            ['label' => 'Dashboard', 'route' => 'user_ui_dashboard', 'icon' => 'dashboard', 'section' => 'home'],
            ['label' => 'Profile', 'route' => 'user_ui_profile', 'icon' => 'person', 'section' => 'home'],
            ['label' => 'Settings', 'route' => 'user_ui_settings', 'icon' => 'settings', 'section' => 'home'],
            ['label' => 'Consultations', 'route' => 'user_ui_consultations', 'icon' => 'medical_services', 'section' => 'modules'],
            ['label' => 'Exercises', 'route' => 'user_ui_exercises', 'icon' => 'fitness_center', 'section' => 'modules'],
            ['label' => 'Forum', 'route' => 'user_ui_forum', 'icon' => 'forum', 'section' => 'modules'],
            [
                'label' => 'Mood',
                'route' => 'user_ui_mood',
                'icon' => 'mood',
                'section' => 'modules',
                'children' => [
                    ['label' => 'Mood entries', 'route' => 'user_ui_mood', 'icon' => 'list'],
                    ['label' => 'Journal', 'route' => 'user_ui_journal_entry', 'icon' => 'edit_note'],
                    ['label' => 'Insights', 'route' => 'user_ui_mood_insights', 'icon' => 'insights'],
                    ['label' => 'Recovery plan', 'route' => 'user_ui_mood_recovery_plan', 'icon' => 'healing'],
                ],
            ],
            [
                'label' => 'Sleep',
                'route' => 'app_sommeil_list',
                'icon' => 'bedtime',
                'section' => 'modules',
                'children' => [
                    ['label' => 'Sommeil', 'route' => 'app_sommeil_list', 'icon' => 'bedtime'],
                    ['label' => 'Reve ', 'route' => 'app_reve_index', 'icon' => 'nights_stay'],
                ],
            ],
        ];

        return array_map(static function (array $item) use ($activeRoute): array {
            $children = $item['children'] ?? [];
            $mappedChildren = array_map(
                static fn(array $child): array => [
                    ...$child,
                    'active' => $child['route'] === $activeRoute,
                ],
                $children,
            );

            $hasActiveChild = false;
            foreach ($mappedChildren as $child) {
                if ($child['active']) {
                    $hasActiveChild = true;
                    break;
                }
            }

            return [
                ...$item,
                'children' => $mappedChildren,
                'active' => $item['route'] === $activeRoute || $hasActiveChild,
            ];
        }, $items);
    }
}
