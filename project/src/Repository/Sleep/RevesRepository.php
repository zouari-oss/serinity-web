<?php

namespace App\Repository\Sleep;

use App\Entity\Sleep\Reves;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reves>
 */
class RevesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reves::class);
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
     * Pour les exports / stats / traitements complets
     *
     * @param array<string, mixed> $filters
     * @return array<int, Reves>
     */
    public function findFrontFiltered(array $filters = []): array
    {
        $qb = $this->createFrontFilteredQueryBuilder($filters);

        return $qb->getQuery()->getResult();
    }

    /**

     *
     * @param array<string, mixed> $filters
     * @return array<int, Reves>
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
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.sommeil', 's')
            ->addSelect('s');

        if (!empty($filters['q'])) {
            $qb->andWhere('
                r.titre LIKE :q
                OR r.description LIKE :q
                OR r.emotions LIKE :q
                OR r.typeReve LIKE :q
                OR r.humeur LIKE :q
                OR r.symboles LIKE :q
            ')
                ->setParameter('q', '%' . trim((string) $filters['q']) . '%');
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('r.typeReve = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['recurrent']) && $filters['recurrent'] !== '') {
            $qb->andWhere('r.recurrent = :recurrent')
                ->setParameter('recurrent', (bool) $filters['recurrent']);
        }

        if (isset($filters['couleur']) && $filters['couleur'] !== '') {
            $qb->andWhere('r.couleur = :couleur')
                ->setParameter('couleur', (bool) $filters['couleur']);
        }

        if (!empty($filters['cauchemars']) && $filters['cauchemars'] == '1') {
            $qb->andWhere('LOWER(r.typeReve) = :nightmare')
                ->setParameter('nightmare', 'cauchemar');
        }

        /** @var array<string, string> $allowedSorts */
        $allowedSorts = [
            'date'        => 'r.createdAt',
            'r.createdAt' => 'r.createdAt',
            's.dateNuit'  => 's.dateNuit',
            'titre'       => 'r.titre',
            'r.titre'     => 'r.titre',
            'type'        => 'r.typeReve',
            'r.typeReve'  => 'r.typeReve',
            'humeur'      => 'r.humeur',
            'r.humeur'    => 'r.humeur',
            'intensite'   => 'r.intensite',
            'r.intensite' => 'r.intensite',
        ];

        $sort = (string) ($filters['sort'] ?? 's.dateNuit');
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

        if (!isset($allowedSorts[$sort])) {
            $sort = 's.dateNuit';
        }

        $qb->orderBy($allowedSorts[$sort], $direction);

        return $qb;
    }

    /**
     * @return array{
     *     total: int,
     *     nightmares: int,
     *     avg_intensity: float|int,
     *     attention: bool
     * }
     */
    public function getFrontStats(): array
    {
        $total = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $nightmares = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('LOWER(r.typeReve) = :nightmare')
            ->setParameter('nightmare', 'cauchemar')
            ->getQuery()
            ->getSingleScalarResult();

        $avgIntensity = $this->createQueryBuilder('r')
            ->select('AVG(r.intensite)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total'         => (int) $total,
            'nightmares'    => (int) $nightmares,
            'avg_intensity' => $avgIntensity !== null ? round((float) $avgIntensity, 1) : 0,
            'attention'     => (int) $nightmares >= 3,
        ];
    }
}