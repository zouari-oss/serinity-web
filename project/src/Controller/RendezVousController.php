<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\RendezVousType;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\User\AbstractUserUiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RendezVousController extends AbstractUserUiController
{

// src/Controller/RendezVousController.php
#[Route('/user/rdv/new/{id}', name: 'app_rdv_new', methods: ['GET', 'POST'])]
public function new(
    string $id,
    Request $request,
    EntityManagerInterface $em
): Response {
    $patient = $this->currentUser();
    if ($patient->getRole() === 'THERAPIST') {
        return $this->redirectToRoute('app_therapist_rdv');
    }

    $doctor = $em->createQueryBuilder()
        ->select('u', 'p')
        ->from(User::class, 'u')
        ->leftJoin('u.profile', 'p')
        ->where('u.id = :id')
        ->setParameter('id', $id)
        ->getQuery()
        ->getOneOrNullResult();

    if (!$doctor instanceof User) {
        throw $this->createNotFoundException('Doctor not found.');
    }

    $rdv = new RendezVous();
    $rdv->setPatient($patient);
    $rdv->setDoctor($doctor);

    $form = $this->createForm(RendezVousType::class, $rdv);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($rdv);
        $em->flush();

        $this->addFlash('success', 'Rendez-vous créé avec succès.');

        return $this->redirectToRoute('app_patient_rdv');
    }

    return $this->render('rdv/new.html.twig', [
        'form'   => $form->createView(),
        'doctor' => $doctor,
         'userName' => $patient->getEmail(),
    ]);
}


    #[Route('/user/mes-rdv', name: 'app_patient_rdv', methods: ['GET'])]
    public function mesRdv(EntityManagerInterface $em): Response
    {
        $patient = $this->currentUser();
        if ($patient->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        $conn = $em->getConnection();

        $sql = "
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
        ";

        $rdvs = $conn->executeQuery($sql, [
            'patientId' => $patient->getId(),
        ])->fetchAllAssociative();

        return $this->render('rdv/mes_rdv.html.twig', [
            'rdvs'     => $rdvs,
            'nav'      => $this->buildNav('app_patient_rdv'),
            'userName' => $patient->getEmail(),
        ]);
    }

    /**
     * GOOGLE CALENDAR DIRECT DOWNLOAD (.ics)
     * no preview
     */
   #[Route('/user/rdv/google-calendar/{id}', name: 'app_rdv_google_calendar', methods: ['GET'])]
public function addGoogleCalendar(
    int $id,
    EntityManagerInterface $em
): Response {
    $user = $this->currentUser();
    if ($user->getRole() === 'THERAPIST') {
        return $this->redirectToRoute('app_therapist_rdv');
    }

    $conn = $em->getConnection();

    $sql = "
        SELECT 
            r.id,
            r.motif,
            r.description,
            r.date_time,
            u.email AS doctor_email,
            p.firstName,
            p.lastName,
            p.country,
            p.state
        FROM rendez_vous r
        INNER JOIN users u ON u.id = r.doctor_id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE r.id = :id
        AND r.patient_id = :patientId
        LIMIT 1
    ";

    $rdv = $conn->executeQuery($sql, [
        'id'        => $id,
        'patientId' => $user->getId()
    ])->fetchAssociative();

    if (!$rdv) {
        throw $this->createNotFoundException('Rendez-vous introuvable');
    }

    $start = new \DateTime($rdv['date_time']);
    $end   = (clone $start)->modify('+30 minutes');

    $doctorName = trim(($rdv['firstName'] ?? '') . ' ' . ($rdv['lastName'] ?? ''));

    $title = 'Consultation médicale avec Dr ' . $doctorName;

    $details = [];
    $details[] = 'Motif : ' . ($rdv['motif'] ?: 'Consultation');
    $details[] = 'Description : ' . ($rdv['description'] ?: 'Aucune');
    $details[] = 'Médecin : Dr ' . $doctorName;
    $details[] = 'Email : ' . ($rdv['doctor_email'] ?: '');

    $desc = implode("\n", $details);

    $location = trim(
        ($rdv['state'] ?? '') . ' ' .
        ($rdv['country'] ?? '')
    );

    if ($location === '') {
        $location = 'Cabinet médical';
    }

    $googleUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . urlencode($title)
        . '&dates=' . $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis')
        . '&details=' . urlencode($desc)
        . '&location=' . urlencode($location)
        . '&sf=true'
        . '&output=xml';

    return $this->redirect($googleUrl);
}

#[Route('/user/rdv/edit/{id}', name: 'app_rdv_edit', methods: ['GET', 'POST'])]
public function edit(
    RendezVous $rdv,
    Request $request,
    EntityManagerInterface $em
): Response {
    $patient = $this->currentUser();
    if ($patient->getRole() === 'THERAPIST') {
        return $this->redirectToRoute('app_therapist_rdv');
    }

    if (!$rdv->getPatient() || $rdv->getPatient()->getId() !== $patient->getId()) {
        $this->addFlash('error', 'Vous ne pouvez pas modifier ce rendez-vous.');

        return $this->redirectToRoute('app_patient_rdv');
    }

    if ($rdv->getStatus() !== 'EN_ATTENTE') {
        $this->addFlash('error', 'Seuls les rendez-vous en attente peuvent être modifiés.');

        return $this->redirectToRoute('app_patient_rdv');
    }

    $form = $this->createForm(RendezVousType::class, $rdv);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();

        $this->addFlash('success', 'Rendez-vous modifié avec succès.');

        return $this->redirectToRoute('app_patient_rdv');
    }

    return $this->render('rdv/edit.html.twig', [
        'form'     => $form->createView(),
        'doctor'   => $rdv->getDoctor(),
        'rdv'      => $rdv,
        'nav'      => $this->buildNav('app_patient_rdv'),
        'userName' => $patient->getEmail(),
    ]);
}


#[Route('/user/rdv/delete/{id}', name: 'app_rdv_delete')]
public function delete(RendezVous $rdv, EntityManagerInterface $em): Response
{
    $patient = $this->currentUser();
    if ($patient->getRole() === 'THERAPIST') {
        return $this->redirectToRoute('app_therapist_rdv');
    }

    $em->remove($rdv);
    $em->flush();

    return $this->redirectToRoute('app_patient_rdv');
}


#[Route('/user/rdv/show/{id}', name: 'app_rdv_show')]
public function show(
    RendezVous $rdv,
    EntityManagerInterface $em
): Response {

    $patient = $this->currentUser();
    if ($patient->getRole() === 'THERAPIST') {
        return $this->redirectToRoute('app_therapist_rdv');
    }

    if ($rdv->getPatient()?->getId() !== $patient->getId()) {
        throw $this->createAccessDeniedException();
    }

    $now = new \DateTime();

    $effectiveDateTime = $rdv->getProposedDateTime() ?? $rdv->getDateTime();

    if (!$effectiveDateTime) {
        throw $this->createNotFoundException('Date du rendez-vous non disponible.');
    }

    $rdvDate = \DateTime::createFromInterface(
        $effectiveDateTime
    );

    /**
     * OPEN 1 HOUR BEFORE
     */
    $meetingStart = (clone $rdvDate)->modify('-24 hour');

    /**
     * CLOSE 45 MIN AFTER
     */
    $meetingEnd = (clone $rdvDate)->modify('+45 minutes');

    $canJoinMeet = $now >= $meetingStart && $now <= $meetingEnd;

    $secondsLeft = max(0, $meetingStart->getTimestamp() - $now->getTimestamp());
    $minutesLeft = (int) ceil($secondsLeft / 60);

    $roomName = 'serinity-rdv-' . $rdv->getId();

    return $this->render('rdv/show.html.twig', [
        'rdv'          => $rdv,
        'canJoinMeet'  => $canJoinMeet,
        'roomName'     => $roomName,
        'minutesLeft'  => $minutesLeft,
        'meetingStart' => $meetingStart,

        'nav'          => $this->buildNav('app_patient_rdv'),
        'userName'     => $patient->getEmail(),
    ]);
}
 
}
