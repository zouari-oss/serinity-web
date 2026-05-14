<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MedicalAiConsultation extends AbstractController
{
    #[Route('/user/medical-ai/analyse', name: 'app_medical_ai_analyse', methods: ['POST'])]
    public function analyse(
        Request $request,
        HttpClientInterface $client
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $message = trim($data['message'] ?? '');

        if ($message === '') {
            return $this->json([
                'success' => false,
                'message' => 'Empty message.'
            ], 400);
        }

        $prompt = "
You are a senior emergency doctor AI assistant.

Patient message:
{$message}

Return ONLY valid JSON with this exact structure:

{
  \"success\": true,
  \"level\": \"low|medium|high|emergency\",
  \"title\": \"short title\",
  \"analysis\": \"short medical explanation\",
  \"actions\": [
    \"action 1\",
    \"action 2\",
    \"action 3\"
  ],
  \"needDoctor\": true,
  \"needEmergency\": false
}

Rules:
-ignore all other discussion non medical , just say sorry i can only help for medical issues
- If chest pain, stroke signs, severe breathing issues, suicide risk, heavy bleeding => emergency
- If moderate symptoms => high or medium
- No markdown
- No extra text
";

        try {
            $response = $client->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    //////api iciiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii
                 /////   'Authorization' => 'Bearer ' . 'iiiiiiiicccccccciiiiiiiiiiiiii',
        'Authorization' => 'Bearer ' . $_ENV['OPENROUTER_API_KEY'],
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => 'http://localhost',
                    'X-Title'       => 'Serinity Medical AI'
                ],
                'json' => [
                    'model' => 'openai/gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.2
                ]
            ]);

            $result = $response->toArray(false);

            $content = $result['choices'][0]['message']['content'] ?? '{}';

            $json = json_decode($content, true);

            if (!$json) {
                return $this->json([
                    'success' => false,
                    'message' => 'AI invalid response'
                ], 500);
            }

            return $this->json($json);

        } catch (\Throwable $e) {

            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
