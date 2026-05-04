<?php

namespace App\Controller\Sleep\Api;

use App\Service\Sleep\SleepAdviceService;
use App\Service\Sleep\WeatherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class SleepWeatherController extends AbstractController
{
    #[Route('/sleep/api/weather-widget', name: 'app_sleep_weather_widget', methods: ['GET'])]
    public function widget(
        WeatherService $weatherService,
        SleepAdviceService $sleepAdviceService
    ): JsonResponse {
        $user = $this->getUser();

        $lat = null;
        $lon = null;
        $locationSource = 'default';
        $userKey = 'guest';

        if ($user) {
            if (method_exists($user, 'getId')) {
                $userKey = (string) $user->getId();
            }

            if (
                method_exists($user, 'getLatitude') &&
                method_exists($user, 'getLongitude') &&
                $user->getLatitude() !== null &&
                $user->getLongitude() !== null
            ) {
                $lat = (float) $user->getLatitude();
                $lon = (float) $user->getLongitude();
                $locationSource = 'user';
            }
        }

        $meteo = $weatherService->getSleepWidgetData($lat, $lon, $userKey);

        if (!$meteo) {
            return $this->json([
                'success' => false,
                'message' => 'La météo est momentanément indisponible. Réessaie dans quelques instants.',
            ], 503);
        }

        return $this->json([
            'success' => true,
            'meteo' => $meteo,
            'analyse' => $sleepAdviceService->analyze($meteo),
            'locationSource' => $locationSource,
        ]);
    }
}
