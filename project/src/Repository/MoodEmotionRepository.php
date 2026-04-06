<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MoodEmotion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MoodEmotion> */
class MoodEmotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MoodEmotion::class);
    }

    /**
     * @param list<string> $names
     * @return list<MoodEmotion>
     */
    public function findByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return $this->createQueryBuilder('emotion')
            ->andWhere('LOWER(emotion.name) IN (:names)')
            ->setParameter('names', array_values(array_unique(array_map('mb_strtolower', $names))))
            ->getQuery()
            ->getResult();
    }
}
