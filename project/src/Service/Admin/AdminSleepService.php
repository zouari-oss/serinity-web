<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\SleepDream;
use App\Entity\SleepSession;
use App\Repository\SleepDreamRepository;
use App\Repository\SleepSessionRepository;

final readonly class AdminSleepService
{
    public function __construct(
        private SleepSessionRepository $sleepSessionRepository,
        private SleepDreamRepository $sleepDreamRepository,
    ) {
    }

    /**
     * @return array{
     *     totalSessions:int,
     *     totalDreams:int,
     *     avgDuration:float,
     *     avgQuality:int,
     *     nightmares:int,
     *     qualityBreakdown:array<string,int>,
     *     dreamTypeBreakdown:array<string,int>
     * }
     */
    public function summary(): array
    {
        $sessions = $this->sleepSessionRepository->findAdminFiltered();
        $dreams = $this->sleepDreamRepository->findAdminFiltered();

        $qualityMap = ['Excellente' => 100, 'Bonne' => 75, 'Moyenne' => 50, 'Mauvaise' => 25];
        $qualityBreakdown = ['Excellente' => 0, 'Bonne' => 0, 'Moyenne' => 0, 'Mauvaise' => 0];
        $dreamTypeBreakdown = ['Normal' => 0, 'Lucide' => 0, 'Cauchemar' => 0];

        $totalDuration = 0.0;
        $qualitySum = 0;
        $qualityCount = 0;
        foreach ($sessions as $session) {
            $quality = $session->getQualite();
            if ($quality !== null && isset($qualityBreakdown[$quality])) {
                ++$qualityBreakdown[$quality];
                $qualitySum += $qualityMap[$quality];
                ++$qualityCount;
            }

            $totalDuration += (float) ($session->getDureeSommeil() ?? 0.0);
        }

        $nightmares = 0;
        foreach ($dreams as $dream) {
            $type = $dream->getTypeReve() ?? 'Normal';
            if (isset($dreamTypeBreakdown[$type])) {
                ++$dreamTypeBreakdown[$type];
            }
            if (mb_strtolower($type) === 'cauchemar') {
                ++$nightmares;
            }
        }

        return [
            'totalSessions' => count($sessions),
            'totalDreams' => count($dreams),
            'avgDuration' => count($sessions) > 0 ? round($totalDuration / count($sessions), 1) : 0.0,
            'avgQuality' => $qualityCount > 0 ? (int) round($qualitySum / $qualityCount) : 0,
            'nightmares' => $nightmares,
            'qualityBreakdown' => $qualityBreakdown,
            'dreamTypeBreakdown' => $dreamTypeBreakdown,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sessions(?string $search = null, ?string $quality = null): array
    {
        $rows = $this->sleepSessionRepository->findAdminFiltered([
            'q' => $search,
            'quality' => $quality,
        ]);

        return array_map(fn(SleepSession $session): array => $this->sessionArray($session), $rows);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function dreams(?string $search = null, ?string $type = null): array
    {
        $rows = $this->sleepDreamRepository->findAdminFiltered([
            'q' => $search,
            'type' => $type,
        ]);

        return array_map(fn(SleepDream $dream): array => $this->dreamArray($dream), $rows);
    }

    /**
     * @return array<string,mixed>
     */
    private function sessionArray(SleepSession $session): array
    {
        return [
            'id' => $session->getId(),
            'userEmail' => $session->getUser()->getEmail(),
            'userRole' => $session->getUser()->getRole(),
            'sleepDate' => $session->getDateNuit()?->format('Y-m-d'),
            'bedTime' => $session->getHeureCoucher(),
            'wakeTime' => $session->getHeureReveil(),
            'sleepDuration' => $session->getDureeSommeil(),
            'quality' => $session->getQualite(),
            'humeurReveil' => $session->getHumeurReveil(),
            'environnement' => $session->getEnvironnement(),
            'temperature' => $session->getTemperature(),
            'bruitNiveau' => $session->getBruitNiveau(),
            'interruptions' => $session->getInterruptions(),
            'insufficient' => $session->isSleepInsufficient(),
            'statusMessage' => $session->isSleepInsufficient() ? 'Sommeil insuffisant' : 'Normal',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dreamArray(SleepDream $dream): array
    {
        return [
            'id' => $dream->getId(),
            'userEmail' => $dream->getUser()?->getEmail(),
            'userRole' => $dream->getUser()?->getRole(),
            'sleepDate' => $dream->getSommeilId()?->getDateNuit()?->format('Y-m-d'),
            'title' => $dream->getTitre(),
            'dreamType' => $dream->getTypeReve(),
            'description' => $dream->getDescription(),
            'mood' => $dream->getHumeur(),
            'intensity' => $dream->getIntensite(),
            'isRecurring' => $dream->getRecurrent(),
            'isColor' => $dream->getCouleur(),
            'emotions' => $dream->getEmotions(),
            'symbols' => $dream->getSymboles(),
        ];
    }
}
