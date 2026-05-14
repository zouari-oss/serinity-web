<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\User;
use App\Enum\AccountStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractUserUiController extends AbstractController
{
    protected function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($user->getRole(), ['PATIENT', 'THERAPIST'], true)) {
            throw $this->createAccessDeniedException();
        }
        if ($user->getAccountStatus() === AccountStatus::DISABLED->value) {
            throw $this->createAccessDeniedException('Your account is disabled.');
        }

        return $user;
    }

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
    protected function buildNav(string $activeRoute): array
    {
        $user = $this->currentUser();
        $consultationItem = $user->getRole() === 'THERAPIST'
            ? [
                'label' => 'Gestion des rendez-vous',
                'route' => 'app_therapist_rdv',
                'icon' => 'calendar_month',
                'section' => 'modules',
            ]
            : [
                'label' => 'Consultations',
                'route' => 'user_ui_consultations',
                'icon' => 'medical_services',
                'section' => 'modules',
                'children' => [
                    [
                        'label' => 'Doctors',
                        'route' => 'app_doctors',
                        'icon' => 'people',
                    ],
                    [
                        'label' => 'Mes rendez vous',
                        'route' => 'app_patient_rdv',
                        'icon' => 'calendar_month',
                    ],
                    [
                        'label' => 'Psychological AI',
                        'route' => 'app_rdv_disease_ai',
                        'icon' => 'psychology',
                    ],
                ],
            ];

        $items = [
            ['label' => 'Dashboard', 'route' => 'user_ui_dashboard', 'icon' => 'dashboard', 'section' => 'home'],
            ['label' => 'Profile', 'route' => 'user_ui_profile', 'icon' => 'person', 'section' => 'home'],
            ['label' => 'Settings', 'route' => 'user_ui_settings', 'icon' => 'settings', 'section' => 'home'],
            $consultationItem,
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
                    ['label' => 'gestion sommeil ', 'route' => 'app_sommeil_list', 'icon' => 'bedtime'],
                    ['label' => 'gestion Reves ', 'route' => 'app_reve_index', 'icon' => 'nights_stay'],
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

            return [
                ...$item,
                'children' => $mappedChildren,
                'active' => $item['route'] === $activeRoute
                    || array_any($mappedChildren, static fn(array $child): bool => $child['active']),
            ];
        }, $items);
    }
}
