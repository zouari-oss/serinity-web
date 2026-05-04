<?php

namespace App\Service\Sleep;

class DayAdviceService
{
    private const HUMEUR_CATEGORIE = [
        '😄 Joyeux' => 'joyeux',
        '😌 Serein' => 'paisible',
        '😢 Triste' => 'triste',
        '😨 Effrayé' => 'effrayé',
        '😐 Neutre' => 'neutre',
    ];

    public function getAdvice(array $hfResult, array $reve): array
    {
        $humeur = trim((string) ($reve['humeur'] ?? ''));
        $typeReve = trim((string) ($reve['type_reve'] ?? ''));

        $categorie = self::HUMEUR_CATEGORIE[$humeur] ?? 'neutre';
        $sentiment = trim((string) ($hfResult['sentiment'] ?? 'neutre'));
        $confiance = (int) ($hfResult['confiance'] ?? 0);

        [$titre, $classe, $emoji] = $this->generateHeader($categorie, $sentiment, $confiance);

        $conseils = $this->generateAdviceWithAI($categorie, $sentiment, $typeReve);

        if (empty($conseils)) {
            $conseils = $this->fallbackAdvice($categorie);
        }

        // 🎭 4. Ajouts selon type de rêve
        if ($typeReve === 'Cauchemar') {
            $conseils[] = '🌙 Couchez-vous 30 min plus tôt ce soir.';
        }

        if ($typeReve === 'Lucide') {
            $conseils[] = '🧠 Votre esprit est actif — profitez pour créer.';
        }

        $confidenceNote = $confiance > 0
            ? "Analyse IA : {$sentiment} ({$confiance}%)"
            : null;

        return [
            'titre' => $titre,
            'classe' => $classe,
            'emoji' => $emoji,
            'conseils' => array_values(array_unique($conseils)),
            'confidenceNote' => $confidenceNote,
        ];
    }

    private function generateAdviceWithAI(string $categorie, string $sentiment, string $typeReve): array
    {
        $prompt = "
Tu es un coach bien-être.

Utilisateur :
- humeur: $categorie
- sentiment: $sentiment
- type de rêve: $typeReve

Donne exactement 5 conseils courts (max 12 mots chacun).
Format: liste simple (une ligne par conseil, sans numérotation).
";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => "http://localhost:1234/v1/chat/completions",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS => json_encode([
                "model" => "mistral",
                "messages" => [
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.7,
                "max_tokens" => 200
            ])
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return [];
        }

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';

        return $this->cleanAIResponse($text);
    }


    private function cleanAIResponse(string $text): array
    {
        $lines = explode("\n", $text);
        $conseils = [];

        foreach ($lines as $line) {
            $line = trim($line);

            $line = preg_replace('/^[0-9\-\*\.\)\s]+/', '', $line);

            if (!empty($line) && strlen($line) > 5) {
                $conseils[] = $line;
            }

            if (count($conseils) >= 5) break;
        }

        return $conseils;
    }


    private function fallbackAdvice(string $categorie): array
    {
        return match ($categorie) {
            'joyeux' => [
                '☀️ Profitez de votre énergie positive.',
                '🏃 Faites une activité dynamique.',
                '🤝 Socialisez avec les autres.',
            ],
            'paisible' => [
                '🧘 Prenez un moment calme.',
                '📖 Écrivez vos pensées.',
            ],
            'triste' => [
                '🧘 Respirez profondément.',
                '🚶 Faites une marche courte.',
            ],
            'effrayé' => [
                '🛡️ Vous êtes en sécurité.',
                '💡 Restez dans un endroit lumineux.',
            ],
            default => [
                '🎯 Fixez 3 objectifs simples.',
                '💧 Hydratez-vous bien.',
            ],
        };
    }


    private function generateHeader(string $categorie, string $sentiment, int $confiance): array
    {
        $mixte = $categorie === 'joyeux'
            && $sentiment === 'négatif'
            && $confiance > 60;

        return match ($categorie) {
            'joyeux' => [
                $mixte ? 'Bonne humeur mais vigilance 🌤️' : 'Belle énergie ! 🌟',
                'success',
                '😊'
            ],
            'paisible' => ['Réveil serein 🌿', 'success', '😌'],
            'triste' => ['Douceur aujourd’hui 🌧️', 'warning', '😔'],
            'effrayé' => ['Rassurez-vous 🛡️', 'danger', '😨'],
            default => ['Journée équilibrée 🌤️', 'secondary', '😐'],
        };
    }
}