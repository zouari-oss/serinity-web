<?php

namespace App\Repository;

use App\Entity\Consultation;
use App\Entity\Rapport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consultation>
 */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }



    public function getConsultationsByRapoort(Rapport $id): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.rapport = :rapport')
            ->setParameter('rapport', $id)
            ->orderBy('c.dateConsultation', 'DESC')
            ->getQuery()
            ->getResult();
    }



}
