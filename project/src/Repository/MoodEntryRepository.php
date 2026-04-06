<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MoodEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MoodEntry> */
class MoodEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoodEntry::class);
    }

    public function hasDayEntryForDate(User $user, \DateTimeImmutable $entryDate, ?string $excludedEntryId = null): bool
    {
        $dayStart = $entryDate->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $qb = $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->andWhere('entry.user = :user')
            ->andWhere('entry.momentType = :momentType')
            ->andWhere('entry.entryDate >= :dayStart')
            ->andWhere('entry.entryDate < :dayEnd')
            ->setParameter('user', $user)
            ->setParameter('momentType', 'DAY')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd);

        if ($excludedEntryId !== null) {
            $qb->andWhere('entry.id != :excludedEntryId')
                ->setParameter('excludedEntryId', $excludedEntryId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<MoodEntry>
     */
    public function findHistory(
        ?User $user,
        ?string $search,
        ?string $momentType,
        ?\DateTimeImmutable $fromDate,
        ?\DateTimeImmutable $toDate,
        int $limit,
        int $offset,
        ?int $level = null,
    ): array {
        $qb = $this->createBaseHistoryQuery($user, $search, $momentType, $fromDate, $toDate, $level)
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function countHistory(
        ?User $user,
        ?string $search,
        ?string $momentType,
        ?\DateTimeImmutable $fromDate,
        ?\DateTimeImmutable $toDate,
        ?int $level = null,
    ): int {
        return (int) $this->createBaseHistoryQuery($user, $search, $momentType, $fromDate, $toDate, $level)
            ->select('COUNT(DISTINCT entry.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<MoodEntry>
     */
    public function findWithinDateRange(?User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): array
    {
        $qb = $this->createQueryBuilder('entry')
            ->andWhere('entry.entryDate BETWEEN :fromDate AND :toDate')
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->orderBy('entry.entryDate', 'DESC');

        if ($user !== null) {
            $qb->andWhere('entry.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{label:string,usageCount:int}|null
     */
    public function findTopEmotionWithinRange(?User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): ?array
    {
        $qb = $this->createQueryBuilder('entry')
            ->select('emotion.name AS label, COUNT(emotion.id) AS usageCount')
            ->join('entry.emotions', 'emotion')
            ->andWhere('entry.entryDate BETWEEN :fromDate AND :toDate')
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->groupBy('emotion.id, emotion.name')
            ->orderBy('usageCount', 'DESC')
            ->addOrderBy('emotion.name', 'ASC')
            ->setMaxResults(1);

        if ($user !== null) {
            $qb->andWhere('entry.user = :user')->setParameter('user', $user);
        }

        /** @var array{label:string,usageCount:string}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        if ($row === null) {
            return null;
        }

        return [
            'label' => $row['label'],
            'usageCount' => (int) $row['usageCount'],
        ];
    }

    /**
     * @return array{label:string,usageCount:int}|null
     */
    public function findTopInfluenceWithinRange(?User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): ?array
    {
        $qb = $this->createQueryBuilder('entry')
            ->select('influence.name AS label, COUNT(influence.id) AS usageCount')
            ->join('entry.influences', 'influence')
            ->andWhere('entry.entryDate BETWEEN :fromDate AND :toDate')
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->groupBy('influence.id, influence.name')
            ->orderBy('usageCount', 'DESC')
            ->addOrderBy('influence.name', 'ASC')
            ->setMaxResults(1);

        if ($user !== null) {
            $qb->andWhere('entry.user = :user')->setParameter('user', $user);
        }

        /** @var array{label:string,usageCount:string}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        if ($row === null) {
            return null;
        }

        return [
            'label' => $row['label'],
            'usageCount' => (int) $row['usageCount'],
        ];
    }

    public function countTypeWithinRange(?User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate, string $momentType): int
    {
        $qb = $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->andWhere('entry.entryDate BETWEEN :fromDate AND :toDate')
            ->andWhere('entry.momentType = :momentType')
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->setParameter('momentType', $momentType);

        if ($user !== null) {
            $qb->andWhere('entry.user = :user')->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function createBaseHistoryQuery(
        ?User $user,
        ?string $search,
        ?string $momentType,
        ?\DateTimeImmutable $fromDate,
        ?\DateTimeImmutable $toDate,
        ?int $level = null,
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('entry')
            ->leftJoin('entry.emotions', 'emotion')
            ->leftJoin('entry.influences', 'influence')
            ->addSelect('emotion', 'influence')
            ->orderBy('entry.entryDate', 'DESC')
            ->addOrderBy('entry.updatedAt', 'DESC')
            ->distinct();

        if ($user !== null) {
            $qb->andWhere('entry.user = :user')
                ->setParameter('user', $user);
        }

        if ($momentType !== null && $momentType !== '') {
            $qb->andWhere('entry.momentType = :momentType')
                ->setParameter('momentType', $momentType);
        }

        if ($fromDate !== null) {
            $qb->andWhere('entry.entryDate >= :fromDate')
                ->setParameter('fromDate', $fromDate);
        }

        if ($toDate !== null) {
            $qb->andWhere('entry.entryDate <= :toDate')
                ->setParameter('toDate', $toDate);
        }

        if ($level !== null) {
            $qb->andWhere('entry.moodLevel = :level')
                ->setParameter('level', $level);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                'LOWER(emotion.name) LIKE :search
                 OR LOWER(influence.name) LIKE :search
                 OR LOWER(entry.momentType) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb;
    }
}
