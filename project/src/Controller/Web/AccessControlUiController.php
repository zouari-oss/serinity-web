<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Dto\Admin\UserFilterRequest;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploadService;
use App\Service\Admin\DashboardService;
use App\Service\Admin\UserManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

final class AccessControlUiController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly UserManagementService $userManagementService,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly ImageUploadService $imageUploadService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

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

    #[Route('/dashboard', name: 'ac_ui_dashboard_legacy', methods: ['GET'])]
    public function dashboardLegacy(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('ac_ui_login');
        }

        if ($user->getRole() === 'ADMIN') {
            return $this->redirectToRoute('ac_ui_dashboard');
        }

        if (in_array($user->getRole(), ['PATIENT', 'THERAPIST'], true)) {
            return $this->redirectToRoute('user_ui_dashboard');
        }

        return $this->redirectToRoute('ac_ui_login');
    }

    #[Route('/admin/dashboard', name: 'ac_ui_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('ac_ui_login');
        }

        if ($user->getRole() !== 'ADMIN') {
            return $this->redirectToRoute('user_ui_dashboard');
        }

        $stats = $this->dashboardService->getStatistics();
        $recentActivity = $this->dashboardService->getRecentActivity(10);

        return $this->render('access_control/pages/dashboard.html.twig', [
            'nav' => $this->buildNav('ac_ui_dashboard'),
            'userName' => $user->getEmail(),
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ]);
    }

    #[Route('/admin/users', name: 'ac_ui_users', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function users(Request $request): Response
    {
        $currentUser = $this->getUser();
        $filterRequest = new UserFilterRequest(
            page: max(1, (int) $request->query->get('page', 1)),
            limit: 20,
            email: $request->query->get('email'),
            role: $request->query->get('role'),
            accountStatus: $request->query->get('accountStatus'),
        );

        $result = $this->userManagementService->getUsersPaginated($filterRequest);

        // Format users for template
        $users = array_map(function ($user) {
            $profile = $user->getProfile();
            return [
                'id' => $user->getId(),
                'username' => $profile?->getUsername() ?? 'N/A',
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'accountStatus' => $user->getAccountStatus(),
                'presenceStatus' => $user->getPresenceStatus(),
                'firstName' => $profile?->getFirstName() ?? '',
                'lastName' => $profile?->getLastName() ?? '',
                'country' => $profile?->getCountry() ?? '',
                'state' => $profile?->getState() ?? '',
                'aboutMe' => $profile?->getAboutMe() ?? '',
                'profileImageUrl' => $profile?->getProfileImageUrl() ?? '',
            ];
        }, array_filter(
            $result['users'],
            static fn($user) => !($currentUser instanceof User) || $user->getId() !== $currentUser->getId(),
        ));

        return $this->render('access_control/pages/user_management.html.twig', [
            'nav' => $this->buildNav('ac_ui_users'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'users' => $users,
            'pagination' => [
                'page' => $result['page'],
                'totalPages' => $result['totalPages'],
                'total' => max(0, $result['total'] - ($currentUser instanceof User ? 1 : 0)),
            ],
            'filters' => [
                'email' => $filterRequest->email,
                'role' => $filterRequest->role,
                'accountStatus' => $filterRequest->accountStatus,
            ],
        ]);
    }

    #[Route('/admin/users/{id}/profile', name: 'ac_ui_user_profile', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function userProfile(string $id): Response
    {
        $targetUser = $this->userRepository->find($id);
        if (!$targetUser instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }

        $targetProfile = $targetUser->getProfile();

        return $this->render('access_control/pages/profile.html.twig', [
            'nav' => $this->buildNav('ac_ui_profile'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'readonly' => true,
            'pageTitle' => 'User preview',
            'pageSubtitle' => 'Read-only profile overview',
            'profile' => [
                'username' => $targetProfile?->getUsername() ?? '',
                'email' => $targetUser->getEmail(),
                'firstName' => $targetProfile?->getFirstName() ?? '',
                'lastName' => $targetProfile?->getLastName() ?? '',
                'profileImageUrl' => $targetProfile?->getProfileImageUrl() ?? '',
                'country' => $targetProfile?->getCountry() ?? '',
                'state' => $targetProfile?->getState() ?? '',
                'aboutMe' => $targetProfile?->getAboutMe() ?? '',
            ],
        ]);
    }

    #[Route('/admin/profile', name: 'ac_ui_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $profile = $user?->getProfile();
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $username = trim((string) $request->request->get('username', ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Please provide a valid email address.');
                return $this->redirectToRoute('ac_ui_profile');
            }
            if ($username === '' || mb_strlen($username) > 255) {
                $this->addFlash('error', 'Username is required and must be 255 characters or fewer.');
                return $this->redirectToRoute('ac_ui_profile');
            }
            if (mb_strlen((string) $request->request->get('aboutMe', '')) > 500) {
                $this->addFlash('error', 'About section must be 500 characters or fewer.');
                return $this->redirectToRoute('ac_ui_profile');
            }

            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser !== null && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'Email is already in use by another account.');
                return $this->redirectToRoute('ac_ui_profile');
            }
            $existingProfile = $this->profileRepository->findOneBy(['username' => $username]);
            if ($existingProfile !== null && $existingProfile->getUser()->getId() !== $user->getId()) {
                $this->addFlash('error', 'Username is already in use by another account.');
                return $this->redirectToRoute('ac_ui_profile');
            }

            if ($profile === null) {
                $now = new \DateTimeImmutable();
                $profile = (new Profile())
                    ->setId(Uuid::v4()->toRfc4122())
                    ->setUser($user)
                    ->setCreatedAt($now)
                    ->setUpdatedAt($now)
                    ->setUsername((string) $request->request->get('username', strtok($email, '@') ?: 'user'));
                $user->setProfile($profile);
                $this->entityManager->persist($profile);
            }

            $profileImage = $request->files->get('profileImage');
            if ($profileImage instanceof UploadedFile) {
                if ($profileImage->getError() !== UPLOAD_ERR_OK) {
                    $this->addFlash('error', 'Profile image upload failed. Please try again.');
                    return $this->redirectToRoute('ac_ui_profile');
                }
                if ($profileImage->getSize() !== null && $profileImage->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Profile image must be 5MB or smaller.');
                    return $this->redirectToRoute('ac_ui_profile');
                }
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array((string) $profileImage->getMimeType(), $allowedMimeTypes, true)) {
                    $this->addFlash('error', 'Profile image must be JPG, PNG, WEBP, or GIF.');
                    return $this->redirectToRoute('ac_ui_profile');
                }

                try {
                    $imageUrl = $this->imageUploadService->uploadProfileImage($profileImage);
                    $profile->setProfileImageUrl($imageUrl);
                } catch (\RuntimeException) {
                    $this->addFlash('error', 'Unable to upload profile image right now.');
                    return $this->redirectToRoute('ac_ui_profile');
                }
            }

            $user->setEmail($email);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $profile->setUsername($username);
            $profile->setFirstName($this->nullableString($request->request->get('firstName')));
            $profile->setLastName($this->nullableString($request->request->get('lastName')));
            $profile->setCountry($this->nullableString($request->request->get('country')));
            $profile->setState($this->nullableString($request->request->get('state')));
            $profile->setAboutMe($this->nullableString($request->request->get('aboutMe')));
            $profile->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('ac_ui_profile');
        }

        return $this->render('access_control/pages/profile.html.twig', [
            'nav' => $this->buildNav('ac_ui_profile'),
            'userName' => $user?->getEmail() ?? 'Admin',
            'profile' => [
                'username' => $profile?->getUsername() ?? '',
                'email' => $user?->getEmail() ?? '',
                'firstName' => $profile?->getFirstName() ?? '',
                'lastName' => $profile?->getLastName() ?? '',
                'profileImageUrl' => $profile?->getProfileImageUrl() ?? '',
                'country' => $profile?->getCountry() ?? '',
                'state' => $profile?->getState() ?? '',
                'aboutMe' => $profile?->getAboutMe() ?? '',
            ],
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    #[Route('/admin/sessions', name: 'ac_ui_sessions', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sessions(): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $sessions = array_map(
            static fn($session): array => [
                $session->getUser()->getEmail(),
                $session->getCreatedAt()->format('Y-m-d H:i'),
                $session->getExpiresAt()->format('Y-m-d H:i'),
                $session->isRevoked() ? 'Yes' : 'No',
            ],
            $this->authSessionRepository->findRecentForUser($currentUser, 100),
        );

        return $this->render('access_control/pages/sessions.html.twig', [
            'nav' => $this->buildNav('ac_ui_sessions'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'sessions' => $sessions,
        ]);
    }

    #[Route('/admin/audit-logs', name: 'ac_ui_audit_logs', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function auditLogs(): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $auditLogs = array_map(
            static fn($log): array => [
                $log->getCreatedAt()->format('Y-m-d H:i'),
                $log->getAction(),
                $log->getPrivateIpAddress(),
                $log->getHostname() ?? '-',
                $log->getOsName() ?? '-',
            ],
            $this->auditLogRepository->findRecentForUser($currentUser, 150),
        );

        return $this->render('access_control/pages/audit_logs.html.twig', [
            'nav' => $this->buildNav('ac_ui_audit_logs'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'auditLogs' => $auditLogs,
        ]);
    }

    #[Route('/admin/consultations', name: 'ac_ui_consultations', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function consultations(): Response
    {
        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('ac_ui_consultations'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'title' => 'Consultations',
            'subtitle' => 'Consultation analytics and moderation will be available soon.',
        ]);
    }

    #[Route('/admin/exercises', name: 'ac_ui_exercises', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercises(): Response
    {
        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('ac_ui_exercises'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'title' => 'Exercises',
            'subtitle' => 'Exercise management and reporting are under construction.',
        ]);
    }

    #[Route('/admin/forum', name: 'ac_ui_forum', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function forum(): Response
    {
        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('ac_ui_forum'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'title' => 'Forum',
            'subtitle' => 'Forum moderation and insights are coming soon.',
        ]);
    }

    #[Route('/admin/mood', name: 'ac_ui_mood', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function mood(): Response
    {
        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('ac_ui_mood'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'title' => 'Mood Tracking',
            'subtitle' => 'Population mood analytics and trends will be added soon.',
        ]);
    }

    #[Route('/admin/sleep', name: 'ac_ui_sleep', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sleep(): Response
    {
        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('ac_ui_sleep'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'title' => 'Sleep',
            'subtitle' => 'Sleep quality reporting and interventions are coming soon.',
        ]);
    }

    /** @return list<array{section: string, label: string, route: string, icon: string, active: bool}> */
    private function buildNav(string $activeRoute): array
    {
        $items = [
            ['section' => 'Admin self-management', 'label' => 'Dashboard', 'route' => 'ac_ui_dashboard', 'icon' => 'dashboard'],
            ['section' => 'Admin self-management', 'label' => 'Profile', 'route' => 'ac_ui_profile', 'icon' => 'person'],
            ['section' => 'Admin self-management', 'label' => 'Sessions', 'route' => 'ac_ui_sessions', 'icon' => 'devices'],
            ['section' => 'Admin self-management', 'label' => 'Audit logs', 'route' => 'ac_ui_audit_logs', 'icon' => 'history'],
            ['section' => 'Users management', 'label' => 'Users', 'route' => 'ac_ui_users', 'icon' => 'group'],
            ['section' => 'Users management', 'label' => 'Consultations', 'route' => 'ac_ui_consultations', 'icon' => 'medical_services'],
            ['section' => 'Users management', 'label' => 'Exercises', 'route' => 'ac_ui_exercises', 'icon' => 'self_improvement'],
            ['section' => 'Users management', 'label' => 'Forum', 'route' => 'ac_ui_forum', 'icon' => 'forum'],
            ['section' => 'Users management', 'label' => 'Mood', 'route' => 'ac_ui_mood', 'icon' => 'mood'],
            ['section' => 'Users management', 'label' => 'Sleep', 'route' => 'ac_ui_sleep', 'icon' => 'hotel'],
        ];

        return array_map(
            static fn(array $item): array => [
                'section' => $item['section'],
                'label' => $item['label'],
                'route' => $item['route'],
                'icon' => $item['icon'],
                'active' => $item['route'] === $activeRoute,
            ],
            $items,
        );
    }
}
