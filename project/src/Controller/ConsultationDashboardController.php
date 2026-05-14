<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\RendezVousAcceptType;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Controller\User\AbstractUserUiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ConsultationDashboardController extends AbstractUserUiController
{


     private function analyseEmergency(string $motif, string $description, HttpClientInterface $httpClient): array
    {
 
       

        $prompt = <<<PROMPT
You are a medical triage assistant.

Analyze this appointment request and return ONLY valid JSON.

Request motif: {$motif}
Request description: {$description}

Return exactly this JSON structure:
{
  "level": "low|medium|high|emergency",
  "title": "short title",
  "reason": "short explanation"
}

Rules:
- emergency: chest pain, stroke signs, severe breathing issue, heavy bleeding, loss of consciousness, suicidal intent
- high: strong acute symptoms needing fast review
- medium: moderate but not immediately life-threatening
- low: mild/non-urgent
- output ONLY JSON
PROMPT;

        try {
            $response = $httpClient->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
        'Authorization' => 'Bearer ' . $_ENV['OPENROUTER_API_KEY'],
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'http://localhost',
                    'X-Title' => 'Serinity Therapist Dashboard',
                ],
                'json' => [
                    'model' => 'openai/gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.1,
                ],
            ]);

            $data = $response->toArray(false);
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $decoded = json_decode($content, true);

            if (!is_array($decoded) || !isset($decoded['level'])) {
                return [
                    'level' => 'unknown',
                    'title' => 'Invalid AI output',
                ];
            }

            return [
                'level' => $decoded['level'] ?? 'unknown',
                'title' => $decoded['title'] ?? '',
                'reason' => $decoded['reason'] ?? '',
            ];
        } catch (\Throwable) {
            return [
                'level' => 'unknown',
                'title' => 'AI unavailable',
            ];
        }
    }

#[Route('/user/dashboard/therapist', name: 'app_therapist_rdv', methods: ['GET'])]
public function index(EntityManagerInterface $em, HttpClientInterface $httpClient): Response
{
    $user = $this->currentUser();
    $conn = $em->getConnection();

    /**
     * RDV LIST
     */
    $sqlRdvs = "
        SELECT
            r.id,
            r.date_time,
            r.proposed_date_time,
            r.status,
            r.motif,
            r.description,
            r.patient_id,
            u.email AS patient_email,
            p.firstName,
            p.lastName
        FROM rendez_vous r
        INNER JOIN users u ON u.id = r.patient_id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE r.doctor_id = :doctorId
        ORDER BY r.date_time DESC
    ";

    $rdvs = $conn->executeQuery($sqlRdvs, [
        'doctorId' => $user->getId(),
    ])->fetchAllAssociative();

    /**
     * AI TRIAGE
     */
    foreach ($rdvs as &$rdv) {
        $rdv['ai_level'] = null;
        $rdv['ai_title'] = null;
        $rdv['ai_reason'] = null;

        if (($rdv['status'] ?? '') === 'EN_ATTENTE') {
            $analysis = $this->analyseEmergency(
                (string) ($rdv['motif'] ?? ''),
                (string) ($rdv['description'] ?? ''),
                $httpClient
            );

            $rdv['ai_level'] = $analysis['level'] ?? 'unknown';
            $rdv['ai_title'] = $analysis['title'] ?? '';
            $rdv['ai_reason'] = $analysis['reason'] ?? '';
        }
    }
    unset($rdv);

    /**
     * PATIENTS
     */
    $sqlPatients = "
        SELECT DISTINCT
            u.id,
            u.email,
            p.firstName,
            p.lastName
        FROM rendez_vous r
        INNER JOIN users u ON u.id = r.patient_id
        LEFT JOIN profiles p ON p.user_id = u.id
        WHERE r.doctor_id = :doctorId
        ORDER BY p.firstName ASC
    ";

    $patients = $conn->executeQuery($sqlPatients, [
        'doctorId' => $user->getId(),
    ])->fetchAllAssociative();

    /**
     * ==========================
     * STATISTICS API (4 CHARTS)
     * ==========================
     */

    // 1. RDV by status
    $statusStats = $conn->executeQuery("
        SELECT status, COUNT(*) total
        FROM rendez_vous
        WHERE doctor_id = :doctorId
        GROUP BY status
    ", [
        'doctorId' => $user->getId()
    ])->fetchAllAssociative();

    // 2. RDV by month
    $monthStats = $conn->executeQuery("
        SELECT DATE_FORMAT(date_time,'%Y-%m') month_label, COUNT(*) total
        FROM rendez_vous
        WHERE doctor_id = :doctorId
        GROUP BY DATE_FORMAT(date_time,'%Y-%m')
        ORDER BY month_label ASC
    ", [
        'doctorId' => $user->getId()
    ])->fetchAllAssociative();

    // 3. RDV by weekday
    $dayStats = $conn->executeQuery("
        SELECT DAYNAME(date_time) day_name, COUNT(*) total
        FROM rendez_vous
        WHERE doctor_id = :doctorId
        GROUP BY DAYNAME(date_time)
    ", [
        'doctorId' => $user->getId()
    ])->fetchAllAssociative();

    // 4. Emergency AI Levels
    $emergency = [
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'emergency' => 0
    ];

    foreach ($rdvs as $item) {
        if (!empty($item['ai_level']) && isset($emergency[$item['ai_level']])) {
            $emergency[$item['ai_level']]++;
        }
    }

    /**
     * RENDER
     */
    return $this->render('dashboard/index.html.twig', [
        'currentUser' => $user,
        'rdvs' => $rdvs,
        'patients' => $patients,

        // charts
        'statusStats' => $statusStats,
        'monthStats' => $monthStats,
        'dayStats' => $dayStats,
        'emergencyStats' => $emergency,

        'nav' => $this->buildNav('app_therapist_rdv'),
        'userName' => $user->getEmail(),
    ]);
}

#[Route('/user/dashboard/rdv/{id}', name: 'app_dashboard_rdv_show', methods: ['GET'])]
public function showRdv(RendezVous $rdv): Response
{
    $user = $this->currentUser();

    /**
     * security doctor only
     */
    if ($rdv->getDoctor()?->getId() !== $user->getId()) {
        throw $this->createAccessDeniedException();
    }

        $effectiveDateTime = $rdv->getProposedDateTime() ?? $rdv->getDateTime();

        if (!$effectiveDateTime) {
            throw $this->createNotFoundException('Date du rendez-vous non disponible.');
        }

    $now = new \DateTime();

    $rdvDate = \DateTime::createFromInterface($effectiveDateTime);

    /**
     * open 1h before
     */
    $meetingStart = (clone $rdvDate)->modify('-24 hour');

    /**
     * close 45 min after start
     */
    $meetingEnd = (clone $rdvDate)->modify('+45 minutes');

    $canJoinMeet = $now >= $meetingStart && $now <= $meetingEnd;

    /**
     * waiting minutes
     */
    $secondsLeft = max(0, $meetingStart->getTimestamp() - $now->getTimestamp());
    $minutesLeft = (int) ceil($secondsLeft / 60);

    /**
     * same room as patient
     */
    $roomName = 'serinity-rdv-' . $rdv->getId();

    return $this->render('rdv/showadmin.html.twig', [
        'rdv'          => $rdv,
        'canJoinMeet'  => $canJoinMeet,
        'roomName'     => $roomName,
        'minutesLeft'  => $minutesLeft,
        'meetingStart' => $meetingStart,

        'nav'          => $this->buildNav('app_therapist_rdv'),
        'userName'     => $user->getEmail(),
    ]);
}
    #[Route('/user/rdv/accept/{id}', name: 'app_rdv_accept', methods: ['GET', 'POST'])]
    public function accept(
        RendezVous $rdv,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();

        if ($rdv->getDoctor()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RendezVousAcceptType::class, $rdv, [
            'rendez_vous' => $rdv,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rdv->setStatus('VALIDE');

            $em->flush();

            $this->addFlash('success', 'Rendez-vous validé avec succès.');

            return $this->redirectToRoute('app_therapist_rdv');
        }

        return $this->render('rdv/accept.html.twig', [
            'form'     => $form->createView(),
            'rdv'      => $rdv,
            'nav'      => $this->buildNav('app_therapist_rdv'),
            'userName' => $user->getEmail(),
        ]);
    }

    #[Route('/user/rdv/refuse/{id}', name: 'app_rdv_refuse', methods: ['GET', 'POST'])]
    public function refuse(
        RendezVous $rdv,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();

        if ($rdv->getDoctor()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $rdv->setStatus('REFUSE');

        $em->flush();

        $this->addFlash('success', 'Rendez-vous refusé.');

        return $this->redirectToRoute('app_therapist_rdv');
    }














#[Route('/user/dashboard/therapist/export/excel', name: 'app_therapist_export_excel')]
public function exportExcel(EntityManagerInterface $em): Response
{
    $user = $this->currentUser();

    $conn = $em->getConnection();

    $rows = $conn->executeQuery("
        SELECT 
            r.id,
            r.status,
            r.motif,
            r.description,
            r.date_time,
            u.email patient_email
        FROM rendez_vous r
        INNER JOIN users u ON u.id = r.patient_id
        WHERE r.doctor_id = :doctorId
        ORDER BY r.date_time DESC
    ", [
        'doctorId' => $user->getId()
    ])->fetchAllAssociative();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Patient');
    $sheet->setCellValue('C1', 'Status');
    $sheet->setCellValue('D1', 'Motif');
    $sheet->setCellValue('E1', 'Description');
    $sheet->setCellValue('F1', 'Date');

    $line = 2;

    foreach ($rows as $row) {
        $sheet->setCellValue('A'.$line, $row['id']);
        $sheet->setCellValue('B'.$line, $row['patient_email']);
        $sheet->setCellValue('C'.$line, $row['status']);
        $sheet->setCellValue('D'.$line, $row['motif']);
        $sheet->setCellValue('E'.$line, $row['description']);
        $sheet->setCellValue('F'.$line, $row['date_time']);
        $line++;
    }

    foreach (range('A','F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);

    $response = new StreamedResponse(function () use ($writer) {
        $writer->save('php://output');
    });

    $filename = 'rendezvous-doctor-'.$user->getId().'.xlsx';

    $response->headers->set(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $response->headers->set(
        'Content-Disposition',
        'attachment;filename="'.$filename.'"'
    );

    $response->headers->set('Cache-Control', 'max-age=0');

    return $response;
}

}
