<?php

namespace App\Controller\Sleep\Api;

use App\Entity\Sleep\Reves;
use App\Service\Sleep\DayAdviceService;
use App\Service\Sleep\HuggingFaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/sleep/api/reve')]
class ReveAnalysisController extends AbstractController
{
    #[Route('/{id}/analysis', name: 'sleep_reve_analysis', methods: ['GET'])]
    public function analyze(
        Reves $reve,
        HuggingFaceService $hf,
        DayAdviceService $advice
    ): JsonResponse {
        $humeur = $reve->getHumeur() ?? '';

        $hfResult = $hf->analyzeHumeur($humeur);

        $advices = $advice->getAdvice($hfResult, [
            'humeur'    => $humeur,
            'type_reve' => $reve->getTypeReve(),
        ]);

        return $this->json([
            'analysis' => $hfResult,
            'advice'   => $advices,
            'humeur'   => $humeur,
        ]);
    }
}