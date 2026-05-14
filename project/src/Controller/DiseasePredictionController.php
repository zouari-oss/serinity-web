<?php

namespace App\Controller;

use App\Controller\User\AbstractUserUiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DiseasePredictionController extends AbstractUserUiController
{
    #[Route('/user/rdv/disease-ai', name: 'app_rdv_disease_ai', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();
        if ($user->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        return $this->render('rdv/rdv_disease.html.twig', [
            'nav' => $this->buildNav('app_rdv_disease_ai'),
            'userName' => $user->getEmail(),
        ]);
    }

    #[Route('/user/rdv/disease-ai/predict', name: 'app_rdv_disease_ai_predict', methods: ['POST'])]
    public function predict(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $user = $this->currentUser();
        if ($user->getRole() === 'THERAPIST') {
            return $this->json([
                'success' => false,
                'message' => 'Therapists cannot use the patient psychological prediction page.',
            ], 403);
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json([
                'success' => false,
                'message' => 'Malformed JSON payload.',
            ], 400);
        }

        $prompt = trim((string) ($payload['prompt'] ?? ''));
        if ($prompt === '') {
            return $this->json([
                'success' => false,
                'message' => 'Please describe psychological symptoms first.',
            ], 400);
        }

        $mentionedSymptoms = $this->extractMentionedSymptoms($prompt);
        $fallbackDisease = $this->resolveFallbackDisease($prompt, $mentionedSymptoms);
        $apiData = null;

        try {
            $apiResponse = $httpClient->request('POST', 'http://127.0.0.1:5001/predict-from-prompt', [
                'json' => ['prompt' => $prompt],
                'timeout' => 10,
            ]);

            $apiData = $apiResponse->toArray(false);
        } catch (\Throwable) {
            $apiData = null;
        }

        $predictedDisease = is_array($apiData) ? (string) ($apiData['probableDisease'] ?? '') : '';
        $predictionSource = is_array($apiData) ? (string) ($apiData['predictionSource'] ?? '') : '';
        $confidence = is_array($apiData) && is_numeric($apiData['confidence'] ?? null)
            ? (float) $apiData['confidence']
            : null;
        $topPredictions = is_array($apiData) && is_array($apiData['topPredictions'] ?? null)
            ? $apiData['topPredictions']
            : [];

        if ($mentionedSymptoms === []
            && is_array($apiData)
            && (($apiData['medicalScope'] ?? '') === 'non_psychological')
        ) {
            return $this->json([
                'success' => false,
                'message' => (string) ($apiData['message'] ?? 'This assistant only answers psychological symptom prompts.'),
                'mentionedSymptoms' => [],
            ], 200);
        }

        $probableDisease = $this->normalizeProbableDisease(
            $prompt,
            $mentionedSymptoms,
            $predictedDisease,
            $fallbackDisease,
        );

        if ($probableDisease === '') {
            return $this->json([
                'success' => false,
                'message' => 'No psychological prediction could be produced.',
                'mentionedSymptoms' => $mentionedSymptoms,
            ], 200);
        }

        return $this->json([
            'success' => true,
            'mentionedText' => $prompt,
            'mentionedSymptoms' => $mentionedSymptoms,
            'probableDisease' => $probableDisease,
            'medicalScope' => 'psychological_only',
            'confidence' => $confidence,
            'predictionSource' => $predictionSource !== '' ? $predictionSource : 'symfony_fallback',
            'topPredictions' => $topPredictions,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractMentionedSymptoms(string $prompt): array
    {
        $normalizedPrompt = $this->normalizePrompt($prompt);
        $catalog = [
            'hopelessness' => ['hopeless', 'hopless', 'despair'],
            'sadness' => ['sad', 'depressed', 'down', 'empty'],
            'loss of interest' => ['lost interest', 'nothing makes me happy', 'no pleasure', "don't enjoy", 'dont enjoy'],
            'fatigue' => ['tired', 'exhausted', 'fatigue', 'no energy'],
            'anxiety' => ['anxious', 'anxiety', 'worried', 'nervous', 'overthinking'],
            'panic' => ['panic attack', 'panic attacks', 'intense panic'],
            'stress' => ['stress', 'stressed', 'overwhelmed', 'burnout'],
            'insomnia' => ["can't sleep", 'cant sleep', 'cannot sleep', 'insomnia', 'waking up at night'],
            'hallucinations' => ['hear voices', 'hearing voices', 'see things', 'hallucination', 'hallucinations'],
            'paranoia' => ['paranoid', 'people are watching me'],
        ];

        $mentionedSymptoms = [];

        foreach ($catalog as $label => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($normalizedPrompt, $pattern)) {
                    $mentionedSymptoms[] = $label;
                    break;
                }
            }
        }

        return array_values(array_unique($mentionedSymptoms));
    }

    /**
     * @param list<string> $mentionedSymptoms
     */
    private function resolveFallbackDisease(string $prompt, array $mentionedSymptoms): string
    {
        $normalizedPrompt = $this->normalizePrompt($prompt);

        if ($this->containsAny($normalizedPrompt, ['hear voices', 'hearing voices', 'hallucination', 'hallucinations', 'paranoid'])) {
            return 'psychotic disorder';
        }

        if ($this->containsAny($normalizedPrompt, ['panic attack', 'panic attacks', 'intense panic'])) {
            return 'panic disorder';
        }

        if ($this->containsAny($normalizedPrompt, ['flashbacks', 'nightmares', 'trauma', 'traumatic event'])) {
            return 'post-traumatic stress disorder (ptsd)';
        }

        if ($this->containsAny($normalizedPrompt, ['intrusive thoughts', 'obsessive thoughts', 'compulsions', 'repeated checking'])) {
            return 'obsessive compulsive disorder (ocd)';
        }

        if (in_array('hopelessness', $mentionedSymptoms, true)
            || in_array('sadness', $mentionedSymptoms, true)
            || in_array('loss of interest', $mentionedSymptoms, true)
        ) {
            return 'depression';
        }

        if (in_array('panic', $mentionedSymptoms, true)) {
            return 'panic disorder';
        }

        if (in_array('anxiety', $mentionedSymptoms, true)) {
            return 'anxiety';
        }

        if (in_array('insomnia', $mentionedSymptoms, true)) {
            return 'primary insomnia';
        }

        return 'psychological distress';
    }

    /**
     * @param list<string> $mentionedSymptoms
     */
    private function normalizeProbableDisease(
        string $prompt,
        array $mentionedSymptoms,
        string $predictedDisease,
        string $fallbackDisease,
    ): string {
        $normalizedPrediction = $this->normalizePrompt($predictedDisease);
        if ($normalizedPrediction === '') {
            return $fallbackDisease;
        }

        $depressionLikePrompt = in_array('hopelessness', $mentionedSymptoms, true)
            || in_array('sadness', $mentionedSymptoms, true)
            || in_array('loss of interest', $mentionedSymptoms, true);

        if ($this->isAddictionPrediction($normalizedPrediction) && !$this->hasAddictionKeywords($prompt) && $depressionLikePrompt) {
            return 'depression';
        }

        if ($normalizedPrediction === 'psychological distress' && $fallbackDisease !== '') {
            return $fallbackDisease;
        }

        if ($depressionLikePrompt && !$this->isDepressionPrediction($normalizedPrediction) && $this->isLowSignalPrediction($normalizedPrediction)) {
            return 'depression';
        }

        return $predictedDisease;
    }

    private function normalizePrompt(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return is_string($value) ? $value : '';
    }

    /**
     * @param list<string> $patterns
     */
    private function containsAny(string $haystack, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function hasAddictionKeywords(string $prompt): bool
    {
        return $this->containsAny($this->normalizePrompt($prompt), [
            'smoke',
            'smoking',
            'tobacco',
            'nicotine',
            'cigarette',
            'alcohol',
            'drinking',
            'drug',
            'drugs',
            'substance',
            'weed',
            'cannabis',
        ]);
    }

    private function isAddictionPrediction(string $prediction): bool
    {
        return $this->containsAny($prediction, [
            'smoking',
            'tobacco',
            'nicotine',
            'alcohol abuse',
            'drug abuse',
            'substance',
            'addiction',
        ]);
    }

    private function isDepressionPrediction(string $prediction): bool
    {
        return $this->containsAny($prediction, [
            'depression',
            'depressive',
        ]);
    }

    private function isLowSignalPrediction(string $prediction): bool
    {
        return !$this->containsAny($prediction, [
            'depression',
            'anxiety',
            'panic',
            'insomnia',
            'psychotic',
            'ptsd',
            'ocd',
            'bipolar',
        ]);
    }
}
