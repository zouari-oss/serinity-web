<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccessControlUiController extends AbstractController
{
    #[Route('/login', name: 'ac_ui_login', methods: ['GET'])]
    #[Route('/register', name: 'ac_ui_register', methods: ['GET'])]
    public function login(Request $request): Response
    {
        $mode = $request->query->get('mode');
        if (!in_array($mode, ['signin', 'signup'], true)) {
            $mode = $request->attributes->get('_route') === 'ac_ui_register' ? 'signup' : 'signin';
        }

        return $this->render('access_control/pages/login.html.twig', [
            'mode' => $mode,
        ]);
    }

    #[Route('/reset-password', name: 'ac_ui_reset_password', methods: ['GET'])]
    public function resetPassword(): Response
    {
        return $this->render('access_control/pages/reset_password.html.twig');
    }

    #[Route('/dashboard', name: 'ac_ui_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('access_control/pages/dashboard.html.twig', [
            'nav' => $this->buildNav('ac_ui_dashboard'),
            'userName' => 'Admin User',
            'stats' => [
                'users' => 184,
                'sessions' => 31,
                'audits' => 768,
                'profileCompletion' => 82,
            ],
            'recentActivity' => [
                ['2026-04-01 10:42', 'omar.admin', 'USER_LOGIN', 'Success'],
                ['2026-04-01 10:39', 'thera_01', 'PASSWORD_CHANGED', 'Success'],
                ['2026-04-01 10:20', 'patient_42', 'TOKEN_REFRESH', 'Success'],
            ],
        ]);
    }

    #[Route('/users', name: 'ac_ui_users', methods: ['GET'])]
    public function users(): Response
    {
        return $this->render('access_control/pages/user_management.html.twig', [
            'nav' => $this->buildNav('ac_ui_users'),
            'userName' => 'Admin User',
            'users' => [
                ['omar.admin', 'omar@serinity.org', 'ADMIN', 'ACTIVE', 'ONLINE'],
                ['thera_01', 'therapist@serinity.org', 'THERAPIST', 'ACTIVE', 'ONLINE'],
                ['patient_42', 'patient@serinity.org', 'PATIENT', 'ACTIVE', 'OFFLINE'],
            ],
        ]);
    }

    #[Route('/profile', name: 'ac_ui_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('access_control/pages/profile.html.twig', [
            'nav' => $this->buildNav('ac_ui_profile'),
            'userName' => 'Admin User',
            'profile' => [
                'username' => 'omar.admin',
                'email' => 'omar@serinity.org',
                'firstName' => 'Omar',
                'lastName' => 'Zouari',
                'country' => 'Tunisia',
                'state' => 'Tunis',
                'aboutMe' => 'Building reliable mental health tooling.',
            ],
        ]);
    }

    #[Route('/sessions', name: 'ac_ui_sessions', methods: ['GET'])]
    public function sessions(): Response
    {
        return $this->render('access_control/pages/sessions.html.twig', [
            'nav' => $this->buildNav('ac_ui_sessions'),
            'userName' => 'Admin User',
            'sessions' => [
                ['omar.admin', '2026-04-01 09:10', '2026-04-02 09:10', 'No'],
                ['thera_01', '2026-03-31 20:01', '2026-04-07 20:01', 'No'],
                ['patient_42', '2026-03-28 08:13', '2026-04-04 08:13', 'Yes'],
            ],
        ]);
    }

    #[Route('/audit-logs', name: 'ac_ui_audit_logs', methods: ['GET'])]
    public function auditLogs(): Response
    {
        return $this->render('access_control/pages/audit_logs.html.twig', [
            'nav' => $this->buildNav('ac_ui_audit_logs'),
            'userName' => 'Admin User',
            'auditLogs' => [
                ['2026-04-01 10:42', 'USER_LOGIN', '127.0.0.1', 'localhost', 'Linux'],
                ['2026-04-01 10:39', 'PASSWORD_CHANGED', '127.0.0.1', 'localhost', 'Linux'],
                ['2026-04-01 10:20', 'TOKEN_REFRESH', '127.0.0.1', 'localhost', 'Linux'],
            ],
        ]);
    }

    /** @return list<array{label: string, route: string, active: bool}> */
    private function buildNav(string $activeRoute): array
    {
        $items = [
            ['label' => 'Dashboard', 'route' => 'ac_ui_dashboard'],
            ['label' => 'Users', 'route' => 'ac_ui_users'],
            ['label' => 'Profile', 'route' => 'ac_ui_profile'],
            ['label' => 'Sessions', 'route' => 'ac_ui_sessions'],
            ['label' => 'Audit logs', 'route' => 'ac_ui_audit_logs'],
        ];

        return array_map(
            static fn(array $item): array => [
                'label' => $item['label'],
                'route' => $item['route'],
                'active' => $item['route'] === $activeRoute,
            ],
            $items,
        );
    }
}
