<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SleepDream;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SleepDream>
 */
class SleepDreamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SleepDream::class);
    }

    /**
     * @return list<SleepDream>
     */
    public function findUserFiltered(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.sommeilId', 's')
            ->addSelect('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user);

        if (!empty($filters['q'])) {
            $qb->andWhere('d.titre LIKE :q OR d.description LIKE :q OR d.typeReve LIKE :q OR d.emotions LIKE :q OR d.symboles LIKE :q')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.typeReve = :type')
                ->setParameter('type', (string) $filters['type']);
        }

        if (!empty($filters['mood'])) {
            $qb->andWhere('d.humeur = :mood')
                ->setParameter('mood', (string) $filters['mood']);
        }

        if (($filters['recurring'] ?? '') !== '') {
            $qb->andWhere('d.recurrent = :recurring')
                ->setParameter('recurring', filter_var((string) $filters['recurring'], FILTER_VALIDATE_BOOLEAN));
        }

        if (($filters['nightmares'] ?? '') === '1') {
            $qb->andWhere('LOWER(d.typeReve) = :nightmare')
                ->setParameter('nightmare', 'cauchemar');
        }

        $sortMap = [
            'date' => 'd.createdAt',
            'title' => 'd.titre',
            'type' => 'd.typeReve',
            'intensity' => 'd.intensite',
        ];
        $sort = $sortMap[(string) ($filters['sort'] ?? 'date')] ?? 'd.createdAt';
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $qb->orderBy($sort, $direction);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<SleepDream>
     */
    public function findAdminFiltered(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.sommeilId', 's')
            ->addSelect('s')
            ->join('s.user', 'u')
            ->addSelect('u');

        if (!empty($filters['q'])) {
            $qb->andWhere('u.email LIKE :q OR d.titre LIKE :q OR d.typeReve LIKE :q OR d.description LIKE :q OR d.emotions LIKE :q OR d.symboles LIKE :q')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.typeReve = :type')
                ->setParameter('type', (string) $filters['type']);
        }

        if (($filters['recurring'] ?? '') !== '') {
            $qb->andWhere('d.recurrent = :recurring')
                ->setParameter('recurring', filter_var((string) $filters['recurring'], FILTER_VALIDATE_BOOLEAN));
        }

        $qb->orderBy('d.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{total:int,nightmares:int,attention:bool,avgIntensity:float}
     */
    public function userStats(User $user): array
    {
        $total = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.sommeilId', 's')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $nightmares = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->join('d.sommeilId', 's')
            ->andWhere('s.user = :user')
            ->andWhere('LOWER(d.typeReve) = :nightmare')
            ->setParameter('user', $user)
            ->setParameter('nightmare', 'cauchemar')
            ->getQuery()
            ->getSingleScalarResult();

        $avgIntensity = $this->createQueryBuilder('d')
            ->select('AVG(d.intensite)')
            ->join('d.sommeilId', 's')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'nightmares' => $nightmares,
            'attention' => $nightmares >= 3,
            'avgIntensity' => $avgIntensity !== null ? round((float) $avgIntensity, 1) : 0.0,
        ];
    }
}
