<?php

namespace App\Controller\Sleep\Api;

use App\Entity\Sleep\Reves;
use App\Repository\Sleep\RevesRepository;
use App\Service\Sleep\LmStudioService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sleep/api/ai')]
class ReveAiController extends AbstractController
{
    #[Route('/reve/{id}', name: 'sleep_ai_reve_single', methods: ['GET'])]
    public function analyzeSingle(Reves $reve, LmStudioService $lmStudio): JsonResponse
    {
        try {
            $result = $lmStudio->analyzeReve([
                'titre' => $reve->getTitre(),
                'description' => $reve->getDescription(),
                'emotions' => $reve->getEmotions(),
                'typeReve' => $reve->getTypeReve(),
                'intensite' => $reve->getIntensite(),
                'couleur' => $reve->isCouleur(),
                'recurrent' => $reve->isRecurrent(),
            ]);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/reves/global', name: 'sleep_ai_reve_global', methods: ['GET'])]
    public function analyzeGlobal(RevesRepository $repository, LmStudioService $lmStudio): JsonResponse
    {
        try {
            $reves = $repository->findAll();

            $payload = array_map(function (Reves $reve) {
                return [
                    'titre' => $reve->getTitre(),
                    'description' => $reve->getDescription(),
                    'emotions' => $reve->getEmotions(),
                    'typeReve' => $reve->getTypeReve(),
                    'intensite' => $reve->getIntensite(),
                    'couleur' => $reve->isCouleur(),
                    'recurrent' => $reve->isRecurrent(),
                ];
            }, $reves);

            $result = $lmStudio->analyzeGlobal($payload);

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}