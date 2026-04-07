<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SleepSession;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SleepSession>
 */
class SleepSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SleepSession::class);
    }

    /**
     * @return list<SleepSession>
     */
    public function findUserFiltered(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user);

        if (!empty($filters['q'])) {
            $qb->andWhere('s.commentaire LIKE :q')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['quality'])) {
            $qb->andWhere('s.qualite = :quality')
                ->setParameter('quality', (string) $filters['quality']);
        }

        if (!empty($filters['mood'])) {
            $qb->andWhere('s.humeurReveil = :mood')
                ->setParameter('mood', (string) $filters['mood']);
        }

        if (($filters['insufficient'] ?? '') === '1') {
            $qb->andWhere('s.dureeSommeil < :minSleepHours')
                ->setParameter('minSleepHours', 5.0);
        }

        $sortMap = [
            'date' => 's.dateNuit',
            'duration' => 's.dureeSommeil',
            'quality' => 's.qualite',
        ];
        $sort = $sortMap[(string) ($filters['sort'] ?? 'date')] ?? 's.dateNuit';
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

        $qb->orderBy($sort, $direction);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<SleepSession>
     */
    public function findAdminFiltered(array $filters = []): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.user', 'u')
            ->addSelect('u');

        if (!empty($filters['q'])) {
            $qb->andWhere('u.email LIKE :q OR s.commentaire LIKE :q')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['quality'])) {
            $qb->andWhere('s.qualite = :quality')
                ->setParameter('quality', (string) $filters['quality']);
        }

        if (!empty($filters['mood'])) {
            $qb->andWhere('s.humeurReveil = :mood')
                ->setParameter('mood', (string) $filters['mood']);
        }

        $qb->orderBy('s.dateNuit', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{total:int,avgDuration:float,insufficient:int,avgQuality:int}
     */
    public function userStats(User $user): array
    {
        $total = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $avgDuration = $this->createQueryBuilder('s')
            ->select('AVG(s.dureeSommeil)')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $insufficient = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.user = :user')
            ->andWhere('s.dureeSommeil < :minSleepHours')
            ->setParameter('user', $user)
            ->setParameter('minSleepHours', 5.0)
            ->getQuery()
            ->getSingleScalarResult();

        $sessions = $this->findBy(['user' => $user]);
        $qualityMap = ['Excellente' => 100, 'Bonne' => 75, 'Moyenne' => 50, 'Mauvaise' => 25];
        $qualitySum = 0;
        $qualityCount = 0;
        foreach ($sessions as $session) {
            $quality = $session->getQualite();
            if ($quality !== null && isset($qualityMap[$quality])) {
                $qualitySum += $qualityMap[$quality];
                ++$qualityCount;
            }
        }

        return [
            'total' => $total,
            'avgDuration' => $avgDuration !== null ? round((float) $avgDuration, 1) : 0.0,
            'insufficient' => $insufficient,
            'avgQuality' => $qualityCount > 0 ? (int) round($qualitySum / $qualityCount) : 0,
        ];
    }
}
