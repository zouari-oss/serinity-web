<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExerciceControl> */
class ExerciceControlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciceControl::class);
    }

    /**
     * @return list<ExerciceControl>
     */
    public function findAssignedForUser(User $user): array
    {
        return $this->createQueryBuilder('control')
            ->leftJoin('control.exercice', 'exercice')
            ->addSelect('exercice')
            ->andWhere('control.user = :user')
            ->setParameter('user', $user)
            ->orderBy('control.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneOwnedByUser(User $user, int $id): ?ExerciceControl
    {
        return $this->createQueryBuilder('control')
            ->leftJoin('control.exercice', 'exercice')
            ->addSelect('exercice')
            ->andWhere('control.id = :id')
            ->andWhere('control.user = :user')
            ->setParameter('id', (string) $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAssignment(User $user, Exercice $exercice): ?ExerciceControl
    {
        return $this->findOneBy(['user' => $user, 'exercice' => $exercice]);
    }

    /**
     * @return list<ExerciceControl>
     */
    public function findAdminControls(?string $status = null, ?string $role = null): array
    {
        $qb = $this->createQueryBuilder('control')
            ->leftJoin('control.exercice', 'exercice')->addSelect('exercice')
            ->leftJoin('control.user', 'user')->addSelect('user')
            ->orderBy('control.createdAt', 'DESC');

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('control.status = :status')->setParameter('status', strtoupper(trim($status)));
        }
        if ($role !== null && trim($role) !== '') {
            $qb->andWhere('user.role = :role')->setParameter('role', strtoupper(trim($role)));
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('control')
            ->select('COUNT(control.id)')
            ->andWhere('control.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
