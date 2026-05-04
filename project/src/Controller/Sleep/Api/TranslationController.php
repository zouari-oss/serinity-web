<?php

namespace App\Controller\Sleep\Api;

use App\Service\Sleep\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sleep/translate')]
class TranslationController extends AbstractController
{
    #[Route('', name: 'sleep_api_translate', methods: ['POST'])]
    public function translate(
        Request $request,
        TranslationService $translationService
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'JSON invalide.',
            ], 400);
        }

        $text       = trim((string) ($data['text'] ?? ''));
        $targetLang = (string) ($data['targetLang'] ?? 'en');
        $sourceLang = (string) ($data['sourceLang'] ?? 'fr');

        if ($text === '') {
            return $this->json([
                'success' => false,
                'error'   => 'Texte vide.',
            ], 400);
        }

        $translated = $translationService->translate($text, $targetLang, $sourceLang);

        return $this->json([
            'success'    => true,
            'original'   => $text,
            'translated' => $translated,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
        ]);
    }

    #[Route('/batch', name: 'sleep_api_translate_batch', methods: ['POST'])]
    public function translateBatch(
        Request $request,
        TranslationService $translationService
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error'   => 'JSON invalide.',
            ], 400);
        }

        $texts      = $data['texts'] ?? [];
        $targetLang = (string) ($data['targetLang'] ?? 'en');
        $sourceLang = (string) ($data['sourceLang'] ?? 'fr');

        if (!is_array($texts) || empty($texts)) {
            return $this->json([
                'success' => false,
                'error'   => 'Textes manquants.',
            ], 400);
        }

        $translations = $translationService->translateBatch($texts, $targetLang, $sourceLang);

        return $this->json([
            'success'      => true,
            'translations' => $translations,
            'sourceLang'   => $sourceLang,
            'targetLang'   => $targetLang,
            'count'        => count($translations),
        ]);
    }
}