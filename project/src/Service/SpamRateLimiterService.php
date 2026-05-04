<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\AccountStatus;
use App\Model\CurrentUser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class SpamRateLimiterService
{
    public function __construct(
        #[Autowire(service: 'limiter.spam_thread_creation')]
        private readonly RateLimiterFactory $threadCreationLimiter,
        #[Autowire(service: 'limiter.spam_reply_creation')]
        private readonly RateLimiterFactory $replyCreationLimiter,
        #[Autowire(service: 'limiter.spam_interactions')]
        private readonly RateLimiterFactory $interactionsLimiter,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private int $spamBanDurationMinutes = 720, // 12 hours
    ) {
    }

    public function checkThreadCreationSpam(string $userId): RateLimit
    {
        return $this->threadCreationLimiter->create('thread_creation_' . $userId)->consume(1);
    }

    public function checkReplyCreationSpam(string $userId): RateLimit
    {
        return $this->replyCreationLimiter->create('reply_creation_' . $userId)->consume(1);
    }

    public function checkInteractionSpam(string $userId): RateLimit
    {
        return $this->interactionsLimiter->create('interactions_' . $userId)->consume(1);
    }

    /**
     * Ban a user for spam with 12-hour duration
     */
    public function banUserForSpam(CurrentUser $currentUser): void
    {
        $user = $this->resolveUser($currentUser);
        if (!$user instanceof User) {
            return;
        }

        $user->setAccountStatus(AccountStatus::BANNED->value);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Check if a user is currently banned for spam
     */
    public function isUserBannedForSpam(CurrentUser $currentUser): bool
    {
        $user = $this->resolveUser($currentUser);
        if (!$user instanceof User) {
            return false;
        }

        return $user->getAccountStatus() === AccountStatus::BANNED->value;
    }

    /**
     * Get remaining ban time in seconds
     */
    public function getRemainingBanSeconds(CurrentUser $currentUser): int
    {
        $user = $this->resolveUser($currentUser);
        if (!$user instanceof User || $user->getAccountStatus() !== AccountStatus::BANNED->value) {
            return 0;
        }

        $banEndsAt = $user->getUpdatedAt()->modify(sprintf('+%d minutes', $this->spamBanDurationMinutes));
        $remaining = $banEndsAt->getTimestamp() - time();

        return max(0, $remaining);
    }

    private function resolveUser(CurrentUser $currentUser): ?User
    {
        return $this->userRepository->find($currentUser->getId());
    }

    /**
     * Convert seconds to hours and minutes for display
     */
    public function formatRemainingBanTime(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return sprintf('%d hour%s %d minute%s', 
                $hours, 
                $hours === 1 ? '' : 's',
                $minutes,
                $minutes === 1 ? '' : 's'
            );
        }

        return sprintf('%d minute%s', $minutes, $minutes === 1 ? '' : 's');
    }
}
