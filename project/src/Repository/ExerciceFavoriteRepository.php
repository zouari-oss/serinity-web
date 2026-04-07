<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExerciceFavorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExerciceFavorite> */
class ExerciceFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciceFavorite::class);
    }

    public function findOneForUser(User $user, string $favoriteType, int $itemId): ?ExerciceFavorite
    {
        return $this->findOneBy([
            'user' => $user,
            'favoriteType' => strtoupper($favoriteType),
            'itemId' => $itemId,
        ]);
    }

    /**
     * @return list<ExerciceFavorite>
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }
}
