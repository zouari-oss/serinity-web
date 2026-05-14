<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ConsultationController extends AbstractUserUiController
{
    public function index(EntityManagerInterface $entityManager): Response
    {
        $patient = $this->currentUser();
        if ($patient->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        $sql = <<<'SQL'
            SELECT 
                r.id,
                r.motif,
                r.description,
                r.status,
                r.date_time,
                u.id AS doctor_id,
                u.email AS doctor_email,
                p.firstName,
                p.lastName,
                p.phone,
                p.country,
                p.state
            FROM rendez_vous r
            INNER JOIN users u ON r.doctor_id = u.id
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE r.patient_id = :patientId
            ORDER BY r.date_time DESC
        SQL;

        $rdvs = $entityManager->getConnection()->executeQuery($sql, [
            'patientId' => $patient->getId(),
        ])->fetchAllAssociative();

        return $this->render('rdv/mes_rdv.html.twig', [
            'rdvs' => $rdvs,
            'nav' => $this->buildNav('user_ui_consultations'),
            'userName' => $patient->getEmail(),
        ]);
    }
}
