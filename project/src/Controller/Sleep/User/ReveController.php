<?php

namespace App\Controller\Sleep\User;

use App\Entity\Sleep\Reves;
use App\Form\Sleep\ReveType;
use App\Repository\Sleep\RevesRepository;
use App\Service\Sleep\LmStudioService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormError;

#[Route('/reve')]
final class ReveController extends AbstractController
{
    #[Route('', name: 'app_reve_index', methods: ['GET'])]
    public function index(
        Request            $request,
        RevesRepository    $revesRepository,
        PaginatorInterface $paginator
    ): Response
    {
        /** @var array<string, mixed> $filters */
        $filters = [
            'q' => $request->query->get('q'),
            'type' => $request->query->get('type'),
            'recurrent' => $request->query->get('recurrent', ''),
            'couleur' => $request->query->get('couleur'),
            'cauchemars' => $request->query->get('cauchemars'),
            'sort' => $request->query->get('sort', 's.dateNuit'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];

        $query = $revesRepository->createFrontFilteredQuery($filters);

        $reves = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            6
        );

        $allReves = $revesRepository->findFrontFiltered($filters);
        $stats = $revesRepository->getFrontStats();

        $dreamMoodCounts = [
            '😄 Joyeux' => 0,
            '😢 Triste' => 0,
            '😨 Effrayé' => 0,
            '😐 Neutre' => 0,
        ];

        foreach ($allReves as $reve) {
            $humeur = trim((string)($reve->getHumeur() ?? ''));
            if (array_key_exists($humeur, $dreamMoodCounts)) {
                $dreamMoodCounts[$humeur]++;
            }
        }

        $stats['dream_mood_labels'] = array_keys($dreamMoodCounts);
        $stats['dream_mood_data'] = array_values($dreamMoodCounts);

        return $this->render('sleep/reve/index.html.twig', [
            'reves' => $reves,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    #[Route('/generate-description', name: 'app_reve_generate_description', methods: ['POST'])]
    public function generateDescription(
        Request         $request,
        LmStudioService $lmStudioService
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $title = trim((string)($data['title'] ?? ''));

        if ($title === '') {
            return $this->json([
                'success' => false,
                'message' => 'Le titre est vide.',
            ], 400);
        }

        try {
            $description = $lmStudioService->generateDreamDescription($title);

            return $this->json([
                'success' => true,
                'description' => $description,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de générer la description pour le moment.',
            ], 500);
        }
    }

    #[Route('/new', name: 'app_reve_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $reve = new Reves();
        $form = $this->createForm(ReveType::class, $reve);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em->persist($reve);
                $em->flush();

                $this->addFlash('success', 'Rêve ajouté avec succès !');

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                    ]);
                }

                return $this->redirectToRoute('app_reve_index');
            }

            foreach ($this->getFormErrors($form) as $error) {
                $this->addFlash('error', $error);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/reve/components/_form_modal.html.twig', [
                'form' => $form->createView(),
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/reve/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/show/{id<\d+>}', name: 'app_reve_show', methods: ['GET'])]
    public function show(Request $request, Reves $reve): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/reve/components/_show_modal.html.twig', [
                'reve' => $reve,
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/reve/show.html.twig', [
            'reve' => $reve,
        ]);
    }

    #[Route('/edit/{id<\d+>}', name: 'app_reve_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reves $reve, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReveType::class, $reve);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $em->flush();

                $this->addFlash('success', 'Rêve modifié avec succès !');

                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => true,
                    ]);
                }

                return $this->redirectToRoute('app_reve_index');
            }

            foreach ($this->getFormErrors($form) as $error) {
                $this->addFlash('error', $error);
            }
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('sleep/reve/components/_form_modal.html.twig', [
                'reve' => $reve,
                'form' => $form->createView(),
                'modal_mode' => true,
            ]);
        }

        return $this->render('sleep/reve/edit.html.twig', [
            'reve' => $reve,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id<\d+>}', name: 'app_reve_delete', methods: ['POST'])]
    public function delete(Request $request, Reves $reve, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reve->getId(), (string)$request->request->get('_token'))) {
            $em->remove($reve);
            $em->flush();

            $this->addFlash('success', 'Rêve supprimé.');
        }

        return $this->redirectToRoute('app_reve_index');
    }

    #[Route('/export/csv', name: 'app_reve_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request, RevesRepository $revesRepository): Response
    {
        /** @var array<string, mixed> $filters */

        $filters = [
            'q' => $request->query->get('q'),
            'type' => $request->query->get('type'),
            'recurrent' => $request->query->get('recurrent', ''),
            'couleur' => $request->query->get('couleur'),
            'cauchemars' => $request->query->get('cauchemars'),
            'sort' => $request->query->get('sort', 's.dateNuit'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];
        /** @var Reves[] $rows */
        $rows = $revesRepository->findFrontFiltered($filters);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="reves.csv"');

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Impossible d’ouvrir le flux CSV');
        }
        fputcsv($handle, ['Date', 'Titre', 'Type', 'Intensité', 'Récurrent', 'Couleur']);

        foreach ($rows as $r) {
            fputcsv($handle, [
                $r->getCreatedAt()?->format('Y-m-d') ?? '',
                $r->getTitre(),
                $r->getTypeReve(),
                $r->getIntensite(),
                $r->isRecurrent() ? 'Oui' : 'Non',
                $r->isCouleur() ? 'Oui' : 'Non',
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response->setContent($content);

        return $response;
    }

    #[Route('/export/pdf', name: 'app_reve_export_pdf', methods: ['GET'])]
    public function exportPdf(
        Request               $request,
        RevesRepository       $revesRepository,
        GotenbergPdfInterface $gotenberg
    ): Response
    {
        /** @var array<string, mixed> $filters */
        $filters = [
            'q' => $request->query->get('q'),
            'type' => $request->query->get('type'),
            'recurrent' => $request->query->get('recurrent', ''),
            'couleur' => $request->query->get('couleur'),
            'cauchemars' => $request->query->get('cauchemars'),
            'sort' => $request->query->get('sort', 's.dateNuit'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];

        $reves = $revesRepository->findFrontFiltered($filters);

        return $gotenberg->html()
            ->content('sleep/reve/export_pdf.html.twig', [
                'reves' => $reves,
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