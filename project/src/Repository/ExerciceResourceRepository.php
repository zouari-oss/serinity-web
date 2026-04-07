<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercice;
use App\Entity\ExerciceResource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ExerciceResource> */
class ExerciceResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciceResource::class);
    }

    /**
     * @return list<ExerciceResource>
     */
    public function findForExercice(Exercice $exercice): array
    {
        return $this->findBy(['exercice' => $exercice], ['createdAt' => 'DESC']);
    }
}
