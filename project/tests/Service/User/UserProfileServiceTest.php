<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Service\User\UserProfileService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class UserProfileServiceTest extends TestCase
{
    public function testUserProfileServiceExists(): void
    {
        self::assertTrue(class_exists(UserProfileService::class));
    }
    
    public function testUserProfileServiceHasToArrayMethod(): void
    {
        $reflection = new ReflectionClass(UserProfileService::class);
        self::assertTrue($reflection->hasMethod('toArray'));
    }
    
    public function testUserProfileServiceHasUpdateMethod(): void
    {
        $reflection = new ReflectionClass(UserProfileService::class);
        self::assertTrue($reflection->hasMethod('update'));
    }
    
    public function testUserProfileServiceHasChangePasswordMethod(): void
    {
        $reflection = new ReflectionClass(UserProfileService::class);
        self::assertTrue($reflection->hasMethod('changePassword'));
    }
    
    public function testUserProfileServiceHasDeleteAccountMethod(): void
    {
        $reflection = new ReflectionClass(UserProfileService::class);
        self::assertTrue($reflection->hasMethod('deleteAccount'));
    }
    
    public function testUserProfileServiceHasSetProfileImageMethod(): void
    {
        $reflection = new ReflectionClass(UserProfileService::class);
        self::assertTrue($reflection->hasMethod('setProfileImage'));
    }
}
