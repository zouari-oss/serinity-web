<?php

namespace App\Controller\Sleep\Api;

use App\Service\Sleep\SleepMLService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/sleep/ml', name: 'sleep_ml_')]
class SleepMLController extends AbstractController
{
    public function __construct(
        private readonly SleepMLService $mlService,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('/widget', name: 'widget', methods: ['GET'])]
    public function widget(): Response
    {
        return $this->render('sleep/sommeil/ml_widget.html.twig', [
            'ml_available' => $this->mlService->isAvailable(),
        ]);
    }

    #[Route('/predict', name: 'predict', methods: ['POST'])]
    public function predict(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !is_array($data)) {
            return $this->json(['error' => 'Corps JSON invalide'], 400);
        }

        $violations = $this->validator->validate($data, new Assert\Collection(
            fields: [
                'bedtime_hour' => [
                    new Assert\Range(min: 0, max: 23),
                ],
                'stress_level' => [
                    new Assert\Range(min: 1, max: 10),
                ],
                'physical_activity_minutes' => [
                    new Assert\Range(min: 0, max: 180),
                ],
                'temperature' => [
                    new Assert\Range(min: 10.0, max: 35.0),
                ],
                'noise_level' => [
                    new Assert\Choice(choices: ['quiet', 'moderate', 'noisy']),
                ],
                'mood_bedtime' => [
                    new Assert\Choice(choices: ['calm', 'stressed', 'neutral', 'tired']),
                ],
                'age' => [
                    new Assert\Range(min: 1, max: 120),
                ],
                'bmi_category' => [
                    new Assert\Choice(choices: ['Normal', 'Overweight', 'Obese', 'Underweight']),
                ],
                'sleep_duration' => [
                    new Assert\Range(min: 1.0, max: 12.0),
                ],
            ],
            allowMissingFields: false,
            allowExtraFields: false
        ));

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }

            return $this->json(['error' => implode(', ', $errors)], 422);
        }

        $result = $this->mlService->predict([
            'bedtime_hour' => (int) $data['bedtime_hour'],
            'stress_level' => (int) $data['stress_level'],
            'physical_activity_minutes' => (int) $data['physical_activity_minutes'],
            'temperature' => (float) $data['temperature'],
            'noise_level' => $data['noise_level'],
            'mood_bedtime' => $data['mood_bedtime'],
            'age' => (int) $data['age'],
            'bmi_category' => $data['bmi_category'],
            'sleep_duration' => (float) $data['sleep_duration'],
        ]);

        return isset($result['error'])
            ? $this->json($result, 503)
            : $this->json($result);
    }
}