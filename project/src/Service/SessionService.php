<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuthSession;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\AuthSessionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SessionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthSessionRepository $authSessionRepository,
        private TokenGenerator $tokenGenerator,
    ) {
    }

    public function revokeActiveSessions(User $user): void
    {
        foreach ($this->authSessionRepository->findActiveForUser($user) as $session) {
            $session->setRevoked(true);
        }
    }

    public function createSession(User $user): AuthSession
    {
        $role = UserRole::tryFrom($user->getRole()) ?? UserRole::PATIENT;
        $days = $role === UserRole::ADMIN ? 1 : 7;

        $session = (new AuthSession())
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setRefreshToken($this->tokenGenerator->generateRefreshToken())
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d day', $days)))
            ->setRevoked(false)
            ->setUser($user);

        $this->entityManager->persist($session);

        return $session;
    }
}
