<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserFace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserFace> */
class UserFaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFace::class);
    }

    public function findOneByUser(User $user): ?UserFace
    {
        return $this->createQueryBuilder('uf')
            ->andWhere('uf.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<UserFace>
     */
    public function findAllEnabledForMatching(): array
    {
        return $this->createQueryBuilder('uf')
            ->innerJoin('uf.user', 'u')
            ->addSelect('u')
            ->andWhere('u.faceRecognitionEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }
}
