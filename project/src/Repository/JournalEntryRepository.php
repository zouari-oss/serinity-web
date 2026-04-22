<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JournalEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JournalEntry> */
class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    /**
     * @return list<JournalEntry>
     */
    public function findForUser(User $user, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('entry')
            ->andWhere('entry.user = :user')
            ->setParameter('user', $user)
            ->orderBy('entry.createdAt', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(entry.title) LIKE :search OR LOWER(entry.content) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneOwnedByUser(User $user, int $id): ?JournalEntry
    {
        return $this->findOneBy(['id' => (string) $id, 'user' => $user]);
    }

    /**
     * @return list<JournalEntry>
     */
    public function findForUserWithinRange(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('entry')
            ->andWhere('entry.user = :user')
            ->andWhere('entry.createdAt BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $from)
            ->setParameter('toDate', $to)
            ->orderBy('entry.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countEntriesWithinRange(User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): int
    {
        return (int) $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->andWhere('entry.user = :user')
            ->andWhere('entry.createdAt BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctJournalDaysWithinRange(User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): int
    {
        /** @var list<array{createdAt:mixed}> $rows */
        $rows = $this->createQueryBuilder('entry')
            ->select('entry.createdAt AS createdAt')
            ->andWhere('entry.user = :user')
            ->andWhere('entry.createdAt BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->setParameter('toDate', $toDate)
            ->getQuery()
            ->getArrayResult();

        $days = [];
        foreach ($rows as $row) {
            $createdAt = $row['createdAt'];
            if ($createdAt instanceof \DateTimeInterface) {
                $days[$createdAt->format('Y-m-d')] = true;
                continue;
            }

            if (is_string($createdAt) && $createdAt !== '') {
                $days[(new \DateTimeImmutable($createdAt))->format('Y-m-d')] = true;
            }
        }

        return count($days);
    }
}
