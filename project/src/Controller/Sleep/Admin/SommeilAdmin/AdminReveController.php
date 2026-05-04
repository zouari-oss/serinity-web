<?php

namespace App\Controller\Sleep\Admin\SommeilAdmin;

use App\Entity\Sleep\Reves;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/reve', name: 'app_admin_reve_')]
final class AdminReveController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
    ): Response {
        $reveEntities = $entityManager->getRepository(Reves::class)->findBy([], ['id' => 'DESC']);

        $revesRaw = [];

        foreach ($reveEntities as $reve) {
            $sommeil  = $reve->getSommeil();
            $userId   = $sommeil?->getUserId();
            $dateNuit = $sommeil?->getDateNuit();
            $humeur   = $reve->getHumeur() ?? 'Neutre';

            $revesRaw[] = [
                'id'                => $reve->getId(),
                'user_id'           => $userId,
                'user_label'        => $userId ? 'Utilisateur #' . $userId : 'Utilisateur inconnu',
                'user_avatar'       => 'U',
                'date_nuit'         => $dateNuit,
                'titre'             => $reve->getTitre() ?? 'Sans titre',
                'description'       => $reve->getDescription() ?? '',
                'description_courte'=> mb_strimwidth($reve->getDescription() ?? '', 0, 60, '…'),
                'type_reve'         => $reve->getTypeReve() ?? 'Inconnu',
                'humeur'            => $humeur,
                'humeur_class'      => $this->mapEmotionClass($humeur),
            ];
        }

        $pagination = $paginator->paginate(
            $revesRaw,
            $request->query->getInt('page', 1),
            10 // items par page
        );

        return $this->render('sleep/admin/sommeil.html.twig', [
            'reves'      => $pagination,
            'pagination' => $pagination,
        ]);
    }

    private function mapEmotionClass(string $humeur): string
    {
        $value = mb_strtolower(trim($humeur));

        return match (true) {
            str_contains($value, 'joy')     ||
            str_contains($value, 'heureux') ||
            str_contains($value, 'heureuse')  => 'emotion-joyeux',

            str_contains($value, 'peur')    ||
            str_contains($value, 'effray')  ||
            str_contains($value, 'angoisse')||
            str_contains($value, 'anx')       => 'emotion-peur',

            str_contains($value, 'serein')  ||
            str_contains($value, 'calme')     => 'emotion-calme',

            str_contains($value, 'triste')    => 'emotion-triste',

            default                           => 'emotion-neutre',
        };
    }
}