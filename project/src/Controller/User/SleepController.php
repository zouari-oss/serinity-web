<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Service\User\UserSleepService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SleepController extends AbstractUserUiController
{
    public function __construct(
        private readonly UserSleepService $userSleepService,
    ) {
    }

    #[Route('/sommeil/list', name: 'app_sommeil_list', methods: ['GET'])]
    #[Route('/user/sommeil/list', name: 'user_ui_sommeil_list', methods: ['GET'])]
    public function sleepList(Request $request): Response
    {
        $user = $this->currentUser();
        $filters = $this->sleepFilters($request);
        $payload = $this->userSleepService->listSessions($user, $filters);

        return $this->render('user/pages/sommeil_list.html.twig', [
            'nav' => $this->buildNav('user_ui_sommeil_list'),
            'userName' => $user->getEmail(),
            'sommeils' => $payload['items'],
            'stats' => $payload['stats'],
            'filters' => $filters,
        ]);
    }

    #[Route('/sommeil/new', name: 'app_sommeil_new', methods: ['GET', 'POST'])]
    #[Route('/user/sommeil/new', name: 'user_ui_sommeil_new', methods: ['GET', 'POST'])]
    public function sleepNew(Request $request): Response
    {
        $user = $this->currentUser();
        if ($request->isMethod('POST')) {
            $result = $this->userSleepService->createSession($user, $request->request->all());
            $this->addFlash($result->success ? 'success' : 'error', $result->message);
            if ($result->success) {
                return $this->redirectToRoute('user_ui_sommeil_list');
            }
        }

        return $this->render('user/pages/sommeil_form.html.twig', [
            'nav' => $this->buildNav('user_ui_sommeil_list'),
            'userName' => $user->getEmail(),
            'mode' => 'create',
            'session' => null,
            'qualities' => array_values(\App\Entity\SleepSession::QUALITY_LABELS),
        ]);
    }

    #[Route('/sommeil/show/{id<\d+>}', name: 'app_sommeil_show', methods: ['GET'])]
    #[Route('/user/sommeil/show/{id<\d+>}', name: 'user_ui_sommeil_show', methods: ['GET'])]
    public function sleepShow(int $id): Response
    {
        $user = $this->currentUser();
        $session = null;
        foreach ($this->userSleepService->listSessions($user)['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $session = $item;
                break;
            }
        }

        if ($session === null) {
            $this->addFlash('error', 'Sleep session not found.');

            return $this->redirectToRoute('user_ui_sommeil_list');
        }

        return $this->render('user/pages/sommeil_show.html.twig', [
            'nav' => $this->buildNav('user_ui_sommeil_list'),
            'userName' => $user->getEmail(),
            'session' => $session,
        ]);
    }

    #[Route('/sommeil/edit/{id<\d+>}', name: 'app_sommeil_edit', methods: ['GET', 'POST'])]
    #[Route('/user/sommeil/edit/{id<\d+>}', name: 'user_ui_sommeil_edit', methods: ['GET', 'POST'])]
    public function sleepEdit(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        $session = null;
        foreach ($this->userSleepService->listSessions($user)['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $session = $item;
                break;
            }
        }
        if ($session === null) {
            $this->addFlash('error', 'Sleep session not found.');

            return $this->redirectToRoute('user_ui_sommeil_list');
        }

        if ($request->isMethod('POST')) {
            $result = $this->userSleepService->updateSession($user, $id, $request->request->all());
            $this->addFlash($result->success ? 'success' : 'error', $result->message);
            if ($result->success) {
                return $this->redirectToRoute('user_ui_sommeil_list');
            }
        }

        return $this->render('user/pages/sommeil_form.html.twig', [
            'nav' => $this->buildNav('user_ui_sommeil_list'),
            'userName' => $user->getEmail(),
            'mode' => 'edit',
            'session' => $session,
            'qualities' => array_values(\App\Entity\SleepSession::QUALITY_LABELS),
        ]);
    }

    #[Route('/sommeil/delete/{id<\d+>}', name: 'app_sommeil_delete', methods: ['POST'])]
    #[Route('/user/sommeil/delete/{id<\d+>}', name: 'user_ui_sommeil_delete', methods: ['POST'])]
    public function sleepDelete(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('delete_sommeil_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('user_ui_sommeil_list');
        }

        $result = $this->userSleepService->deleteSession($user, $id);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('user_ui_sommeil_list');
    }

    #[Route('/sommeil/export/csv', name: 'app_sommeil_export_csv', methods: ['GET'])]
    #[Route('/user/sommeil/export/csv', name: 'user_ui_sommeil_export_csv', methods: ['GET'])]
    public function sleepExportCsv(Request $request): Response
    {
        $user = $this->currentUser();
        $rows = $this->userSleepService->listSessions($user, $this->sleepFilters($request))['items'];

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="sommeils.csv"');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Heure coucher', 'Heure reveil', 'Duree', 'Qualite', 'Humeur reveil', 'Interruptions', 'Environnement', 'Temperature', 'Bruit', 'Statut']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['sleepDate'] ?? '',
                $row['bedTime'] ?? '',
                $row['wakeTime'] ?? '',
                $row['sleepDuration'] ?? '',
                $row['quality'] ?? '',
                $row['humeur_reveil'] ?? '',
                $row['interruptions'] ?? '',
                $row['environnement'] ?? '',
                $row['temperature'] ?? '',
                $row['bruit_niveau'] ?? '',
                ($row['insufficient'] ?? false) ? 'Sommeil insuffisant' : 'Normal',
            ]);
        }
        rewind($handle);
        $response->setContent((string) stream_get_contents($handle));
        fclose($handle);

        return $response;
    }

    #[Route('/sommeil/export/pdf', name: 'app_sommeil_export_pdf', methods: ['GET'])]
    #[Route('/user/sommeil/export/pdf', name: 'user_ui_sommeil_export_pdf', methods: ['GET'])]
    public function sleepExportPdf(Request $request): Response
    {
        $user = $this->currentUser();
        $rows = $this->userSleepService->listSessions($user, $this->sleepFilters($request))['items'];
        $html = $this->renderView('user/pages/sommeil_export_pdf.html.twig', ['rows' => $rows]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="sommeils.pdf"',
        ]);
    }

    #[Route('/reve', name: 'app_reve_index', methods: ['GET'])]
    #[Route('/user/reve', name: 'user_ui_reve_index', methods: ['GET'])]
    public function dreamIndex(Request $request): Response
    {
        $user = $this->currentUser();
        $filters = $this->dreamFilters($request);
        $payload = $this->userSleepService->listDreams($user, $filters);
        $sessions = $this->userSleepService->listSessions($user)['items'];

        return $this->render('user/pages/reves_management.html.twig', [
            'nav' => $this->buildNav('user_ui_reve_index'),
            'userName' => $user->getEmail(),
            'reves' => $payload['items'],
            'stats' => $payload['stats'],
            'filters' => $filters,
            'sessions' => $sessions,
            'dreamTypes' => \App\Entity\SleepDream::DREAM_TYPES,
        ]);
    }

    #[Route('/reve/new', name: 'app_reve_new', methods: ['GET', 'POST'])]
    #[Route('/user/reve/new', name: 'user_ui_reve_new', methods: ['GET', 'POST'])]
    public function dreamNew(Request $request): Response
    {
        $user = $this->currentUser();
        if ($request->isMethod('POST')) {
            $result = $this->userSleepService->createDream($user, $request->request->all());
            $this->addFlash($result->success ? 'success' : 'error', $result->message);
            if ($result->success) {
                return $this->redirectToRoute('user_ui_reve_index');
            }
        }

        return $this->render('user/pages/reve_form.html.twig', [
            'nav' => $this->buildNav('user_ui_reve_index'),
            'userName' => $user->getEmail(),
            'mode' => 'create',
            'dream' => null,
            'sessions' => $this->userSleepService->listSessions($user)['items'],
            'dreamTypes' => \App\Entity\SleepDream::DREAM_TYPES,
        ]);
    }

    #[Route('/reve/show/{id<\d+>}', name: 'app_reve_show', methods: ['GET'])]
    #[Route('/user/reve/show/{id<\d+>}', name: 'user_ui_reve_show', methods: ['GET'])]
    public function dreamShow(int $id): Response
    {
        $user = $this->currentUser();
        $dream = null;
        foreach ($this->userSleepService->listDreams($user)['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $dream = $item;
                break;
            }
        }

        if ($dream === null) {
            $this->addFlash('error', 'Dream not found.');

            return $this->redirectToRoute('user_ui_reve_index');
        }

        return $this->render('user/pages/reve_show.html.twig', [
            'nav' => $this->buildNav('user_ui_reve_index'),
            'userName' => $user->getEmail(),
            'dream' => $dream,
        ]);
    }

    #[Route('/reve/edit/{id<\d+>}', name: 'app_reve_edit', methods: ['GET', 'POST'])]
    #[Route('/user/reve/edit/{id<\d+>}', name: 'user_ui_reve_edit', methods: ['GET', 'POST'])]
    public function dreamEdit(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        $dream = null;
        foreach ($this->userSleepService->listDreams($user)['items'] as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $dream = $item;
                break;
            }
        }
        if ($dream === null) {
            $this->addFlash('error', 'Dream not found.');

            return $this->redirectToRoute('user_ui_reve_index');
        }

        if ($request->isMethod('POST')) {
            $result = $this->userSleepService->updateDream($user, $id, $request->request->all());
            $this->addFlash($result->success ? 'success' : 'error', $result->message);
            if ($result->success) {
                return $this->redirectToRoute('user_ui_reve_index');
            }
        }

        return $this->render('user/pages/reve_form.html.twig', [
            'nav' => $this->buildNav('user_ui_reve_index'),
            'userName' => $user->getEmail(),
            'mode' => 'edit',
            'dream' => $dream,
            'sessions' => $this->userSleepService->listSessions($user)['items'],
            'dreamTypes' => \App\Entity\SleepDream::DREAM_TYPES,
        ]);
    }

    #[Route('/reve/delete/{id<\d+>}', name: 'app_reve_delete', methods: ['POST'])]
    #[Route('/user/reve/delete/{id<\d+>}', name: 'user_ui_reve_delete', methods: ['POST'])]
    public function dreamDelete(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('delete_reve_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('user_ui_reve_index');
        }

        $result = $this->userSleepService->deleteDream($user, $id);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('user_ui_reve_index');
    }

    #[Route('/reve/export/csv', name: 'app_reve_export_csv', methods: ['GET'])]
    #[Route('/user/reve/export/csv', name: 'user_ui_reve_export_csv', methods: ['GET'])]
    public function dreamExportCsv(Request $request): Response
    {
        $user = $this->currentUser();
        $rows = $this->userSleepService->listDreams($user, $this->dreamFilters($request))['items'];

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="reves.csv"');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Titre', 'Type', 'Humeur', 'Intensite', 'Couleur', 'Recurrent', 'Emotions', 'Symboles', 'Description']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['sleepDate'] ?? (isset($row['createdAt']) ? substr((string) $row['createdAt'], 0, 10) : ''),
                $row['title'] ?? '',
                $row['dreamType'] ?? '',
                $row['humeur'] ?? '',
                $row['intensite'] ?? $row['intensity'] ?? '',
                ($row['couleur'] ?? $row['isColor'] ?? false) ? 'Oui' : 'Non',
                ($row['recurrent'] ?? $row['isRecurring'] ?? false) ? 'Oui' : 'Non',
                $row['emotions'] ?? '',
                $row['symboles'] ?? '',
                $row['description'] ?? '',
            ]);
        }
        rewind($handle);
        $response->setContent((string) stream_get_contents($handle));
        fclose($handle);

        return $response;
    }

    #[Route('/reve/export/pdf', name: 'app_reve_export_pdf', methods: ['GET'])]
    #[Route('/user/reve/export/pdf', name: 'user_ui_reve_export_pdf', methods: ['GET'])]
    public function dreamExportPdf(Request $request): Response
    {
        $user = $this->currentUser();
        $rows = $this->userSleepService->listDreams($user, $this->dreamFilters($request))['items'];
        $html = $this->renderView('user/pages/reve_export_pdf.html.twig', ['rows' => $rows]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reves.pdf"',
        ]);
    }

    /**
     * @return array{q:?string,quality:?string,insufficient:string,sort:string,direction:string}
     */
    private function sleepFilters(Request $request): array
    {
        return [
            'q' => $this->queryString($request, 'q'),
            'quality' => $this->queryString($request, 'quality'),
            'insufficient' => (string) $request->query->get('insufficient', ''),
            'sort' => (string) $request->query->get('sort', 'date'),
            'direction' => (string) $request->query->get('direction', 'DESC'),
        ];
    }

    /**
     * @return array{q:?string,type:?string,mood:?string,recurring:string,nightmares:string,sort:string,direction:string}
     */
    private function dreamFilters(Request $request): array
    {
        return [
            'q' => $this->queryString($request, 'q'),
            'type' => $this->queryString($request, 'type'),
            'mood' => $this->queryString($request, 'mood'),
            'recurring' => (string) $request->query->get('recurring', ''),
            'nightmares' => (string) $request->query->get('nightmares', ''),
            'sort' => (string) $request->query->get('sort', 'date'),
            'direction' => (string) $request->query->get('direction', 'DESC'),
        ];
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);
        if (!is_scalar($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
