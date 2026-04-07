<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Exercice> */
class ExerciceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercice::class);
    }

    /**
     * @return list<Exercice>
     */
    public function findCatalog(?string $search = null, ?string $type = null, ?bool $active = null): array
    {
        $qb = $this->createQueryBuilder('exercice')
            ->orderBy('exercice.level', 'ASC')
            ->addOrderBy('exercice.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(exercice.title) LIKE :search OR LOWER(exercice.description) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower(trim($search)) . '%');
        }
        if ($type !== null && trim($type) !== '') {
            $qb->andWhere('exercice.type = :type')->setParameter('type', trim($type));
        }
        if ($active !== null) {
            $qb->andWhere('exercice.isActive = :active')->setParameter('active', $active);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<string>
     */
    public function findTypes(): array
    {
        $rows = $this->createQueryBuilder('exercice')
            ->select('DISTINCT exercice.type AS type')
            ->orderBy('exercice.type', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(static fn(array $row): string => (string) $row['type'], $rows)));
    }
}
