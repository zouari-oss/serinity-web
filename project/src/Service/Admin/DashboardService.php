<?php

declare(strict_types=1);

namespace App\Service\Admin;

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
    public function getStatistics(): array
    {
        return [
            'totalUsers' => $this->userRepository->countUsers(),
            'activeSessions' => $this->authSessionRepository->countActiveSessions(),
            'recentAuditEvents' => $this->auditLogRepository->countRecentEvents(days: 7),
            'profileCompletionPercentage' => $this->userRepository->getProfileCompletionPercentage(),
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
}
