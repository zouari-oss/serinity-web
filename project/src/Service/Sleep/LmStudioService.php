<?php

namespace App\Service\Sleep;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class LmStudioService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $model,
    ) {}

    /**
     * @return string
     */
    public function generateDreamDescription(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return '';
        }

        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => <<<TXT
Tu aides une application de journal de rêves appelée Serinity. Tu rédiges des descriptions de rêves brèves, naturelles, fluides, plausibles et modifiables par l’utilisateur.

À partir du titre d’un rêve, génère une description courte, naturelle et plausible en français.

Contraintes :
- 2 à 4 phrases
- ton immersif et fluide
- pas de liste
- pas de titre
- texte modifiable par l'utilisateur
- ne jamais répondre en JSON
- retourner uniquement le texte final

Titre du rêve : {$title}
TXT,
                ],
            ],
            'temperature' => 0.7,
            'max_tokens' => 180,
            'stream' => false,
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 120,
        ]);

        $status = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException('LM Studio HTTP ' . $status . ' : ' . $rawBody);
        }

        $raw = json_decode($rawBody, true);

        if (!is_array($raw)) {
            throw new \RuntimeException('Réponse LM Studio non JSON.');
        }

        $content = trim((string) ($raw['choices'][0]['message']['content'] ?? ''));

        if ($content === '') {
            throw new \RuntimeException('Réponse LM Studio invalide : content introuvable.');
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function analyzeReve(array $data): array
    {
        $messages = [
            [
                'role' => 'user',
                'content' => $this->buildSingleUserMessage($data),
            ],
        ];

        return $this->requestStructured(
            $messages,
            $this->singleSchema(),
            'dream_single_analysis'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $reves
     * @return array<string, mixed>
     */
    public function analyzeGlobal(array $reves): array
    {
        if (empty($reves)) {
            return [
                'scorePsychologique' => 50,
                'profilDominant' => 'ÉQUILIBRÉ',
                'symbolesDetectes' => [],
                'impactEmotionnel' => '🟢 — Aucun rêve enregistré pour le moment.',
                'conclusion' => 'Aucune analyse globale ne peut être produite car aucun rêve n’a été trouvé.',
                'recommandations' => [
                    'Commencez à noter vos rêves chaque matin.',
                    'Essayez de relever vos émotions dominantes.',
                    'Surveillez les récurrences sur plusieurs nuits.',
                    'Ajoutez davantage de détails descriptifs.',
                    'Revenez consulter l’analyse après plusieurs entrées.',
                ],
                'niveauAlerte' => 'AUCUN',
            ];
        }

        $messages = [
            [
                'role' => 'user',
                'content' => $this->buildGlobalUserMessage($reves),
            ],
        ];

        return $this->requestStructured(
            $messages,
            $this->globalSchema(),
            'dream_global_analysis'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function requestStructured(array $messages, array $schema, string $schemaName): array
    {
        $url = rtrim($this->baseUrl, '/') . '/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => 700,
            'stream' => false,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schemaName,
                    'strict' => 'true',
                    'schema' => $schema,
                ],
            ],
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 120,
        ]);

        $status = $response->getStatusCode();
        $rawBody = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException('LM Studio HTTP ' . $status . ' : ' . $rawBody);
        }

        /** @var mixed $raw */
        $raw = json_decode($rawBody, true);
        if (!is_array($raw)) {
            throw new \RuntimeException('Réponse LM Studio non JSON.');
        }

        $content = $raw['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new \RuntimeException('Réponse LM Studio invalide : content introuvable.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($content, true);


        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse JSON invalide retournée par LM Studio : ' . $content);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $d
     */
    private function buildSingleUserMessage(array $d): string
    {
        $titre = $d['titre'] ?? 'Non renseigné';
        $description = $d['description'] ?? 'Non renseignée';
        $emotions = $d['emotions'] ?? 'Non renseignées';
        $typeReve = $d['typeReve'] ?? 'Normal';
        $intensite = $d['intensite'] ?? 5;
        $couleur = !empty($d['couleur']) ? 'Oui' : 'Non';
        $recurrent = !empty($d['recurrent']) ? 'Oui' : 'Non';

        return <<<TXT
Tu es un assistant de bien-être spécialisé dans l’analyse symbolique des rêves.
Tu ne poses jamais de diagnostic médical.
Tu produis une lecture psychologique douce, prudente, structurée et utile.
Réponds uniquement avec un JSON conforme au schéma demandé.

Analyse le rêve suivant :

- Titre : {$titre}
- Description : {$description}
- Émotions : {$emotions}
- Type : {$typeReve}
- Intensité : {$intensite}/10
- Couleur : {$couleur}
- Récurrent : {$recurrent}

Consignes supplémentaires :
- Sois bref mais pertinent.
- Les recommandations doivent être concrètes et bienveillantes.
- Le niveau d’alerte reste prudent et non médical.
TXT;
    }

    /**
     * @param array<int, array<string, mixed>> $reves
     */
    private function buildGlobalUserMessage(array $reves): string

    {
        $resume = implode("\n", array_map(function (array $r) {
            $titre = $r['titre'] ?? 'Sans titre';
            $description = $r['description'] ?? 'Sans description';
            $emotions = $r['emotions'] ?? 'Non renseignées';
            $typeReve = $r['typeReve'] ?? 'Normal';
            $intensite = $r['intensite'] ?? 5;
            $couleur = !empty($r['couleur']) ? 'Oui' : 'Non';
            $recurrent = !empty($r['recurrent']) ? 'Oui' : 'Non';

            return "- Titre : {$titre}\n  Description : {$description}\n  Émotions : {$emotions}\n  Type : {$typeReve}\n  Intensité : {$intensite}/10\n  Couleur : {$couleur}\n  Récurrent : {$recurrent}";
        }, $reves));

        return <<<TXT
Tu es un assistant de bien-être spécialisé dans l’analyse symbolique des rêves.
Tu ne poses jamais de diagnostic médical.
Tu produis une synthèse psychologique douce, prudente, structurée et utile.
Réponds uniquement avec un JSON conforme au schéma demandé.

Analyse globalement l’ensemble de ces rêves :

{$resume}

Consignes supplémentaires :
- Fais ressortir les tendances dominantes.
- Déduis un profil dominant plausible.
- Identifie les thèmes ou symboles récurrents.
- Fournis 5 recommandations concrètes.
- Le niveau d’alerte reste prudent et non médical.
TXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function singleSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scorePsychologique' => [
                    'type' => 'integer',
                ],
                'profilDominant' => [
                    'type' => 'string',
                    'enum' => [
                        'ANXIEUX',
                        'CRÉATIF',
                        'NOSTALGIQUE',
                        'CONFLICTUEL',
                        'SPIRITUEL',
                        'INSÉCURISÉ',
                        'LIBÉRATEUR',
                        'ÉQUILIBRÉ',
                    ],
                ],
                'symbolesDetectes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nom' => ['type' => 'string'],
                            'signification' => ['type' => 'string'],
                        ],
                        'required' => ['nom', 'signification'],
                    ],
                ],
                'impactEmotionnel' => [
                    'type' => 'string',
                ],
                'conclusion' => [
                    'type' => 'string',
                ],
                'recommandations' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'niveauAlerte' => [
                    'type' => 'string',
                    'enum' => ['AUCUN', 'FAIBLE', 'MODÉRÉ', 'ÉLEVÉ', 'CRITIQUE'],
                ],
            ],
            'required' => [
                'scorePsychologique',
                'profilDominant',
                'symbolesDetectes',
                'impactEmotionnel',
                'conclusion',
                'recommandations',
                'niveauAlerte',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function globalSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scorePsychologique' => [
                    'type' => 'integer',
                ],
                'profilDominant' => [
                    'type' => 'string',
                    'enum' => [
                        'ANXIEUX',
                        'CRÉATIF',
                        'NOSTALGIQUE',
                        'CONFLICTUEL',
                        'SPIRITUEL',
                        'INSÉCURISÉ',
                        'LIBÉRATEUR',
                        'ÉQUILIBRÉ',
                    ],
                ],
                'symbolesDetectes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nom' => ['type' => 'string'],
                            'signification' => ['type' => 'string'],
                        ],
                        'required' => ['nom', 'signification'],
                    ],
                ],
                'impactEmotionnel' => [
                    'type' => 'string',
                ],
                'conclusion' => [
                    'type' => 'string',
                ],
                'recommandations' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'niveauAlerte' => [
                    'type' => 'string',
                    'enum' => ['AUCUN', 'FAIBLE', 'MODÉRÉ', 'ÉLEVÉ', 'CRITIQUE'],
                ],
            ],
            'required' => [
                'scorePsychologique',
                'profilDominant',
                'symbolesDetectes',
                'impactEmotionnel',
                'conclusion',
                'recommandations',
                'niveauAlerte',
            ],
        ];
    }
}