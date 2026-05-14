<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Dto\Admin\UserFilterRequest;
use App\Dto\Exercice\AddResourceRequest;
use App\Dto\Exercice\ExerciceUpsertRequest;
use App\Entity\MoodEmotion;
use App\Entity\MoodInfluence;
use App\Entity\Profile;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\MoodEmotionRepository;
use App\Repository\MoodInfluenceRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\ImageUploadService;
use App\Service\Admin\AdminExerciceService;
use App\Service\Admin\DashboardService;
use App\Service\Admin\UserManagementService;
use App\Service\User\UserDashboardService;
use App\Service\User\UserProfileService;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\ColumnChart;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AccessControlUiController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly UserManagementService $userManagementService,
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
        private readonly AuthSessionRepository $authSessionRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly MoodEmotionRepository $moodEmotionRepository,
        private readonly MoodInfluenceRepository $moodInfluenceRepository,
        private readonly AdminExerciceService $adminExerciceService,
        private readonly ImageUploadService $imageUploadService,
        private readonly UserDashboardService $userDashboardService,
        private readonly UserProfileService $userProfileService,
        private readonly EntityManagerInterface $entityManager,
        private readonly PaginatorInterface $paginator,
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
    public function resetPassword(Request $request): Response
    {
        $step = (string) $request->query->get('step', 'request');
        if (!in_array($step, ['request', 'verify', 'new'], true)) {
            $step = 'request';
        }

        return $this->render('access_control/pages/reset_password.html.twig', [
            'step' => $step,
        ]);
    }

    #[Route('/verify-email', name: 'ac_ui_verify_email', methods: ['GET'])]
    public function verifyEmail(): Response
    {
        return $this->render('access_control/pages/verify_email.html.twig');
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

        $stats = $this->dashboardService->getStatistics($user);
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
            riskLevel: $request->query->get('riskLevel'),
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
                'riskLevel' => $user->getRiskLevel(),
                'riskPrediction' => null,
                'riskConfidence' => null,
                'riskEvaluatedAt' => null,
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
                'total' => $result['total'],
            ],
            'filters' => [
                'email' => $filterRequest->email,
                'role' => $filterRequest->role,
                'accountStatus' => $filterRequest->accountStatus,
                'riskLevel' => $filterRequest->riskLevel,
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

    #[Route('/admin/settings', name: 'ac_ui_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function settings(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $current = $this->userDashboardService->decodeSettings((string) $request->cookies->get('admin_settings', ''));

        if ($request->isMethod('POST')) {
            $updated = $this->userDashboardService->sanitizeSettings([
                'theme' => (string) $request->request->get('theme', $current['theme']),
                'notifications' => (bool) $request->request->get('notifications', false),
                'compactView' => (bool) $request->request->get('compactView', false),
            ]);
            $encoded = $this->userDashboardService->encodeSettings($updated);

            $response = $this->redirectToRoute('ac_ui_settings');
            $response->headers->setCookie(new Cookie(
                'admin_settings',
                $encoded,
                time() + 31536000,
                '/',
                null,
                false,
                false,
                false,
                'lax',
            ));
            $this->addFlash('success', 'Settings updated successfully.');

            return $response;
        }

        return $this->render('user/pages/settings.html.twig', [
            'nav' => $this->buildNav('ac_ui_settings'),
            'userName' => $user->getEmail(),
            'settings' => $current,
            'faceRecognitionEnabled' => $user->isFaceRecognitionEnabled(),
            'twoFactorEnabled' => $user->isTwoFactorEnabled(),
            'settingsSaveRoute' => 'ac_ui_settings',
            'settingsChangePasswordRoute' => 'ac_ui_settings_change_password',
            'settingsDeleteAccountRoute' => 'ac_ui_settings_delete_account',
        ]);
    }

    #[Route('/admin/settings/delete-account', name: 'ac_ui_settings_delete_account', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteAccount(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $result = $this->userProfileService->deleteAccount(
            $user,
            trim((string) $request->request->get('currentPassword', '')),
            trim((string) $request->request->get('confirmDelete', '')),
        );

        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('ac_ui_settings');
        }

        $response = $this->redirectToRoute('home');
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/');
        $response->headers->clearCookie('jwt', '/');
        $response->headers->clearCookie('admin_settings', '/');

        return $response;
    }

    #[Route('/admin/settings/change-password', name: 'ac_ui_settings_change_password', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $result = $this->userProfileService->changePassword(
            $user,
            trim((string) $request->request->get('currentPassword', '')),
            trim((string) $request->request->get('newPassword', '')),
            trim((string) $request->request->get('confirmPassword', '')),
        );

        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('ac_ui_settings');
        }

        $this->addFlash('success', $result->message);

        return $this->redirectToRoute('ac_ui_settings');
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
    public function exercises(Request $request): Response
    {
        $exercices = $this->adminExerciceService->listExercices();

        return $this->render('access_control/pages/exercises.html.twig', [
            'nav' => $this->buildNav('ac_ui_exercises'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'summary' => $this->adminExerciceService->summary(),
            'exercices' => $this->paginator->paginate($exercices, max(1, $request->query->getInt('page', 1)), 3),
            'exerciseTypeChart' => $this->buildPieChart(
                'Exercises by type',
                ['Type', 'Exercises'],
                $this->adminExerciceService->countExercicesByType(),
            ),
            'sessionStatusChart' => $this->buildColumnChart(
                'Session status distribution',
                ['Status', 'Sessions'],
                $this->adminExerciceService->countControlsByStatus(),
            ),
        ]);
    }

    #[Route('/admin/exercises/create', name: 'ac_ui_exercises_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesCreate(Request $request, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('exercice_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid create token.');

            return $this->redirectToRoute('ac_ui_exercises');
        }

        $dto = $this->exerciseDtoFromRequest($request);

        if (!$this->isDtoValid($validator, $dto)) {
            return $this->redirectToRoute('ac_ui_exercises');
        }

        $result = $this->adminExerciceService->createExercice($dto);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('ac_ui_exercises');
    }

    #[Route('/admin/exercises/{id}/edit', name: 'ac_ui_exercises_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesEdit(Request $request, int $id, ValidatorInterface $validator): Response
    {
        $exercice = $this->adminExerciceService->getExercice($id);
        if ($exercice === null) {
            $this->addFlash('error', 'Exercice not found.');

            return $this->redirectToRoute('ac_ui_exercises');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('exercice_edit_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid edit token.');

                return $this->redirectToRoute('ac_ui_exercises_edit', ['id' => $id]);
            }

            $dto = $this->exerciseDtoFromRequest($request);
            if (!$this->isDtoValid($validator, $dto)) {
                return $this->redirectToRoute('ac_ui_exercises_edit', ['id' => $id]);
            }

            $result = $this->adminExerciceService->updateExercice($id, $dto);
            $this->addFlash($result->success ? 'success' : 'error', $result->message);

            return $this->redirectToRoute($result->success ? 'ac_ui_exercises' : 'ac_ui_exercises_edit', $result->success ? [] : ['id' => $id]);
        }

        return $this->render('access_control/pages/exercise_edit.html.twig', [
            'nav' => $this->buildNav('ac_ui_exercises'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'exercice' => $exercice,
        ]);
    }

    #[Route('/admin/exercises/assign', name: 'ac_ui_exercises_assign', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesAssign(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('exercice_assign', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid assign token.');

            return $this->redirectToRoute('ac_ui_exercises');
        }

        $admin = $this->getUser();
        if (!$admin instanceof User) {
            return $this->redirectToRoute('ac_ui_login');
        }

        $result = $this->adminExerciceService->assignExercice(
            (int) $request->request->get('exerciceId', 0),
            (string) $request->request->get('userId', ''),
            $admin,
        );
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('ac_ui_exercises');
    }

    #[Route('/admin/exercises/{id}/resources', name: 'ac_ui_exercises_resources', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesResources(Request $request, int $id): Response
    {
        $result = $this->adminExerciceService->resourcesForExercice($id);
        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('ac_ui_exercises');
        }

        return $this->render('access_control/pages/exercise_resources.html.twig', [
            'nav' => $this->buildNav('ac_ui_exercises'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'exercice' => $result->data['exercice'],
            'resources' => $this->paginator->paginate($result->data['resources'], max(1, $request->query->getInt('page', 1)), 8),
            'resourceCountChart' => $this->buildColumnChart(
                'Resource count by exercise',
                ['Exercise', 'Resources'],
                $this->adminExerciceService->countResourcesByExercise(),
            ),
        ]);
    }

    #[Route('/admin/exercises/{id}/resource', name: 'ac_ui_exercises_add_resource', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesAddResource(Request $request, int $id, ValidatorInterface $validator): Response
    {
        $redirectRoute = $request->request->get('returnTo') === 'resources' ? 'ac_ui_exercises_resources' : 'ac_ui_exercises';
        $redirectParams = $redirectRoute === 'ac_ui_exercises_resources' ? ['id' => $id] : [];

        if (!$this->isCsrfTokenValid('exercice_resource_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid resource token.');

            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $dto = new AddResourceRequest();
        $dto->title = trim((string) $request->request->get('title', ''));
        $dto->resourceType = trim((string) $request->request->get('resourceType', ''));
        $dto->resourceUrl = trim((string) $request->request->get('resourceUrl', ''));

        if (!$this->isDtoValid($validator, $dto)) {
            return $this->redirectToRoute($redirectRoute, $redirectParams);
        }

        $result = $this->adminExerciceService->addResource($id, $dto->title, $dto->resourceType, $dto->resourceUrl);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute($redirectRoute, $redirectParams);
    }

    #[Route('/admin/resources/{id}/edit', name: 'ac_ui_exercise_resource_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exerciseResourceEdit(Request $request, int $id, ValidatorInterface $validator): Response
    {
        $resource = $this->adminExerciceService->getResource($id);
        if ($resource === null) {
            $this->addFlash('error', 'Resource not found.');

            return $this->redirectToRoute('ac_ui_exercises');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('exercice_resource_edit_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid resource edit token.');

                return $this->redirectToRoute('ac_ui_exercise_resource_edit', ['id' => $id]);
            }

            $dto = new AddResourceRequest();
            $dto->title = trim((string) $request->request->get('title', ''));
            $dto->resourceType = trim((string) $request->request->get('resourceType', ''));
            $dto->resourceUrl = trim((string) $request->request->get('resourceUrl', ''));

            if (!$this->isDtoValid($validator, $dto)) {
                return $this->redirectToRoute('ac_ui_exercise_resource_edit', ['id' => $id]);
            }

            $result = $this->adminExerciceService->updateResource($id, $dto->title, $dto->resourceType, $dto->resourceUrl);
            $this->addFlash($result->success ? 'success' : 'error', $result->message);

            $exerciseId = (int) ($resource['exercice']['id'] ?? 0);

            return $this->redirectToRoute($result->success && $exerciseId > 0 ? 'ac_ui_exercises_resources' : 'ac_ui_exercise_resource_edit', $result->success && $exerciseId > 0 ? ['id' => $exerciseId] : ['id' => $id]);
        }

        return $this->render('access_control/pages/exercise_resource_edit.html.twig', [
            'nav' => $this->buildNav('ac_ui_exercises'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'resource' => $resource,
        ]);
    }

    #[Route('/admin/resources/{id}/delete', name: 'ac_ui_exercise_resource_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exerciseResourceDelete(Request $request, int $id): Response
    {
        $resource = $this->adminExerciceService->getResource($id);
        $exerciseId = is_array($resource) ? (int) ($resource['exercice']['id'] ?? 0) : 0;
        if (!$this->isCsrfTokenValid('exercice_resource_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid resource delete token.');

            return $exerciseId > 0
                ? $this->redirectToRoute('ac_ui_exercises_resources', ['id' => $exerciseId])
                : $this->redirectToRoute('ac_ui_exercises');
        }

        $result = $this->adminExerciceService->deleteResource($id);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);
        $redirectExerciseId = (int) ($result->data['exerciceId'] ?? $exerciseId);

        return $redirectExerciseId > 0
            ? $this->redirectToRoute('ac_ui_exercises_resources', ['id' => $redirectExerciseId])
            : $this->redirectToRoute('ac_ui_exercises');
    }

    #[Route('/admin/exercises/{id}/delete', name: 'ac_ui_exercises_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exercisesDelete(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('exercice_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('ac_ui_exercises');
        }

        $result = $this->adminExerciceService->deleteExercice($id);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('ac_ui_exercises');
    }

    private function exerciseDtoFromRequest(Request $request): ExerciceUpsertRequest
    {
        $dto = new ExerciceUpsertRequest();
        $dto->title = trim((string) $request->request->get('title', ''));
        $dto->type = trim((string) $request->request->get('type', ''));
        $dto->level = max(1, min(10, (int) $request->request->get('level', 1)));
        $dto->durationMinutes = max(1, min(300, (int) $request->request->get('durationMinutes', 10)));
        $description = trim((string) $request->request->get('description', ''));
        $dto->description = $description !== '' ? $description : null;
        $benefits = trim((string) $request->request->get('benefits', ''));
        $dto->benefits = $benefits !== '' ? $benefits : null;
        $tips = trim((string) $request->request->get('tips', ''));
        $dto->tips = $tips !== '' ? $tips : null;
        $theme = trim((string) $request->request->get('theme', ''));
        $dto->theme = $theme !== '' ? $theme : null;
        $guidedInstructionsText = trim((string) $request->request->get('guidedInstructionsText', ''));
        $dto->guidedInstructionsText = $guidedInstructionsText !== '' ? $guidedInstructionsText : null;
        $dto->isActive = $request->request->has('isActive');

        return $dto;
    }

    /**
     * @param list<array{0:string,1:int}> $rows
     */
    private function buildPieChart(string $title, array $header, array $rows): PieChart
    {
        $chart = new PieChart();
        $chart->getData()->setArrayToDataTable([
            $header,
            ...($rows !== [] ? $rows : [['No data', 0]]),
        ]);
        $chart->getOptions()
            ->setTitle($title)
            ->setHeight(320)
            ->setWidth(520)
            ->setColors(['#2f6f6d', '#88bdbc', '#5e8c8a', '#cfe1df', '#355d6e']);
        $chart->getOptions()->getLegend()->setPosition('bottom');

        return $chart;
    }

    /**
     * @param list<array{0:string,1:int}> $rows
     */
    private function buildColumnChart(string $title, array $header, array $rows): ColumnChart
    {
        $chart = new ColumnChart();
        $chart->getData()->setArrayToDataTable([
            $header,
            ...($rows !== [] ? $rows : [['No data', 0]]),
        ]);
        $chart->getOptions()
            ->setTitle($title)
            ->setHeight(320)
            ->setWidth(560)
            ->setColors(['#2f6f6d']);
        $chart->getOptions()->getLegend()->setPosition('none');

        return $chart;
    }

    #[Route('/admin/mood', name: 'ac_ui_mood', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function mood(): Response
    {
        return $this->render('access_control/pages/mood_analytics.html.twig', [
            'nav' => $this->buildNav('ac_ui_mood'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
        ]);
    }

    #[Route('/admin/emotion', name: 'ac_ui_emotion', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function emotionManagement(Request $request): Response
    {
        $listState = $this->resolveTaxonomyListState($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('emotion_create', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid create token.');
            } else {
                $name = trim((string) $request->request->get('name', ''));
                $error = $this->validateEmotionName($name);
                if ($error !== null) {
                    $this->addFlash('error', $error);
                } else {
                    $exists = $this->moodEmotionRepository->createQueryBuilder('emotion')
                        ->select('COUNT(emotion.id)')
                        ->andWhere('LOWER(emotion.name) = :name')
                        ->setParameter('name', mb_strtolower($name))
                        ->getQuery()
                        ->getSingleScalarResult();

                    if ((int) $exists > 0) {
                        $this->addFlash('error', 'This emotion already exists.');
                    } else {
                        $emotion = (new MoodEmotion())->setName($name);
                        $this->entityManager->persist($emotion);
                        $this->entityManager->flush();
                        $this->addFlash('success', 'Emotion created successfully.');
                    }
                }
            }

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($listState));
        }

        $emotionQuery = $this->moodEmotionRepository->createQueryBuilder('emotion')
            ->orderBy('emotion.id', $listState['sortDirection'])
            ->getQuery();
        $emotionPerPageLimit = $listState['perPage'] === 'all'
            ? max(1, (int) $this->moodEmotionRepository->count([]))
            : $listState['perPageLimit'];
        $emotions = $this->paginator->paginate(
            $emotionQuery,
            $listState['page'],
            $emotionPerPageLimit
        );

        return $this->render('access_control/pages/emotion_management.html.twig', [
            'nav' => $this->buildNav('ac_ui_emotion'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'emotions' => $emotions,
            'direction' => $listState['direction'],
            'perPage' => $listState['perPage'],
        ]);
    }

    #[Route('/admin/emotion/{id}/edit', name: 'ac_ui_emotion_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function emotionEdit(Request $request, int $id): Response
    {
        $emotion = $this->moodEmotionRepository->find($id);
        if (!$emotion instanceof MoodEmotion) {
            $this->addFlash('error', 'Emotion not found.');

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }
        if (!$this->isCsrfTokenValid('emotion_edit_' . $emotion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid edit token.');

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $name = trim((string) $request->request->get('name', ''));
        $error = $this->validateEmotionName($name);
        if ($error !== null) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $exists = $this->moodEmotionRepository->createQueryBuilder('emotion')
            ->select('COUNT(emotion.id)')
            ->andWhere('LOWER(emotion.name) = :name')
            ->andWhere('emotion.id != :id')
            ->setParameter('name', mb_strtolower($name))
            ->setParameter('id', $emotion->getId())
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $exists > 0) {
            $this->addFlash('error', 'This emotion already exists.');

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $emotion->setName($name);
        $this->entityManager->flush();
        $this->addFlash('success', 'Emotion updated successfully.');

        return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
    }

    #[Route('/admin/emotion/{id}/delete', name: 'ac_ui_emotion_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function emotionDelete(Request $request, int $id): Response
    {
        $emotion = $this->moodEmotionRepository->find($id);
        if (!$emotion instanceof MoodEmotion) {
            $this->addFlash('error', 'Emotion not found.');

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }
        if (!$this->isCsrfTokenValid('emotion_delete_' . $emotion->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $this->entityManager->remove($emotion);
        $this->entityManager->flush();
        $this->addFlash('success', 'Emotion deleted successfully.');

        return $this->redirectToRoute('ac_ui_emotion', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
    }

    #[Route('/admin/influence', name: 'ac_ui_influence', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function influenceManagement(Request $request): Response
    {
        $listState = $this->resolveTaxonomyListState($request);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('influence_create', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid create token.');
            } else {
                $name = trim((string) $request->request->get('name', ''));
                $error = $this->validateInfluenceName($name);
                if ($error !== null) {
                    $this->addFlash('error', $error);
                } else {
                    $exists = $this->moodInfluenceRepository->createQueryBuilder('influence')
                        ->select('COUNT(influence.id)')
                        ->andWhere('LOWER(influence.name) = :name')
                        ->setParameter('name', mb_strtolower($name))
                        ->getQuery()
                        ->getSingleScalarResult();

                    if ((int) $exists > 0) {
                        $this->addFlash('error', 'This influence already exists.');
                    } else {
                        $influence = (new MoodInfluence())->setName($name);
                        $this->entityManager->persist($influence);
                        $this->entityManager->flush();
                        $this->addFlash('success', 'Influence created successfully.');
                    }
                }
            }

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($listState));
        }

        $influenceQuery = $this->moodInfluenceRepository->createQueryBuilder('influence')
            ->orderBy('influence.id', $listState['sortDirection'])
            ->getQuery();
        $influencePerPageLimit = $listState['perPage'] === 'all'
            ? max(1, (int) $this->moodInfluenceRepository->count([]))
            : $listState['perPageLimit'];
        $influences = $this->paginator->paginate(
            $influenceQuery,
            $listState['page'],
            $influencePerPageLimit
        );

        return $this->render('access_control/pages/influence_management.html.twig', [
            'nav' => $this->buildNav('ac_ui_influence'),
            'userName' => $this->getUser()?->getEmail() ?? 'Admin',
            'influences' => $influences,
            'direction' => $listState['direction'],
            'perPage' => $listState['perPage'],
        ]);
    }

    #[Route('/admin/influence/{id}/edit', name: 'ac_ui_influence_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function influenceEdit(Request $request, int $id): Response
    {
        $influence = $this->moodInfluenceRepository->find($id);
        if (!$influence instanceof MoodInfluence) {
            $this->addFlash('error', 'Influence not found.');

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }
        if (!$this->isCsrfTokenValid('influence_edit_' . $influence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid edit token.');

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $name = trim((string) $request->request->get('name', ''));
        $error = $this->validateInfluenceName($name);
        if ($error !== null) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $exists = $this->moodInfluenceRepository->createQueryBuilder('influence')
            ->select('COUNT(influence.id)')
            ->andWhere('LOWER(influence.name) = :name')
            ->andWhere('influence.id != :id')
            ->setParameter('name', mb_strtolower($name))
            ->setParameter('id', $influence->getId())
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) $exists > 0) {
            $this->addFlash('error', 'This influence already exists.');

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $influence->setName($name);
        $this->entityManager->flush();
        $this->addFlash('success', 'Influence updated successfully.');

        return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
    }

    #[Route('/admin/influence/{id}/delete', name: 'ac_ui_influence_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function influenceDelete(Request $request, int $id): Response
    {
        $influence = $this->moodInfluenceRepository->find($id);
        if (!$influence instanceof MoodInfluence) {
            $this->addFlash('error', 'Influence not found.');

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }
        if (!$this->isCsrfTokenValid('influence_delete_' . $influence->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
        }

        $this->entityManager->remove($influence);
        $this->entityManager->flush();
        $this->addFlash('success', 'Influence deleted successfully.');

        return $this->redirectToRoute('ac_ui_influence', $this->taxonomyRedirectParams($this->resolveTaxonomyListState($request)));
    }

    /**
     * @return array{page:int,perPage:string,perPageLimit:int,direction:string,sortDirection:'ASC'|'DESC'}
     */
    private function resolveTaxonomyListState(Request $request): array
    {
        $allowedPerPage = ['5', '10', '20', 'all'];
        $allowedDirection = ['asc', 'desc'];

        $perPage = (string) $request->query->get('perPage', '10');
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = '10';
        }

        $direction = mb_strtolower((string) $request->query->get('direction', 'asc'));
        if (!in_array($direction, $allowedDirection, true)) {
            $direction = 'asc';
        }

        $page = max(1, $request->query->getInt('page', 1));
        if ($perPage === 'all') {
            $page = 1;
        }

        $perPageLimit = match ($perPage) {
            '5' => 5,
            '20' => 20,
            'all' => 5000,
            default => 10,
        };

        return [
            'page' => $page,
            'perPage' => $perPage,
            'perPageLimit' => $perPageLimit,
            'direction' => $direction,
            'sortDirection' => $direction === 'desc' ? 'DESC' : 'ASC',
        ];
    }

    /**
     * @param array{page:int,perPage:string,direction:string} $listState
     * @return array{page:int,perPage:string,direction:string}
     */
    private function taxonomyRedirectParams(array $listState): array
    {
        return [
            'page' => $listState['page'],
            'perPage' => $listState['perPage'],
            'direction' => $listState['direction'],
        ];
    }

    /** @return list<array{section: string, label: string, route: string, icon: string, active: bool, children?: list<array{label: string, route: string, icon: string, active: bool}>}> */
    private function buildNav(string $activeRoute): array
    {
        $moodChildRoutes = ['ac_ui_mood', 'ac_ui_emotion', 'ac_ui_influence'];
        $sleepChildRoutes = ['app_admin_sommeil_index'];
        $items = [
            ['section' => 'Admin self-management', 'label' => 'Dashboard', 'route' => 'ac_ui_dashboard', 'icon' => 'dashboard'],
            ['section' => 'Admin self-management', 'label' => 'Profile', 'route' => 'ac_ui_profile', 'icon' => 'person'],
            ['section' => 'Admin self-management', 'label' => 'Settings', 'route' => 'ac_ui_settings', 'icon' => 'settings'],
            ['section' => 'Admin self-management', 'label' => 'Sessions', 'route' => 'ac_ui_sessions', 'icon' => 'devices'],
            ['section' => 'Admin self-management', 'label' => 'Audit logs', 'route' => 'ac_ui_audit_logs', 'icon' => 'history'],
            ['section' => 'Users management', 'label' => 'Users', 'route' => 'ac_ui_users', 'icon' => 'group'],
            ['section' => 'Users management', 'label' => 'Consultations', 'route' => 'ac_ui_consultations', 'icon' => 'medical_services'],
            ['section' => 'Users management', 'label' => 'Exercises', 'route' => 'ac_ui_exercises', 'icon' => 'self_improvement'],
            ['section' => 'Users management', 'label' => 'Forum', 'route' => 'app_admin_forum', 'icon' => 'forum'],
            ['section' => 'Users management', 'label' => 'Sleep', 'route' => 'app_admin_sommeil_index', 'icon' => 'nights_stay'],
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

        ];

        return array_map(
            static function (array $item) use ($activeRoute, $moodChildRoutes, $sleepChildRoutes): array {
                $isMoodGroup = $item['route'] === 'ac_ui_mood' && isset($item['children']);
                $isSleepGroup = $item['route'] === 'app_admin_sommeil_index' && isset($item['children']);
                $active = $item['route'] === $activeRoute;

                if ($isMoodGroup) {
                    $active = in_array($activeRoute, $moodChildRoutes, true);
                } elseif ($isSleepGroup) {
                    $active = in_array($activeRoute, $sleepChildRoutes, true);
                }

                if (!$isMoodGroup && !$isSleepGroup) {
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

    private function validateEmotionName(string $name): ?string
    {
        if ($name === '') {
            return 'Emotion name is required.';
        }
        if (mb_strlen($name) < 3) {
            return 'Emotion name must contain at least 3 characters.';
        }
        if (mb_strlen($name) > 40) {
            return 'Emotion name cannot exceed 40 characters.';
        }
        if (preg_match('/^[A-Za-z ]+$/', $name) !== 1) {
            return 'Emotion name can contain only letters and spaces.';
        }

        return null;
    }

    private function validateInfluenceName(string $name): ?string
    {
        if ($name === '') {
            return 'Influence name is required.';
        }
        if (mb_strlen($name) > 60) {
            return 'Influence name cannot exceed 60 characters.';
        }
        if (preg_match('/^[A-Za-z\/ ]+$/', $name) !== 1) {
            return 'Influence name can contain only letters, spaces, and /.';
        }

        return null;
    }

    private function isDtoValid(ValidatorInterface $validator, object $dto): bool
    {
        $violations = $validator->validate($dto);
        if (count($violations) === 0) {
            return true;
        }

        foreach ($violations as $violation) {
            /** @var ConstraintViolationInterface $violation */
            $field = $violation->getPropertyPath();
            $message = $violation->getMessage();
            $this->addFlash('error', ($field !== '' ? $field . ': ' : '') . $message);
        }

        return false;
    }
}
