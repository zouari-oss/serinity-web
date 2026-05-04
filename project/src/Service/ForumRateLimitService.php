<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ForumRateLimitService
{
    public function __construct(
        #[Autowire(service: 'limiter.follow_toggle')]
        private readonly RateLimiterFactory $followToggleLimiter,
    ) {
    }

    public function consumeFollowToggle(string $userId): RateLimit
    {
        return $this->followToggleLimiter->create('follow_toggle_'.$userId)->consume(1);
    }
}