<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\ProfileRepository;

final readonly class UserDashboardService
{
    public function __construct(
        private AuthSessionRepository $authSessionRepository,
        private AuditLogRepository $auditLogRepository,
        private ProfileRepository $profileRepository,
    ) {
    }

    /**
     * @return array{
     *     activeSessions:int,
     *     recentAuditEvents:int,
     *     profileCompletion:int,
     *     role:string,
     *     accountStatus:string,
     *     profileImageUrl:string,
     *     animeAvatarImageUrl:string
     * }
     */
    public function getSummary(User $user): array
    {
        $activeSessions = count($this->authSessionRepository->findActiveForUser($user));
        $recentAudits = count($this->auditLogRepository->findRecentForUser($user, 20));
        $profile = $this->profileRepository->findOneBy(['user' => $user]);

        $profileFields = [
            $profile?->getUsername(),
            $profile?->getFirstName(),
            $profile?->getLastName(),
            $profile?->getCountry(),
            $profile?->getState(),
            $profile?->getAboutMe(),
        ];
        $completed = count(array_filter($profileFields, static fn($value) => is_string($value) && trim($value) !== ''));
        $profileCompletion = (int) round(($completed / max(1, count($profileFields))) * 100);

        return [
            'activeSessions' => $activeSessions,
            'recentAuditEvents' => $recentAudits,
            'profileCompletion' => $profileCompletion,
            'role' => $user->getRole(),
            'accountStatus' => $user->getAccountStatus(),
            'profileImageUrl' => $profile?->getProfileImageUrl() ?? '',
            'animeAvatarImageUrl' => $profile?->getAnimeAvatarImageUrl() ?? '',
        ];
    }

    /**
     * @return array{theme:string,notifications:bool,compactView:bool}
     */
    public function decodeSettings(?string $encodedSettings): array
    {
        $settings = ['theme' => 'system', 'notifications' => true, 'compactView' => false];
        if (!is_string($encodedSettings) || $encodedSettings === '') {
            return $settings;
        }

        $json = base64_decode($encodedSettings, true);
        if (!is_string($json) || $json === '') {
            return $settings;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return $settings;
        }

        return $this->sanitizeSettings($payload);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{theme:string,notifications:bool,compactView:bool}
     */
    public function sanitizeSettings(array $input): array
    {
        $theme = (string) ($input['theme'] ?? 'system');
        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            $theme = 'system';
        }

        return [
            'theme' => $theme,
            'notifications' => (bool) ($input['notifications'] ?? true),
            'compactView' => (bool) ($input['compactView'] ?? false),
        ];
    }

    /**
     * @param array{theme:string,notifications:bool,compactView:bool} $settings
     */
    public function encodeSettings(array $settings): string
    {
        return base64_encode((string) json_encode($settings, JSON_THROW_ON_ERROR));
    }
}
