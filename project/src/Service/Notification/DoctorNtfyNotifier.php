<?php

declare(strict_types=1);

namespace App\Service\Notification;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class DoctorNtfyNotifier
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $topic,
        private bool $enabled,
    ) {
    }

    public function sendCriticalPatientAlert(
        string $patientLabel,
        string $patientEmail,
        string $summary = 'Critical state detected. Please contact the patient.',
    ): void {
        if (!$this->enabled) {
            return;
        }

        $body = sprintf(
            "Serinity critical patient alert\nPatient: %s\nEmail: %s\nMessage: %s",
            $patientLabel,
            $patientEmail,
            $summary,
        );

        $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/' . $this->topic, [
            'headers' => [
                'Title' => 'Serinity critical patient',
                'Priority' => 'urgent',
                'Tags' => 'warning,rotating_light,health_warning',
                'Content-Type' => 'text/plain; charset=utf-8',
            ],
            'body' => $body,
        ]);
    }
}
