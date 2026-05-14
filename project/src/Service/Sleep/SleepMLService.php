<?php

namespace App\Service\Sleep;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SleepMLService
{
    private const API_BASE = 'http://127.0.0.1:5001/api/sleep';

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    public function predict(array $data): array
    {
        try {
            $response = $this->httpClient->request('POST', self::API_BASE . '/predict', [
                'json'    => $data,
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
            ]);

            $status  = $response->getStatusCode();
            $content = $response->getContent(false); // brut, même si erreur

            // Essayer de décoder le JSON
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return [
                    'error'         => 'Réponse ML invalide (non JSON)',
                    'raw_content'   => substr($content, 0, 500),
                    'http_status'   => $status,
                ];
            }

            // On ajoute le statut HTTP pour info, mais on renvoie la structure Flask telle quelle
            $decoded['_http_status'] = $status;

            return $decoded;
        } catch (\Exception $e) {
            return ['error' => 'Service ML indisponible : ' . $e->getMessage()];
        }
    }

    public function isAvailable(): bool
    {
        try {
            return $this->httpClient->request('GET', self::API_BASE . '/health', ['timeout' => 3])
                    ->getStatusCode() === 200;
        } catch (\Exception) {
            return false;
        }
    }
}