<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Admin\AdminSleepService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminSleepUiController extends AbstractController
{
    public function __construct(
        private readonly AdminSleepService $adminSleepService,
    ) {
    }

    #[Route('/admin/sleep', name: 'ac_ui_sleep_legacy', methods: ['GET'])]
    public function legacyRedirect(): Response
    {
        return $this->redirectToRoute('ac_ui_sleep');
    }

    #[Route('/admin/sommeil', name: 'ac_ui_sleep', methods: ['GET'])]
    public function sommeil(Request $request): Response
    {
        return $this->render('access_control/pages/sleep_overview.html.twig', [
            'nav' => $this->buildNav('ac_ui_sleep'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'summary' => $this->adminSleepService->summary(),
            'sessions' => $this->adminSleepService->sessions(
                $this->queryString($request, 'q'),
                $this->queryString($request, 'quality'),
            ),
            'qualities' => array_values(\App\Entity\SleepSession::QUALITY_LABELS),
        ]);
    }

    #[Route('/admin/reve', name: 'ac_ui_sleep_reves', methods: ['GET'])]
    public function reves(Request $request): Response
    {
        return $this->render('access_control/pages/sleep_dreams.html.twig', [
            'nav' => $this->buildNav('ac_ui_sleep_reves'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'summary' => $this->adminSleepService->summary(),
            'dreams' => $this->adminSleepService->dreams(
                $this->queryString($request, 'q'),
                $this->queryString($request, 'type'),
            ),
            'dreamTypes' => \App\Entity\SleepDream::DREAM_TYPES,
        ]);
    }

    /**
     * @return list<array{section: string, label: string, route: string, icon: string, active: bool, children?: list<array{label: string, route: string, icon: string, active: bool}>}>
     */
    private function buildNav(string $activeRoute): array
    {
        $moodChildRoutes = ['ac_ui_mood', 'ac_ui_emotion', 'ac_ui_influence'];
        $sleepChildRoutes = ['ac_ui_sleep', 'ac_ui_sleep_reves'];
        $items = [
            ['section' => 'Admin self-management', 'label' => 'Dashboard', 'route' => 'ac_ui_dashboard', 'icon' => 'dashboard'],
            ['section' => 'Admin self-management', 'label' => 'Profile', 'route' => 'ac_ui_profile', 'icon' => 'person'],
            ['section' => 'Admin self-management', 'label' => 'Sessions', 'route' => 'ac_ui_sessions', 'icon' => 'devices'],
            ['section' => 'Admin self-management', 'label' => 'Audit logs', 'route' => 'ac_ui_audit_logs', 'icon' => 'history'],
            ['section' => 'Users management', 'label' => 'Users', 'route' => 'ac_ui_users', 'icon' => 'group'],
            ['section' => 'Users management', 'label' => 'Consultations', 'route' => 'ac_ui_consultations', 'icon' => 'medical_services'],
            ['section' => 'Users management', 'label' => 'Exercises', 'route' => 'ac_ui_exercises', 'icon' => 'self_improvement'],
            ['section' => 'Users management', 'label' => 'Forum', 'route' => 'ac_ui_forum', 'icon' => 'forum'],
            [
                'section' => 'Users management',
                'label' => 'Mood',
                'route' => 'ac_ui_mood',
                'icon' => 'mood',
                'children' => [
                    ['label' => 'Mood analytics', 'route' => 'ac_ui_mood', 'icon' => 'analytics'],
                    ['label' => 'Emotion management', 'route' => 'ac_ui_emotion', 'icon' => 'sentiment_satisfied'],
                    ['label' => 'Influence management', 'route' => 'ac_ui_influence', 'icon' => 'tune'],
                ],
            ],
            [
                'section' => 'Users management',
                'label' => 'Sleep',
                'route' => 'ac_ui_sleep',
                'icon' => 'hotel',
                'children' => [
                    ['label' => 'Sommeil', 'route' => 'ac_ui_sleep', 'icon' => 'bedtime'],
                    ['label' => 'Reves management', 'route' => 'ac_ui_sleep_reves', 'icon' => 'nights_stay'],
                ],
            ],
        ];

        return array_map(
            static function (array $item) use ($activeRoute, $moodChildRoutes, $sleepChildRoutes): array {
                $isMoodGroup = $item['route'] === 'ac_ui_mood' && isset($item['children']);
                $isSleepGroup = $item['route'] === 'ac_ui_sleep' && isset($item['children']);
                $active = $item['route'] === $activeRoute;

                if ($isMoodGroup) {
                    $active = in_array($activeRoute, $moodChildRoutes, true);
                } elseif ($isSleepGroup) {
                    $active = in_array($activeRoute, $sleepChildRoutes, true);
                }

                if (!isset($item['children'])) {
                    return [
                        'section' => $item['section'],
                        'label' => $item['label'],
                        'route' => $item['route'],
                        'icon' => $item['icon'],
                        'active' => $active,
                    ];
                }

                return [
                    'section' => $item['section'],
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'active' => $active,
                    'children' => array_map(
                        static fn(array $child): array => [
                            'label' => $child['label'],
                            'route' => $child['route'],
                            'icon' => $child['icon'],
                            'active' => $child['route'] === $activeRoute,
                        ],
                        $item['children'],
                    ),
                ];
            },
            $items,
        );
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if (!is_scalar($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
