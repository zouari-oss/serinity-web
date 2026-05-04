<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\User;
use App\Entity\Profile;
use App\Repository\AuditLogRepository;
use App\Repository\AuthSessionRepository;
use App\Repository\ProfileRepository;
use App\Service\User\UserDashboardService;
use PHPUnit\Framework\TestCase;

final class UserDashboardServiceTest extends TestCase
{
    public function testGetSummaryReturnsCompleteStructure(): void
    {
        $user = $this->buildUser();
        
        $authSessionRepository = $this->createMock(AuthSessionRepository::class);
        $authSessionRepository->method('findActiveForUser')->willReturn([
            $this->createMock(\stdClass::class),
            $this->createMock(\stdClass::class),
        ]);
        
        $auditLogRepository = $this->createMock(AuditLogRepository::class);
        $auditLogRepository->method('findRecentForUser')->willReturn([
            $this->createMock(\stdClass::class),
        ]);
        
        $profile = $this->buildProfile();
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn($profile);
        
        $service = new UserDashboardService($authSessionRepository, $auditLogRepository, $profileRepository);
        $summary = $service->getSummary($user);
        
        self::assertIsArray($summary);
        self::assertArrayHasKey('activeSessions', $summary);
        self::assertArrayHasKey('recentAuditEvents', $summary);
        self::assertArrayHasKey('profileCompletion', $summary);
        self::assertArrayHasKey('role', $summary);
        self::assertArrayHasKey('accountStatus', $summary);
        self::assertArrayHasKey('profileImageUrl', $summary);
        self::assertArrayHasKey('animeAvatarImageUrl', $summary);
        
        self::assertSame(2, $summary['activeSessions']);
        self::assertSame(1, $summary['recentAuditEvents']);
        self::assertSame('PATIENT', $summary['role']);
        self::assertSame('ACTIVE', $summary['accountStatus']);
    }
    
    public function testGetSummaryCalculatesProfileCompletion(): void
    {
        $user = $this->buildUser();
        
        $authSessionRepository = $this->createMock(AuthSessionRepository::class);
        $authSessionRepository->method('findActiveForUser')->willReturn([]);
        
        $auditLogRepository = $this->createMock(AuditLogRepository::class);
        $auditLogRepository->method('findRecentForUser')->willReturn([]);
        
        $profile = (new Profile())
            ->setUsername('testuser')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setCountry(null)
            ->setState(null)
            ->setAboutMe(null);
        
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn($profile);
        
        $service = new UserDashboardService($authSessionRepository, $auditLogRepository, $profileRepository);
        $summary = $service->getSummary($user);
        
        self::assertGreaterThan(0, $summary['profileCompletion']);
        self::assertLessThanOrEqual(100, $summary['profileCompletion']);
    }
    
    public function testGetSummaryHandlesNoProfile(): void
    {
        $user = $this->buildUser();
        
        $authSessionRepository = $this->createMock(AuthSessionRepository::class);
        $authSessionRepository->method('findActiveForUser')->willReturn([]);
        
        $auditLogRepository = $this->createMock(AuditLogRepository::class);
        $auditLogRepository->method('findRecentForUser')->willReturn([]);
        
        $profileRepository = $this->createMock(ProfileRepository::class);
        $profileRepository->method('findOneBy')->willReturn(null);
        
        $service = new UserDashboardService($authSessionRepository, $auditLogRepository, $profileRepository);
        $summary = $service->getSummary($user);
        
        self::assertSame('', $summary['profileImageUrl']);
        self::assertSame('', $summary['animeAvatarImageUrl']);
    }
    
    public function testDecodeSettingsReturnsDefaultsForInvalidInput(): void
    {
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        $defaults = $service->decodeSettings(null);
        self::assertSame('system', $defaults['theme']);
        self::assertTrue($defaults['notifications']);
        self::assertFalse($defaults['compactView']);
    }
    
    public function testDecodeSettingsReturnsDefaultsForEmptyString(): void
    {
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        $defaults = $service->decodeSettings('');
        self::assertSame('system', $defaults['theme']);
    }
    
    public function testDecodeSettingsDecodesValidBase64Json(): void
    {
        $settings = ['theme' => 'dark', 'notifications' => false, 'compactView' => true];
        $encoded = base64_encode((string) json_encode($settings, JSON_THROW_ON_ERROR));
        
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        $decoded = $service->decodeSettings($encoded);
        self::assertSame('dark', $decoded['theme']);
        self::assertFalse($decoded['notifications']);
        self::assertTrue($decoded['compactView']);
    }
    
    public function testSanitizeSettingsRejectsInvalidTheme(): void
    {
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        $input = ['theme' => 'invalid', 'notifications' => true, 'compactView' => false];
        $sanitized = $service->sanitizeSettings($input);
        
        self::assertSame('system', $sanitized['theme']);
    }
    
    public function testSanitizeSettingsAcceptsValidThemes(): void
    {
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        foreach (['system', 'light', 'dark'] as $theme) {
            $input = ['theme' => $theme, 'notifications' => true, 'compactView' => false];
            $sanitized = $service->sanitizeSettings($input);
            self::assertSame($theme, $sanitized['theme']);
        }
    }
    
    public function testEncodeSettingsProducesBase64Json(): void
    {
        $service = new UserDashboardService(
            $this->createMock(AuthSessionRepository::class),
            $this->createMock(AuditLogRepository::class),
            $this->createMock(ProfileRepository::class),
        );
        
        $settings = ['theme' => 'dark', 'notifications' => false, 'compactView' => true];
        $encoded = $service->encodeSettings($settings);
        
        $decoded = json_decode(base64_decode($encoded, true), true);
        self::assertSame($settings, $decoded);
    }
    
    private function buildUser(): User
    {
        return (new User())
            ->setId('test-user')
            ->setEmail('test@example.com')
            ->setPassword('secret')
            ->setRole('PATIENT')
            ->setPresenceStatus('OFFLINE')
            ->setAccountStatus('ACTIVE')
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());
    }
    
    private function buildProfile(): Profile
    {
        return (new Profile())
            ->setUsername('testuser')
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setCountry('USA')
            ->setState('CA')
            ->setAboutMe('Test profile')
            ->setProfileImageUrl('https://example.com/image.jpg');
    }
}
