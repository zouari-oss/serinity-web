<?php

namespace App\Repository\Sleep;

use App\Entity\Sleep\Sommeil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sommeil>
 */
class SommeilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sommeil::class);
    }

    /**
     * Pour la pagination KNP
     *
     * @param array<string, mixed> $filters
     */
    public function createFrontFilteredQuery(array $filters = []): Query
    {
        $qb = $this->createFrontFilteredQueryBuilder($filters);

        return $qb->getQuery();
    }

    /**
     *
     * @param array<string, mixed> $filters
     * @return array<int, Sommeil>
     */
    public function findFrontFiltered(array $filters = []): array
    {
        $qb = $this->createFrontFilteredQueryBuilder($filters);

        return $qb->getQuery()->getResult();
    }

    /**
     *
     * @param array<string, mixed> $filters
     * @return array<int, Sommeil>
     */
    public function findFrontFilteredForExport(array $filters = []): array
    {
        return $this->findFrontFiltered($filters);
    }

    /**
     * QueryBuilder centralisé
     *
     * @param array<string, mixed> $filters
     */
    private function createFrontFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s');

        if (!empty($filters['q'])) {
            $qb->andWhere('
                s.commentaire LIKE :q
                OR s.qualite LIKE :q
                OR s.humeurReveil LIKE :q
                OR s.environnement LIKE :q
            ')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['qualite'])) {
            $qb->andWhere('s.qualite = :qualite')
                ->setParameter('qualite', $filters['qualite']);
        }

        if (!empty($filters['humeur'])) {
            $qb->andWhere('s.humeurReveil = :humeur')
                ->setParameter('humeur', $filters['humeur']);
        }

        if (!empty($filters['insuffisant']) && $filters['insuffisant'] == '1') {
            $qb->andWhere('s.dureeSommeil < :minSleep')
                ->setParameter('minSleep', 5);
        }

        $sort = (string) ($filters['sort'] ?? 's.dateNuit');
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

        if (in_array($sort, ['qualite', 's.qualite'], true)) {
            $qb->addSelect("
                CASE
                    WHEN s.qualite = 'Excellente' THEN 4
                    WHEN s.qualite = 'Bonne' THEN 3
                    WHEN s.qualite = 'Moyenne' THEN 2
                    WHEN s.qualite = 'Mauvaise' THEN 1
                    ELSE 0
                END AS HIDDEN qualite_order
            ");
            $qb->orderBy('qualite_order', $direction);

            return $qb;
        }

        /** @var array<string, string> $allowedSorts */
        $allowedSorts = [
            'dateNuit'        => 's.dateNuit',
            's.dateNuit'      => 's.dateNuit',
            'date'            => 's.dateNuit',
            'duree'           => 's.dureeSommeil',
            's.dureeSommeil'  => 's.dureeSommeil',
            'interruptions'   => 's.interruptions',
            's.interruptions' => 's.interruptions',
        ];

        if (!isset($allowedSorts[$sort])) {
            $sort = 's.dateNuit';
        }

        $qb->orderBy($allowedSorts[$sort], $direction);

        return $qb;
    }

    /**
     * @return array{
     *     total: int,
     *     avg_duration: float|int,
     *     insufficient: int
     * }
     */
    public function getFrontStats(): array
    {
        $qb = $this->createQueryBuilder('s');

        $total = (clone $qb)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $avgDuration = $this->createQueryBuilder('s')
            ->select('AVG(s.dureeSommeil)')
            ->getQuery()
            ->getSingleScalarResult();

        $insufficient = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.dureeSommeil < :minSleep')
            ->setParameter('minSleep', 5)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total'        => (int) $total,
            'avg_duration' => $avgDuration !== null ? round((float) $avgDuration, 1) : 0,
            'insufficient' => (int) $insufficient,
        ];
    }
}