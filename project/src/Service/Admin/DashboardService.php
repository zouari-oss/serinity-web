<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\Profile;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Enum\UserRole;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\UserRepository;

/**
 * Service for aggregating dashboard statistics.
 */
final readonly class DashboardService
{
    public function __construct(
        private UserRepository $userRepository,
        private AuthSessionRepository $authSessionRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    /**
     * Get dashboard statistics.
     */
    public function getStatistics(User $adminUser): array
    {
        $totalUsers = $this->userRepository->countUsers();
        $patients = $this->userRepository->countByRole(UserRole::PATIENT);
        $therapists = $this->userRepository->countByRole(UserRole::THERAPIST);
        $activeUsers = $this->userRepository->countNonAdminByAccountStatus(AccountStatus::ACTIVE);
        $disabledUsers = $this->userRepository->countNonAdminByAccountStatus(AccountStatus::DISABLED);
        $profileCompletionPercentage = $this->userRepository->getProfileCompletionPercentage();
        $nonAdminUsers = $this->userRepository->findAllNonAdmin();

        $usersCompletionTotal = 0;
        $usersCompleteProfiles = 0;
        foreach ($nonAdminUsers as $user) {
            $completion = $this->calculateProfileCompletion($user->getProfile());
            $usersCompletionTotal += $completion;
            if ($completion === 100) {
                ++$usersCompleteProfiles;
            }
        }
        $usersPopulation = count($nonAdminUsers);
        $usersProfileCompletion = $usersPopulation > 0 ? (int) round($usersCompletionTotal / $usersPopulation) : 0;
        $adminProfileCompletion = $this->calculateProfileCompletion($adminUser->getProfile());

        return [
            'totalUsers' => $totalUsers,
            'activeSessions' => $this->authSessionRepository->countActiveSessions(),
            'recentAuditEvents' => $this->auditLogRepository->countRecentEvents(days: 7),
            'profileCompletionPercentage' => $profileCompletionPercentage,
            'adminProfileCompletion' => $adminProfileCompletion,
            'usersProfileCompletion' => $usersProfileCompletion,
            'usersPopulation' => $usersPopulation,
            'usersCompleteProfiles' => $usersCompleteProfiles,
            'activeUsers' => $activeUsers,
            'disabledUsers' => $disabledUsers,
            'patients' => $patients,
            'therapists' => $therapists,
            'newUsers7d' => $this->userRepository->countNonAdminCreatedSince(new \DateTimeImmutable('-7 days')),
        ];
    }

    /**
     * Get recent activity from audit logs.
     * 
     * @return array<int, array{timestamp: \DateTimeImmutable, eventType: string, userEmail: string, ipAddress: string}>
     */
    public function getRecentActivity(int $limit = 10): array
    {
        $logs = $this->auditLogRepository->findRecent($limit);
        $activity = [];

        foreach ($logs as $log) {
            $session = $log->getAuthSession();
            $activity[] = [
                'timestamp' => $log->getCreatedAt(),
                'eventType' => $log->getAction(),
                'userEmail' => $session->getUser()->getEmail(),
                'ipAddress' => $log->getPrivateIpAddress(),
            ];
        }

        return $activity;
    }

    private function calculateProfileCompletion(?Profile $profile): int
    {
        if (!$profile instanceof Profile) {
            return 0;
        }

        $fields = [
            $profile->getFirstName(),
            $profile->getLastName(),
            $profile->getPhone(),
            $profile->getGender(),
            $profile->getCountry(),
            $profile->getState(),
            $profile->getAboutMe(),
            $profile->getProfileImageUrl(),
        ];

        $filled = 0;
        foreach ($fields as $value) {
            if (is_string($value) && trim($value) !== '') {
                ++$filled;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}
