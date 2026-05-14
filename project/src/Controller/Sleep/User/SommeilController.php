<?php

namespace App\Controller\Sleep\User;

use App\Entity\Sleep\Sommeil;
use App\Form\Sleep\SommeilType;
use App\Repository\Sleep\SommeilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/sommeil')]
final class SommeilController extends AbstractController
{
    #[Route('/', name: 'app_sommeil_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_sommeil_list');
    }

    #[Route('/list', name: 'app_sommeil_list', methods: ['GET'])]
    public function list(
        Request $request,
        SommeilRepository $sommeilRepository,
        PaginatorInterface $paginator
    ): Response {
        /** @var array<string, mixed> $filters */
        $filters = [
            'q'           => $request->query->get('q'),
            'qualite'     => $request->query->get('qualite'),
            'humeur'      => $request->query->get('humeur'),
            'insuffisant' => $request->query->get('insuffisant'),
            'sort'        => $request->query->get('sort', 's.dateNuit'),
            'direction'   => $request->query->get('direction', 'DESC'),
        ];

        $query = $sommeilRepository->createFrontFilteredQuery($filters);

        $sommeils = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            6
        );

        $allFilteredSommeils = $sommeilRepository->findFrontFiltered($filters);
        $stats               = $sommeilRepository->getFrontStats();

        $qualityCounts = [
            'Excellente' => 0,
            'Bonne'      => 0,
            'Moyenne'    => 0,
            'Mauvaise'   => 0,
        ];

        $wakeMoodCounts = [
            'Reposé'   => 0,
            'Joyeux'   => 0,
            'Neutre'   => 0,
            'Fatigué'  => 0,
            'Énergisé' => 0,
        ];

        $sleepDurationLabels = [];
        $sleepDurationData   = [];

        foreach ($allFilteredSommeils as $sommeil) {
            $qualite = $sommeil->getQualite() ?? '';
            if (array_key_exists($qualite, $qualityCounts)) {
                $qualityCounts[$qualite]++;
            }

            $dateLabel             = $sommeil->getDateNuit()?->format('d/m') ?? ('#' . $sommeil->getId());
            $sleepDurationLabels[] = $dateLabel;
            $sleepDurationData[]   = (float) ($sommeil->getDureeSommeil() ?? 0);

            $humeur = trim((string) ($sommeil->getHumeurReveil() ?? ''));
            $humeurClean = str_replace(
                ['😌 ', '😄 ', '😐 ', '😴 ', '⚡ '],
                '',
                $humeur
            );

            if (array_key_exists($humeurClean, $wakeMoodCounts)) {
                $wakeMoodCounts[$humeurClean]++;
            }
        }

        $stats['avg_quality'] = (int) round(
            (
                $qualityCounts['Excellente'] * 100 +
                $qualityCounts['Bonne']      * 75 +
                $qualityCounts['Moyenne']    * 50 +
                $qualityCounts['Mauvaise']   * 25
            ) / max(count($allFilteredSommeils), 1)
        );

        $stats['qualite_excellente']    = $qualityCounts['Excellente'];
        $stats['qualite_bonne']         = $qualityCounts['Bonne'];
        $stats['qualite_moyenne']       = $qualityCounts['Moyenne'];
        $stats['qualite_mauvaise']      = $qualityCounts['Mauvaise'];
        $stats['sleep_duration_labels'] = $sleepDurationLabels;
        $stats['sleep_duration_data']   = $sleepDurationData;
        $stats['wake_mood_labels']      = array_keys($wakeMoodCounts);
        $stats['wake_mood_data']        = array_values($wakeMoodCounts);

        return $this->render('sleep/sommeil/list.html.twig', [
            'sommeils' => $sommeils,
            'filters'  => $filters,
            'stats'    => $stats,
        ]);
    }

    #[Route('/ml-widget', name: 'ml_widget', methods: ['GET'])]
    public function mlWidget(): Response
    {
        return $this->render('sleep/sommeil/components/_ml_widget_inner.html.twig');
    }

    #[Route('/new', name: 'app_sommeil_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $sommeil = new Sommeil();
        $form    = $this->createForm(SommeilType::class, $sommeil);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $sommeil->setCreatedAt(new \DateTimeImmutable());
                $sommeil->setUpdatedAt(new \DateTimeImmutable());
                $user = $this->getUser();
                if (!$user instanceof User) {
                    throw $this->createAccessDeniedException('Utilisateur non connecté.');
                }

                $sommeil->setUser($user);

                $em->persist($sommeil);
                $em->flush();

                $this->addFlash('success', 'Nuit de sommeil ajoutée avec succès !');

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                    ]);
                }

                return $this->redirectToRoute('app_sommeil_list');
            }

            foreach ($this->getFormErrors($form) as $error) {
                $this->addFlash('error', $error);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/sommeil/components/_form_modal.html.twig', [
                'form'       => $form->createView(),
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/sommeil/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/show/{id<\d+>}', name: 'app_sommeil_show', methods: ['GET'])]
    public function show(Request $request, Sommeil $sommeil): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/sommeil/components/_show_modal.html.twig', [
                'sommeil'    => $sommeil,
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/sommeil/show.html.twig', [
            'sommeil' => $sommeil,
        ]);
    }

    #[Route('/edit/{id<\d+>}', name: 'app_sommeil_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sommeil $sommeil, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SommeilType::class, $sommeil);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $sommeil->setUpdatedAt(new \DateTimeImmutable());
                $em->flush();

                $this->addFlash('success', 'Nuit de sommeil modifiée avec succès !');

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                    ]);
                }

                return $this->redirectToRoute('app_sommeil_list');
            }

            foreach ($this->getFormErrors($form) as $error) {
                $this->addFlash('error', $error);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/sommeil/components/_form_modal.html.twig', [
                'sommeil'    => $sommeil,
                'form'       => $form->createView(),
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/sommeil/edit.html.twig', [
            'sommeil' => $sommeil,
            'form'    => $form->createView(),
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'app_sommeil_delete', methods: ['POST'])]
    public function delete(Request $request, Sommeil $sommeil, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $sommeil->getId(), (string) $request->request->get('_token'))) {
            $em->remove($sommeil);
            $em->flush();

            $this->addFlash('success', 'Nuit de sommeil supprimée.');
        }

        return $this->redirectToRoute('app_sommeil_list');
    }

    #[Route('/export/csv', name: 'app_sommeil_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, SommeilRepository $sommeilRepository): Response
    {
        /** @var array<string, mixed> $filters */
        $filters = [
            'q'           => $request->query->get('q'),
            'qualite'     => $request->query->get('qualite'),
            'humeur'      => $request->query->get('humeur'),
            'insuffisant' => $request->query->get('insuffisant'),
            'sort'        => $request->query->get('sort', 's.dateNuit'),
            'direction'   => $request->query->get('direction', 'DESC'),
        ];

        $rows = $sommeilRepository->findFrontFiltered($filters);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="sommeils.csv"');

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le flux CSV');
        }

        fputcsv($handle, ['Date', 'Heure coucher', 'Heure réveil', 'Durée', 'Qualité', 'Humeur', 'Statut']);

        foreach ($rows as $s) {
            fputcsv($handle, [
                $s->getDateNuit()?->format('Y-m-d'),
                $s->getHeureCoucher(),
                $s->getHeureReveil(),
                $s->getDureeSommeil(),
                $s->getQualite(),
                $s->getHumeurReveil(),
                $s->isSommeilInsuffisant() ? 'Sommeil insuffisant' : 'Normal',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response->setContent($content);

        return $response;
    }

    #[Route('/export/pdf', name: 'app_sommeil_export_pdf', methods: ['GET'])]
    public function exportPdf(
        Request $request,
        SommeilRepository $sommeilRepository,
        GotenbergPdfInterface $gotenberg
    ): Response {
        /** @var array<string, mixed> $filters */
        $filters = [
            'q'           => $request->query->get('q'),
            'qualite'     => $request->query->get('qualite'),
            'humeur'      => $request->query->get('humeur'),
            'insuffisant' => $request->query->get('insuffisant'),
            'sort'        => $request->query->get('sort', 's.dateNuit'),
            'direction'   => $request->query->get('direction', 'DESC'),
        ];

        $sommeils = $sommeilRepository->findFrontFiltered($filters);

        return $gotenberg->html()
            ->content('sleep/sommeil/export_pdf.html.twig', [
                'sommeils' => $sommeils,
                'filters' => $filters,
                'generatedAt' => new \DateTimeImmutable(),
            ])
            ->generate()
            ->stream();
    }

    /**
     * @return array<int, string>
     */
    private function getFormErrors(FormInterface $form): array
    {
        /** @var array<int, string> $errors */
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            if ($error instanceof FormError) {
                $message = $error->getMessage();
                if (!in_array($message, $errors, true)) {
                    $errors[] = $message;
                }
            }
        }

        return $errors;
    }
}