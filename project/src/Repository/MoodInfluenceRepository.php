<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MoodInfluence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MoodInfluence> */
class MoodInfluenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoodInfluence::class);
    }

    /**
     * @param list<string> $names
     * @return list<MoodInfluence>
     */
    public function findByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return $this->createQueryBuilder('influence')
            ->andWhere('LOWER(influence.name) IN (:names)')
            ->setParameter('names', array_values(array_unique(array_map('mb_strtolower', $names))))
            ->getQuery()
            ->getResult();
    }
}
