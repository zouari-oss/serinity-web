<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\Common\ServiceResult;
use App\Dto\Mood\MoodCreateRequest;
use App\Dto\Mood\MoodHistoryFilterRequest;
use App\Dto\Mood\MoodSummaryRequest;
use App\Entity\MoodEmotion;
use App\Entity\MoodEntry;
use App\Entity\MoodInfluence;
use App\Entity\User;
use App\Repository\MoodEmotionRepository;
use App\Repository\MoodEntryRepository;
use App\Repository\MoodInfluenceRepository;
use App\Service\Notification\CriticalAlertGuard;
use App\Service\Notification\DoctorNtfyNotifier;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserMoodService
{
    public function __construct(
        private MoodEntryRepository $moodEntryRepository,
        private MoodEmotionRepository $moodEmotionRepository,
        private MoodInfluenceRepository $moodInfluenceRepository,
        private EntityManagerInterface $entityManager,
        private CriticalPeriodDetectionService $criticalPeriodDetectionService,
        private ResilienceScoreService $resilienceScoreService,
        private DoctorNtfyNotifier $doctorNtfyNotifier,
        private CriticalAlertGuard $criticalAlertGuard,
        private int $ntfyCooldownSeconds,
    ) {
    }

    public function create(User $user, MoodCreateRequest $request): ServiceResult
    {
        $wasCriticalBefore = $this->criticalPeriodDetectionService->isCritical($user);

        $rawEntryDate = $this->nullable($request->entryDate);
        $entryDate = $rawEntryDate === null
            ? new \DateTimeImmutable()
            : $this->parseDate($rawEntryDate);

        if ($entryDate === null) {
            return ServiceResult::failure('Invalid entry date.');
        }

        $momentType = strtoupper(trim($request->momentType));
        if ($momentType === 'DAY' && $this->moodEntryRepository->hasDayEntryForDate($user, $entryDate)) {
            return ServiceResult::failure('Only one DAY entry is allowed per day.');
        }

        $emotions = $this->resolveEmotions($request->emotionKeys);
        if ($emotions === null) {
            return ServiceResult::failure('One or more emotion keys are invalid.');
        }

        $influences = $this->resolveInfluences($request->influenceKeys);
        if ($influences === null) {
            return ServiceResult::failure('One or more influence keys are invalid.');
        }

        $now = new \DateTimeImmutable();

        $entry = (new MoodEntry())
            ->setUser($user)
            ->setEntryDate($entryDate)
            ->setMomentType($momentType)
            ->setMoodLevel($request->moodLevel)
            ->setUpdatedAt($now);

        foreach ($emotions as $emotion) {
            $entry->addEmotion($emotion);
        }

        foreach ($influences as $influence) {
            $entry->addInfluence($influence);
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
        $this->handleCriticalStateNotification($user, $wasCriticalBefore, true);

        return ServiceResult::success('Mood entry created successfully.', $this->toArray($entry));
    }

    public function update(User $user, string $entryId, MoodCreateRequest $request): ServiceResult
    {
        $wasCriticalBefore = $this->criticalPeriodDetectionService->isCritical($user);

        $entry = $this->moodEntryRepository->findOneBy([
            'id' => $entryId,
            'user' => $user,
        ]);

        if (!$entry instanceof MoodEntry) {
            return ServiceResult::failure('Mood entry not found.');
        }

        $rawEntryDate = $this->nullable($request->entryDate);
        $entryDate = $rawEntryDate === null
            ? $entry->getEntryDate()
            : $this->parseDate($rawEntryDate);

        if ($entryDate === null) {
            return ServiceResult::failure('Invalid entry date.');
        }

        $momentType = strtoupper(trim($request->momentType));
        if (
            $momentType === 'DAY'
            && $this->moodEntryRepository->hasDayEntryForDate($user, $entryDate, $entry->getId())
        ) {
            return ServiceResult::failure('Only one DAY entry is allowed per day.');
        }

        $emotions = $this->resolveEmotions($request->emotionKeys);
        if ($emotions === null) {
            return ServiceResult::failure('One or more emotion keys are invalid.');
        }

        $influences = $this->resolveInfluences($request->influenceKeys);
        if ($influences === null) {
            return ServiceResult::failure('One or more influence keys are invalid.');
        }

        $entry
            ->setEntryDate($entryDate)
            ->setMomentType($momentType)
            ->setMoodLevel($request->moodLevel)
            ->setUpdatedAt(new \DateTimeImmutable());

        foreach ($entry->getEmotions()->toArray() as $emotion) {
            $entry->removeEmotion($emotion);
        }
        foreach ($emotions as $emotion) {
            $entry->addEmotion($emotion);
        }

        foreach ($entry->getInfluences()->toArray() as $influence) {
            $entry->removeInfluence($influence);
        }
        foreach ($influences as $influence) {
            $entry->addInfluence($influence);
        }

        $this->entityManager->flush();
        $this->handleCriticalStateNotification($user, $wasCriticalBefore);

        return ServiceResult::success('Mood entry updated successfully.', $this->toArray($entry));
    }

    public function delete(User $user, string $entryId): ServiceResult
    {
        $entry = $this->moodEntryRepository->findOneBy([
            'id' => $entryId,
            'user' => $user,
        ]);

        if (!$entry instanceof MoodEntry) {
            return ServiceResult::failure('Mood entry not found.');
        }

        $wasCriticalBefore = $this->criticalPeriodDetectionService->isCritical($user);
        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        if ($wasCriticalBefore && !$this->criticalPeriodDetectionService->isCritical($user)) {
            $this->criticalAlertGuard->clear($user->getId());
        }

        return ServiceResult::success('Mood entry deleted successfully.');
    }

    /**
     * @return list<array{key:string,label:string}>
     */
    public function getEmotionOptions(): array
    {
        $rows = [];
        $emotions = $this->moodEmotionRepository->createQueryBuilder('emotion')
            ->orderBy('emotion.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($emotions as $emotion) {
            $rows[] = [
                'key' => self::toKey($emotion->getName()),
                'label' => $emotion->getName(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key:string,label:string}>
     */
    public function getInfluenceOptions(): array
    {
        $rows = [];
        $influences = $this->moodInfluenceRepository->createQueryBuilder('influence')
            ->orderBy('influence.name', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($influences as $influence) {
            $rows[] = [
                'key' => self::toKey($influence->getName()),
                'label' => $influence->getName(),
            ];
        }

        return $rows;
    }

    public function history(User $user, MoodHistoryFilterRequest $request): ServiceResult
    {
        $fromDate = $this->parseDate($request->fromDate);
        $toDate = $this->parseDate($request->toDate);

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            return ServiceResult::failure('fromDate must be earlier than or equal to toDate.');
        }

        return ServiceResult::success('Mood history fetched successfully.', $this->getGroupedHistory($user, $request));
    }

    public function summary(User $user, MoodSummaryRequest $request): ServiceResult
    {
        $fromDate = $this->parseDate($request->fromDate);
        $toDate = $this->parseDate($request->toDate);

        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            return ServiceResult::failure('fromDate must be earlier than or equal to toDate.');
        }

        return ServiceResult::success('Mood summary fetched successfully.', $this->getSummary($user, $request));
    }

    /**
     * @return array{groups:array<string,array{label:string,entries:list<array<string,mixed>>}>,pagination:array{total:int,page:int,limit:int,totalPages:int}}
     */
    public function getGroupedHistory(User $user, MoodHistoryFilterRequest $request): array
    {
        $page = max(1, $request->page);
        $limit = min(100, max(1, $request->limit));
        $offset = ($page - 1) * $limit;

        $entries = $this->moodEntryRepository->findHistory(
            user: $user,
            search: $this->nullable($request->search),
            momentType: $request->momentType,
            fromDate: $this->parseDate($request->fromDate),
            toDate: $this->parseDate($request->toDate),
            limit: $limit,
            offset: $offset,
        );

        $total = $this->moodEntryRepository->countHistory(
            user: $user,
            search: $this->nullable($request->search),
            momentType: $request->momentType,
            fromDate: $this->parseDate($request->fromDate),
            toDate: $this->parseDate($request->toDate),
        );

        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $groups = [];

        foreach ($entries as $entry) {
            $entryDay = $entry->getEntryDate()->setTime(0, 0);

            if ($entryDay == $today) {
                $groupKey = 'today';
                $groupLabel = 'Today';
            } elseif ($entryDay == $yesterday) {
                $groupKey = 'yesterday';
                $groupLabel = 'Yesterday';
            } else {
                $groupKey = $entryDay->format('Y-m-d');
                $groupLabel = $entryDay->format('Y-m-d');
            }

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $groupLabel,
                    'entries' => [],
                ];
            }

            $groups[$groupKey]['entries'][] = $this->toArray($entry);
        }

        return [
            'groups' => $groups,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / max(1, $limit)),
            ],
        ];
    }

    /**
     * @return array{
     *     weeklyCount:int,
     *     weeklyAverageMood:float|null,
     *     mostUsedType:string,
     *     topEmotion:array{label:string,usageCount:int},
     *     topInfluence:array{label:string,usageCount:int},
     *     fromDate:string,
     *     toDate:string,
     *     criticalPeriod:array{
     *         status:string,
     *         score:int,
     *         reasons:list<string>,
     *         timeframe:array{days:int,from:string,to:string},
     *         summary:string,
     *         repeatedNegativeEmotion:?array{name:string,count:int}
     *     },
     *     resilienceScore:array{
     *         score:int,
     *         label:string,
     *         breakdown:array{
     *             mood:int,
     *             tracking:int,
     *             journaling:int,
     *             moodAverage:float|null,
     *             trackedMoodDays:int,
     *             trackedJournalDays:int,
     *             windowDays:int
     *         },
     *         timeframe:array{days:int,from:string,to:string},
     *         interpretation:string
     *     }
     * }
     */
    public function getSummary(User $user, MoodSummaryRequest $request): array
    {
        [$fromDate, $toDate] = $this->resolveRange($request);

        $weeklyEntries = $this->moodEntryRepository->findWithinDateRange($user, $fromDate, $toDate);
        $weeklyCount = count($weeklyEntries);
        $weeklyAverageMood = null;

        if ($weeklyCount > 0) {
            $totalMood = 0;
            foreach ($weeklyEntries as $entry) {
                $totalMood += $entry->getMoodLevel();
            }

            $weeklyAverageMood = round($totalMood / $weeklyCount, 1);
        }

        $dayCount = $this->moodEntryRepository->countTypeWithinRange($user, $fromDate, $toDate, 'DAY');
        $momentCount = $this->moodEntryRepository->countTypeWithinRange($user, $fromDate, $toDate, 'MOMENT');
        $mostUsedType = $dayCount === 0 && $momentCount === 0
            ? 'No data'
            : ($dayCount >= $momentCount ? 'DAY' : 'MOMENT');

        $topEmotion = $this->moodEntryRepository->findTopEmotionWithinRange($user, $fromDate, $toDate) ?? [
            'label' => 'No data',
            'usageCount' => 0,
        ];

        $topInfluence = $this->moodEntryRepository->findTopInfluenceWithinRange($user, $fromDate, $toDate) ?? [
            'label' => 'No data',
            'usageCount' => 0,
        ];

        $criticalPeriod = $this->criticalPeriodDetectionService->detect($user, 7);
        $resilienceScore = $this->resilienceScoreService->compute($user, 14);

        return [
            'weeklyCount' => $weeklyCount,
            'weeklyAverageMood' => $weeklyAverageMood,
            'mostUsedType' => $mostUsedType,
            'topEmotion' => $topEmotion,
            'topInfluence' => $topInfluence,
            'fromDate' => $fromDate->format('Y-m-d'),
            'toDate' => $toDate->format('Y-m-d'),
            'criticalPeriod' => $criticalPeriod,
            'resilienceScore' => $resilienceScore,
        ];
    }

    /** @return list<MoodEmotion>|null */
    private function resolveEmotions(array $keys): ?array
    {
        $normalized = $this->normalizeNames($keys, []);
        $emotions = $this->moodEmotionRepository->findByNames($normalized);

        return count($emotions) === count($normalized) ? $emotions : null;
    }

    /** @return list<MoodInfluence>|null */
    private function resolveInfluences(array $keys): ?array
    {
        $normalized = $this->normalizeNames($keys, [
            'work' => 'school/work',
            'school_work' => 'school/work',
            'social' => 'social media',
        ]);
        $influences = $this->moodInfluenceRepository->findByNames($normalized);

        return count($influences) === count($normalized) ? $influences : null;
    }

    /**
     * @param list<mixed> $values
     * @param array<string, string> $aliases
     * @return list<string>
     */
    private function normalizeNames(array $values, array $aliases): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $candidate = mb_strtolower(trim((string) $value));
            $candidate = str_replace('_', ' ', $candidate);
            $candidate = $aliases[$candidate] ?? $candidate;

            if ($candidate !== '') {
                $normalized[] = $candidate;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    private function buildPatientLabel(User $user): string
    {
        $profile = $user->getProfile();

        if ($profile !== null) {
            $fullName = trim(sprintf(
                '%s %s',
                (string) ($profile->getFirstName() ?? ''),
                (string) ($profile->getLastName() ?? ''),
            ));

            if ($fullName !== '') {
                return $fullName;
            }

            $username = trim($profile->getUsername());
            if ($username !== '') {
                return $username;
            }
        }

        return $user->getEmail();
    }

    private function handleCriticalStateNotification(
        User $user,
        bool $wasCriticalBefore,
        bool $sendWhenStillCritical = false,
    ): void
    {
        $isCriticalNow = $this->criticalPeriodDetectionService->isCritical($user);

        if ($isCriticalNow && (!$wasCriticalBefore || $sendWhenStillCritical)) {
            if ($this->criticalAlertGuard->shouldSend($user->getId(), $this->ntfyCooldownSeconds)) {
                $this->doctorNtfyNotifier->sendCriticalPatientAlert(
                    $this->buildPatientLabel($user),
                    $user->getEmail(),
                    'Critical state detected. Please contact the patient.',
                );
            }

            return;
        }

        if ($wasCriticalBefore && !$isCriticalNow) {
            $this->criticalAlertGuard->clear($user->getId());
        }
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

    /**
     * @return array{
     *     id:string|int,
     *     entryDate:string,
     *     momentType:string,
     *     moodLevel:int,
     *     note:?string,
     *     emotions:list<array{key:string,label:string}>,
     *     influences:list<array{key:string,label:string}>,
     *     createdAt:string,
     *     updatedAt:string
     * }
     */
    private function toArray(MoodEntry $entry): array
    {
        $emotions = array_map(static fn (MoodEmotion $emotion) => [
            'key' => self::toKey($emotion->getName()),
            'label' => $emotion->getName(),
        ], $entry->getEmotions()->toArray());

        $influences = array_map(static fn (MoodInfluence $influence) => [
            'key' => self::toKey($influence->getName()),
            'label' => $influence->getName(),
        ], $entry->getInfluences()->toArray());

        return [
            'id' => $entry->getId(),
            'entryDate' => $entry->getEntryDate()->format('Y-m-d H:i:s'),
            'momentType' => $entry->getMomentType(),
            'moodLevel' => $entry->getMoodLevel(),
            'note' => null,
            'emotions' => $emotions,
            'influences' => $influences,
            'createdAt' => $entry->getUpdatedAt()->format('c'),
            'updatedAt' => $entry->getUpdatedAt()->format('c'),
        ];
    }

    private static function toKey(string $name): string
    {
        $key = mb_strtolower(trim($name));
        $key = preg_replace('/[^a-z0-9]+/u', '_', $key) ?? $key;

        return trim($key, '_');
    }
}
