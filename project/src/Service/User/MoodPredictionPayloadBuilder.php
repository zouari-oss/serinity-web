<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\MoodEntry;
use App\Entity\User;
use App\Repository\MoodEntryRepository;

final readonly class MoodPredictionPayloadBuilder
{
    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
    ) {
    }

    /**
     * Builds the payload expected by the Mood ML v5 service.
     *
     * @return array{
     *     userId: string,
     *     weekStart: string,
     *     weekEnd: string,
     *     entries: list<array{
     *         entryDate: string,
     *         momentType: string,
     *         moodLevel: int,
     *         emotions: list<string>,
     *         influences: list<string>
     *     }>
     * }
     */
    public function buildForLastDays(User $user, int $days = 7): array
    {
        $days = max(1, $days);

        $toDate = new \DateTimeImmutable('today 23:59:59');
        $fromDate = $toDate
            ->sub(new \DateInterval(sprintf('P%dD', $days - 1)))
            ->setTime(0, 0);

        return $this->buildForRange($user, $fromDate, $toDate);
    }

    /**
     * @return array{
     *     userId: string,
     *     weekStart: string,
     *     weekEnd: string,
     *     entries: list<array{
     *         entryDate: string,
     *         momentType: string,
     *         moodLevel: int,
     *         emotions: list<string>,
     *         influences: list<string>
     *     }>
     * }
     */
    public function buildForRange(User $user, \DateTimeImmutable $fromDate, \DateTimeImmutable $toDate): array
    {
        $entries = $this->moodEntryRepository->findWithinDateRange($user, $fromDate, $toDate);

        usort(
            $entries,
            static fn (MoodEntry $a, MoodEntry $b): int => $a->getEntryDate() <=> $b->getEntryDate(),
        );

        return [
            'userId' => $user->getId(),
            'weekStart' => $fromDate->format('Y-m-d'),
            'weekEnd' => $toDate->format('Y-m-d'),
            'entries' => array_map(
                fn (MoodEntry $entry): array => $this->entryToPayloadRow($entry),
                $entries,
            ),
        ];
    }

    /**
     * @return array{
     *     entryDate: string,
     *     momentType: string,
     *     moodLevel: int,
     *     emotions: list<string>,
     *     influences: list<string>
     * }
     */
    private function entryToPayloadRow(MoodEntry $entry): array
    {
        return [
            'entryDate' => $entry->getEntryDate()->format('Y-m-d\TH:i:s'),
            'momentType' => strtoupper($entry->getMomentType()), // MOMENT or DAY
            'moodLevel' => $entry->getMoodLevel(), // 1 to 5
            'emotions' => $this->emotionNames($entry),
            'influences' => $this->influenceNames($entry),
        ];
    }

    /**
     * @return list<string>
     */
    private function emotionNames(MoodEntry $entry): array
    {
        $names = [];

        foreach ($entry->getEmotions() as $emotion) {
            $name = trim($emotion->getName());

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function influenceNames(MoodEntry $entry): array
    {
        $names = [];

        foreach ($entry->getInfluences() as $influence) {
            $name = trim($influence->getName());

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
