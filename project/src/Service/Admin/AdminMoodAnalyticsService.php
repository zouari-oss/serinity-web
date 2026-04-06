<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Mood\MoodHistoryFilterRequest;
use App\Dto\Mood\MoodSummaryRequest;
use App\Repository\MoodEntryRepository;

final readonly class AdminMoodAnalyticsService
{
    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
    ) {
    }

    /**
     * @return array{
     *     range:array{fromDate:string,toDate:string},
     *     totals:array{entries:int,averageMood:float|null,mostUsedType:string},
     *     topEmotion:array{label:string,usageCount:int},
     *     topInfluence:array{label:string,usageCount:int}
     * }
     */
    public function getSummary(MoodSummaryRequest $request): array
    {
        [$fromDate, $toDate] = $this->resolveRange($request);

        $entries = $this->moodEntryRepository->findWithinDateRange(null, $fromDate, $toDate);
        $entryCount = count($entries);

        $averageMood = null;
        if ($entryCount > 0) {
            $totalMood = 0;
            foreach ($entries as $entry) {
                $totalMood += $entry->getMoodLevel();
            }

            $averageMood = round($totalMood / $entryCount, 1);
        }

        $dayCount = $this->moodEntryRepository->countTypeWithinRange(null, $fromDate, $toDate, 'DAY');
        $momentCount = $this->moodEntryRepository->countTypeWithinRange(null, $fromDate, $toDate, 'MOMENT');
        $mostUsedType = $dayCount === 0 && $momentCount === 0
            ? 'No data'
            : ($dayCount >= $momentCount ? 'DAY' : 'MOMENT');

        return [
            'range' => [
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate' => $toDate->format('Y-m-d'),
            ],
            'totals' => [
                'entries' => $entryCount,
                'averageMood' => $averageMood,
                'mostUsedType' => $mostUsedType,
            ],
            'topEmotion' => $this->moodEntryRepository->findTopEmotionWithinRange(null, $fromDate, $toDate) ?? [
                'label' => 'No data',
                'usageCount' => 0,
            ],
            'topInfluence' => $this->moodEntryRepository->findTopInfluenceWithinRange(null, $fromDate, $toDate) ?? [
                'label' => 'No data',
                'usageCount' => 0,
            ],
        ];
    }

    /**
     * @return array{
     *     rows:list<array{entryDate:string,momentType:string,moodLevel:int,userRole:string}>,
     *     pagination:array{total:int,page:int,limit:int,totalPages:int}
     * }
     */
    public function getHistory(MoodHistoryFilterRequest $request): array
    {
        return [
            'rows' => $this->getHistoryRows($request),
            'pagination' => $this->getHistoryPagination($request),
        ];
    }

    /**
     * @return array{total:int,page:int,limit:int,totalPages:int}
     */
    public function getHistoryPagination(MoodHistoryFilterRequest $request): array
    {
        $page = max(1, $request->page);
        $limit = min(100, max(1, $request->limit));

        $total = $this->moodEntryRepository->countHistory(
            user: null,
            search: $this->nullable($request->search),
            momentType: $request->momentType,
            fromDate: $this->parseDate($request->fromDate),
            toDate: $this->parseDate($request->toDate),
            level: $request->level,
        );

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }

    /**
     * @return list<array{entryDate:string,momentType:string,moodLevel:int,userRole:string}>
     */
    public function getHistoryRows(MoodHistoryFilterRequest $request): array
    {
        $page = max(1, $request->page);
        $limit = min(100, max(1, $request->limit));
        $offset = ($page - 1) * $limit;

        $rows = [];
        $entries = $this->moodEntryRepository->findHistory(
            user: null,
            search: $this->nullable($request->search),
            momentType: $request->momentType,
            fromDate: $this->parseDate($request->fromDate),
            toDate: $this->parseDate($request->toDate),
            limit: $limit,
            offset: $offset,
            level: $request->level,
        );

        foreach ($entries as $entry) {
            $rows[] = [
                'entryDate' => $entry->getEntryDate()->format('Y-m-d'),
                'momentType' => $entry->getMomentType(),
                'moodLevel' => $entry->getMoodLevel(),
                'userRole' => $entry->getUser()->getRole(),
            ];
        }

        return $rows;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array{\DateTimeImmutable,\DateTimeImmutable}
     */
    private function resolveRange(MoodSummaryRequest $request): array
    {
        $toDate = $this->parseDate($request->toDate)?->setTime(0, 0) ?? new \DateTimeImmutable('today');

        if ($request->fromDate !== null) {
            $fromDate = $this->parseDate($request->fromDate)?->setTime(0, 0);
            if ($fromDate !== null) {
                return [$fromDate, $toDate];
            }
        }

        $fromDate = $toDate->modify(sprintf('-%d days', max(0, $request->days - 1)));

        return [$fromDate, $toDate];
    }
}
