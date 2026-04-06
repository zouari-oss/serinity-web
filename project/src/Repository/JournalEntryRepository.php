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
}
