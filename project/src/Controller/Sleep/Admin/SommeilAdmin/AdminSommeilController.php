<?php

namespace App\Controller\Sleep\Admin\SommeilAdmin;

use App\Entity\Sleep\Reves;
use App\Entity\Sleep\Sommeil;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/sommeil', name: 'app_admin_sommeil_')]
final class AdminSommeilController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $sommeilEntities = $entityManager->getRepository(Sommeil::class)->findBy([], ['id' => 'DESC']);
        $reveEntities = $entityManager->getRepository(Reves::class)->findBy([], ['id' => 'DESC']);

        $qualityMap = [
            'Excellente' => 100,
            'Bonne' => 75,
            'Moyenne' => 50,
            'Mauvaise' => 25,
        ];

        $sommeils = [];
        $reves = [];

        $totalDuree = 0.0;
        $totalQualite = 0;
        $countSommeilsAvecQualite = 0;

        $qualiteExcellente = 0;
        $qualiteBonne = 0;
        $qualiteMoyenne = 0;
        $qualiteMauvaise = 0;

        $sleepDurationLabels = [];
        $sleepDurationData = [];

        $wakeMoodCounts = [
            'Reposé' => 0,
            'Joyeux' => 0,
            'Neutre' => 0,
            'Fatigué' => 0,
            'Énergisé' => 0,
        ];

        foreach ($sommeilEntities as $sommeil) {
            $qualiteLabel = $sommeil->getQualite() ?? 'Mauvaise';
            $qualiteScore = $qualityMap[$qualiteLabel] ?? 0;

            $statutClass = 'status-bad';
            $statutLabel = 'À améliorer';

            if ($qualiteScore >= 70) {
                $statutClass = 'status-good';
                $statutLabel = 'Bon';
            } elseif ($qualiteScore >= 40) {
                $statutClass = 'status-warn';
                $statutLabel = 'Moyen';
            }

            $duree = (float) ($sommeil->getDureeSommeil() ?? 0);
            $totalDuree += $duree;

            if ($qualiteScore > 0) {
                $totalQualite += $qualiteScore;
                $countSommeilsAvecQualite++;
            }

            switch ($qualiteLabel) {
                case 'Excellente':
                    $qualiteExcellente++;
                    break;
                case 'Bonne':
                    $qualiteBonne++;
                    break;
                case 'Moyenne':
                    $qualiteMoyenne++;
                    break;
                default:
                    $qualiteMauvaise++;
                    break;
            }

            $dateLabel = $sommeil->getDateNuit()?->format('d/m') ?? ('#' . $sommeil->getId());
            $sleepDurationLabels[] = $dateLabel;
            $sleepDurationData[] = $duree;

            $humeurReveil = trim((string) ($sommeil->getHumeurReveil() ?? ''));
            $humeurReveilClean = str_replace(['😌 ', '😄 ', '😐 ', '😴 ', '⚡ '], '', $humeurReveil);

            if (array_key_exists($humeurReveilClean, $wakeMoodCounts)) {
                $wakeMoodCounts[$humeurReveilClean]++;
            }

            $userId = $sommeil->getUserId();

            $sommeils[] = [
                'id' => $sommeil->getId(),
                'user_id' => $userId,
                'user_label' => $userId ? 'Utilisateur #' . $userId : 'Utilisateur inconnu',
                'user_avatar' => 'U',
                'date_nuit' => $sommeil->getDateNuit(),
                'duree' => $duree,
                'qualite_label' => $qualiteLabel,
                'qualite_score' => $qualiteScore,
                'heure_coucher' => $sommeil->getHeureCoucher() ?? '-',
                'heure_reveil' => $sommeil->getHeureReveil() ?? '-',
                'humeur_reveil' => $sommeil->getHumeurReveil() ?? '-',
                'statut_label' => $statutLabel,
                'statut_class' => $statutClass,
            ];
        }

        $revesNormaux = 0;
        $revesLucides = 0;
        $revesCauchemars = 0;
        $revesPremonitoires = 0;

        $dreamMoodCounts = [
            'Joyeux' => 0,
            'Triste' => 0,
            'Effrayé' => 0,
            'Serein' => 0,
            'Neutre' => 0,
        ];

        foreach ($reveEntities as $reve) {
            $sommeil = $reve->getSommeil();
            $userId = $sommeil?->getUserId();
            $dateNuit = $sommeil?->getDateNuit();
            $humeur = $reve->getHumeur() ?? 'Neutre';
            $typeReve = $reve->getTypeReve() ?? 'Inconnu';

            $typeNormalize = mb_strtolower(trim($typeReve));

            if ($typeNormalize === 'normal') {
                $revesNormaux++;
            } elseif ($typeNormalize === 'lucide') {
                $revesLucides++;
            } elseif ($typeNormalize === 'cauchemar') {
                $revesCauchemars++;
            } elseif (in_array($typeNormalize, ['prémonitoire', 'premonitoire'], true)) {
                $revesPremonitoires++;
            }

            $humeurClean = str_replace(['😄 ', '😢 ', '😨 ', '😌 ', '😐 '], '', $humeur);

            if (array_key_exists($humeurClean, $dreamMoodCounts)) {
                $dreamMoodCounts[$humeurClean]++;
            }

            $reves[] = [
                'id' => $reve->getId(),
                'user_id' => $userId,
                'user_label' => $userId ? 'Utilisateur #' . $userId : 'Utilisateur inconnu',
                'user_avatar' => 'U',
                'date_nuit' => $dateNuit,
                'titre' => $reve->getTitre() ?? 'Sans titre',
                'description' => $reve->getDescription() ?? '',
                'description_courte' => mb_strimwidth($reve->getDescription() ?? '', 0, 60, '…'),
                'type_reve' => $typeReve,
                'humeur' => $humeur,
                'humeur_class' => $this->mapEmotionClass($humeur),
            ];
        }

        $paginationSommeils = $paginator->paginate(
            $sommeils,
            $request->query->getInt('sleep_page', 1),
            5,
            ['pageParameterName' => 'sleep_page']
        );

        $paginationReves = $paginator->paginate(
            $reves,
            $request->query->getInt('dream_page', 1),
            5,
            ['pageParameterName' => 'dream_page']
        );

        $kpis = [
            'total_sommeils' => count($sommeils),
            'total_reves' => count($reves),
            'moyenne_duree' => count($sommeils) > 0 ? round($totalDuree / count($sommeils), 1) : 0,
            'moyenne_qualite' => $countSommeilsAvecQualite > 0 ? round($totalQualite / $countSommeilsAvecQualite) : 0,
            'qualite_excellente' => $qualiteExcellente,
            'qualite_bonne' => $qualiteBonne,
            'qualite_moyenne' => $qualiteMoyenne,
            'qualite_mauvaise' => $qualiteMauvaise,
            'reves_normaux' => $revesNormaux,
            'reves_lucides' => $revesLucides,
            'reves_cauchemars' => $revesCauchemars,
            'reves_premonitoires' => $revesPremonitoires,
            'sleep_duration_labels' => $sleepDurationLabels,
            'sleep_duration_data' => $sleepDurationData,
            'wake_mood_labels' => array_keys($wakeMoodCounts),
            'wake_mood_data' => array_values($wakeMoodCounts),
            'dream_mood_labels' => array_keys($dreamMoodCounts),
            'dream_mood_data' => array_values($dreamMoodCounts),
        ];

        return $this->render('sleep/admin/sommeil.html.twig', [
            'nav' => $this->buildNav('app_admin_sommeil_index'),
            'userName' => $this->getUser()?->getUserIdentifier() ?? 'Admin',
            'sommeils' => $paginationSommeils,
            'reves' => $paginationReves,
            'pagination_sommeils' => $paginationSommeils,
            'pagination_reves' => $paginationReves,
            'kpis' => $kpis,
        ]);
    }

    #[Route('/modal/sommeil/{id}', name: 'modal_sommeil_show', methods: ['GET'])]
    public function modalSommeil(Sommeil $sommeil): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('sleep/admin/_modal_sommeil_show.html.twig', [
            'sommeil' => $sommeil,
        ]);
    }

    #[Route('/modal/reve/{id}', name: 'modal_reve_show', methods: ['GET'])]
    public function modalReve(Reves $reve): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('sleep/admin/_modal_reve_show.html.twig', [
            'reve' => $reve,
            'sommeil' => $reve->getSommeil(),
        ]);
    }

    #[Route('/delete/sommeil/{id}', name: 'delete_sommeil', methods: ['POST', 'DELETE'])]
    public function deleteSommeil(
        Request $request,
        Sommeil $sommeil,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete_sommeil_' . $sommeil->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($sommeil);
            $entityManager->flush();
            $this->addFlash('success', 'La nuit a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_sommeil_index');
    }

    #[Route('/delete/reve/{id}', name: 'delete_reve', methods: ['POST', 'DELETE'])]
    public function deleteReve(
        Request $request,
        Reves $reve,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete_reve_' . $reve->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($reve);
            $entityManager->flush();
            $this->addFlash('success', 'Le rêve a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_sommeil_index');
    }

    private function mapEmotionClass(string $humeur): string
    {
        $value = mb_strtolower(trim($humeur));

        return match (true) {
            str_contains($value, 'joy') ||
            str_contains($value, 'heureux') ||
            str_contains($value, 'heureuse') => 'emotion-joyeux',

            str_contains($value, 'peur') ||
            str_contains($value, 'effray') ||
            str_contains($value, 'angoisse') ||
            str_contains($value, 'anx') => 'emotion-peur',

            str_contains($value, 'serein') ||
            str_contains($value, 'calme') => 'emotion-calme',

            str_contains($value, 'triste') => 'emotion-triste',

            default => 'emotion-neutre',
        };
    }

    private function buildNav(string $activeRoute): array
    {
        $items = [
            ['section' => 'Admin self-management', 'label' => 'Dashboard', 'route' => 'ac_ui_dashboard', 'icon' => 'dashboard'],
            ['section' => 'Admin self-management', 'label' => 'Profile', 'route' => 'ac_ui_profile', 'icon' => 'person'],
            ['section' => 'Admin self-management', 'label' => 'Settings', 'route' => 'ac_ui_settings', 'icon' => 'settings'],
            ['section' => 'Admin self-management', 'label' => 'Sessions', 'route' => 'ac_ui_sessions', 'icon' => 'devices'],
            ['section' => 'Admin self-management', 'label' => 'Audit logs', 'route' => 'ac_ui_audit_logs', 'icon' => 'history'],

            ['section' => 'Users management', 'label' => 'Users', 'route' => 'ac_ui_users', 'icon' => 'group'],
            ['section' => 'Users management', 'label' => 'Consultations', 'route' => 'ac_ui_consultations', 'icon' => 'medical_services'],
            ['section' => 'Users management', 'label' => 'Exercises', 'route' => 'ac_ui_exercises', 'icon' => 'self_improvement'],
            ['section' => 'Users management', 'label' => 'Forum', 'route' => 'ac_ui_forum', 'icon' => 'forum'],
            ['section' => 'Users management', 'label' => 'Sleep', 'route' => 'app_admin_sommeil_index', 'icon' => 'hotel'],
            [
                'section' => 'Users management',
                'label' => 'Mood',
                'route' => 'ac_ui_mood',
                'icon' => 'mood',
                'children' => [
                    ['label' => 'Mood analytics', 'route' => 'ac_ui_mood', 'icon' => 'analytics'],
                    ['label' => 'Emotion management', 'route' => 'ac_ui_emotion', 'icon' => 'sentiment_satisfied'],
                    ['label' => 'Influence management', 'route' => 'ac_ui_influence', 'icon' => 'tune'],
                ],
            ],
        ];

        return array_map(
            static function (array $item) use ($activeRoute): array {
                $children = $item['children'] ?? [];

                $mappedChildren = array_map(
                    static fn(array $child): array => [
                        ...$child,
                        'active' => $child['route'] === $activeRoute,
                    ],
                    $children
                );

                $hasActiveChild = false;
                foreach ($mappedChildren as $child) {
                    if ($child['active']) {
                        $hasActiveChild = true;
                        break;
                    }
                }

                $result = [
                    'section' => $item['section'],
                    'label' => $item['label'],
                    'route' => $item['route'],
                    'icon' => $item['icon'],
                    'active' => $item['route'] === $activeRoute || $hasActiveChild,
                ];

                if (!empty($mappedChildren)) {
                    $result['children'] = $mappedChildren;
                }

                return $result;
            },
            $items
        );
    }
}