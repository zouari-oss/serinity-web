<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use App\Controller\User\AbstractUserUiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DoctorController extends AbstractUserUiController
{


    #[Route('/user/doctors', name: 'app_doctors', methods: ['GET'])]
    public function list(EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if ($user->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        $doctors = $em->createQueryBuilder()
            ->select('u', 'p')
            ->from(User::class, 'u')
            ->leftJoin('u.profile', 'p')
            ->where('u.role LIKE :role')
            ->setParameter('role', '%THERAPIST%')
            ->orderBy('p.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('doctor/list.html.twig', [
            'doctors' => $doctors,
            'nav' => $this->buildNav('app_doctors'),
            'userName' => $user->getEmail(),
        ]);
    }

    #[Route('/user/rdv/save', name: 'app_rdv_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): Response
    {
        $patient = $this->currentUser();
        if ($patient->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        $doctorId = $request->request->get('doctor_id');
        $motif = trim((string) $request->request->get('motif'));
        $description = trim((string) $request->request->get('description'));
        $dateTime = (string) $request->request->get('dateTime');

        $doctor = $em->getRepository(User::class)->find($doctorId);

        if (!$doctor instanceof User) {
            $this->addFlash('error', 'Doctor not found.');

            return $this->redirectToRoute('app_doctors');
        }

        if ($motif === '') {
            $this->addFlash('error', 'Motif is required.');

            return $this->redirectToRoute('app_doctors');
        }

        try {
            $date = new \DateTime($dateTime);
        } catch (\Throwable) {
            $this->addFlash('error', 'Invalid date.');

            return $this->redirectToRoute('app_doctors');
        }

        $rdv = new RendezVous();
        $rdv->setPatient($patient);
        $rdv->setDoctor($doctor);
        $rdv->setMotif($motif);
        $rdv->setDescription($description);
        $rdv->setDateTime($date);

        $em->persist($rdv);
        $em->flush();

        $this->addFlash('success', 'Appointment created successfully.');

        return $this->redirectToRoute('app_doctors');
    }

    #[Route('/user/doctor/{id}', name: 'app_doctor_show', methods: ['GET'])] public function show(string $id, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if ($user->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        $doctor = $em->createQueryBuilder()->select('u', 'p')->from(User::class, 'u')->leftJoin('u.profile', 'p')->where('u.id = :id')->setParameter('id', $id)->getQuery()->getOneOrNullResult();
        if (!$doctor instanceof User) {
            throw $this->createNotFoundException('Doctor not found.');
        }
        $firstName = $doctor->getProfile()?->getFirstName() ?? '';
        $lastName = $doctor->getProfile()?->getLastName() ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $doctor->getEmail();
        }
        $phone = $doctor->getProfile()?->getPhone() ?? '';
        $email = $doctor->getEmail();
        $country = $doctor->getProfile()?->getCountry() ?? '';
        $state = $doctor->getProfile()?->getState() ?? '';
        $address = trim($country . ' ' . $state);    /**     * VCARD     */
        $vcard = "BEGIN:VCARD\r\n";
        $vcard .= "VERSION:3.0\r\n";
        $vcard .= "N:{$lastName};{$firstName};;;\r\n";
        $vcard .= "FN:{$fullName}\r\n";
        $vcard .= "ORG:Serinity\r\n";
        $vcard .= "TITLE:Therapist\r\n";
        if ($phone !== '') {
            $vcard .= "TEL;TYPE=CELL:{$phone}\r\n";
        }
        $vcard .= "EMAIL:{$email}\r\n";
        if ($address !== '') {
            $vcard .= "ADR:;;{$address};;;;\r\n";
        }
        $vcard .= "END:VCARD";    /**     * OLD VERSION BUNDLE FIX     */
        $builder = new Builder(writer: new PngWriter(), data: $vcard, size: 260, margin: 10);
        $result = $builder->build();
        return $this->render('doctor/show.html.twig', ['doctor' => $doctor, 'qrCode' => $result->getDataUri(), 'nav' => $this->buildNav('app_doctors'), 'userName' => $user->getEmail(),]);
    }
}
