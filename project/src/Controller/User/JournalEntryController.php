<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\JournalEntry;
use App\Repository\JournalEntryRepository;
use App\Service\AI\JournalEmotionTagger;
use App\Service\Api\CallMeBotClient;
use App\Service\User\JournalContentSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/user/journal/entry')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class JournalEntryController extends AbstractUserUiController
{
    public function __construct(
        private readonly JournalEntryRepository $journalEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly JournalContentSanitizer $journalContentSanitizer,
        private readonly CallMeBotClient $callMeBotClient,
        private readonly JournalEmotionTagger $journalEmotionTagger,
    ) {
    }

    #[Route('', name: 'user_ui_journal_entry', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $selectedMonth = $this->resolveCalendarMonth((string) $request->query->get('month', ''));
        $calendarMonth = $selectedMonth->format('Y-m');
        $monthStart = $selectedMonth->setTime(0, 0, 0);
        $monthEnd = $selectedMonth->modify('last day of this month')->setTime(23, 59, 59);

        $monthEntries = $this->journalEntryRepository->findForUserWithinRange($user, $monthStart, $monthEnd);
        $entriesByDay = $this->groupEntriesByDay($monthEntries);
        $calendarCells = $this->buildCalendarCells($monthStart, $monthEnd, $entriesByDay);

        $allEntries = $this->journalEntryRepository->findForUser($user);
        $distinctDays = $this->extractDistinctJournalDays($allEntries);

        return $this->render('user/pages/journal_entry.html.twig', [
            'nav' => $this->buildNav('user_ui_journal_entry'),
            'userName' => $user->getEmail(),
            'calendar_month' => $calendarMonth,
            'calendar_label' => $selectedMonth->format('F Y'),
            'previous_month' => $selectedMonth->modify('-1 month')->format('Y-m'),
            'next_month' => $selectedMonth->modify('+1 month')->format('Y-m'),
            'calendar_cells' => $calendarCells,
            'entries_by_day' => $entriesByDay,
            'current_streak' => $this->calculateCurrentStreak($distinctDays),
            'longest_streak' => $this->calculateLongestStreak($distinctDays),
            'max_entries_one_day' => $this->calculateMaxEntriesOneDay($allEntries),
        ]);
    }

    #[Route('/new', name: 'user_ui_journal_entry_new', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): RedirectResponse
    {
        $user = $this->currentUser();
        $month = $this->resolveMonthValue($request->request->get('month'));
        $title = trim((string) $request->request->get('title', ''));
        $content = $this->journalContentSanitizer->sanitize((string) $request->request->get('content', ''));

        $now = new \DateTimeImmutable();
        $entry = (new JournalEntry())
            ->setUser($user)
            ->setTitle($title)
            ->setContent($content)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $violations = $validator->validate($entry);
        if (count($violations) > 0) {
            $this->addFlash('error', $violations[0]->getMessage());

            return $this->redirectToJournalIndex($month);
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
        $this->applyEmotionTaggingSafely($entry);
        $this->callMeBotClient->sendJournalSavedNotification(
            $entry->getTitle() ?? '',
            $entry->getCreatedAt(),
        );

        $this->addFlash('success', 'Journal entry created successfully.');

        return $this->redirectToJournalIndex($month);
    }

    #[Route('/{id}/edit', name: 'user_ui_journal_entry_edit', methods: ['POST'])]
    public function edit(Request $request, int $id, ValidatorInterface $validator): RedirectResponse
    {
        $user = $this->currentUser();
        $month = $this->resolveMonthValue($request->request->get('month'));
        $entry = $this->journalEntryRepository->findOneOwnedByUser($user, $id);

        if ($entry === null) {
            $this->addFlash('error', 'Journal entry not found.');

            return $this->redirectToJournalIndex($month);
        }

        if (!$this->isCsrfTokenValid('journal_edit_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid edit token.');

            return $this->redirectToJournalIndex($month);
        }

        $sanitizedContent = $this->journalContentSanitizer->sanitize((string) $request->request->get('content', ''));
        $contentChanged = $entry->getContent() !== $sanitizedContent;

        $entry
            ->setTitle(trim((string) $request->request->get('title', '')))
            ->setContent($sanitizedContent)
            ->setUpdatedAt(new \DateTimeImmutable());

        $violations = $validator->validate($entry);
        if (count($violations) > 0) {
            $this->addFlash('error', $violations[0]->getMessage());

            return $this->redirectToJournalIndex($month);
        }

        $this->entityManager->flush();
        if ($contentChanged) {
            $this->applyEmotionTaggingSafely($entry);
        }
        $this->addFlash('success', 'Journal entry updated successfully.');

        return $this->redirectToJournalIndex($month);
    }

    #[Route('/{id}/delete', name: 'user_ui_journal_entry_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser();
        $month = $this->resolveMonthValue($request->request->get('month'));
        $entry = $this->journalEntryRepository->findOneOwnedByUser($user, $id);

        if ($entry === null) {
            $this->addFlash('error', 'Journal entry not found.');

            return $this->redirectToJournalIndex($month);
        }

        if (!$this->isCsrfTokenValid('journal_delete_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToJournalIndex($month);
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        $this->addFlash('success', 'Journal entry deleted successfully.');

        return $this->redirectToJournalIndex($month);
    }

    /**
     * @param list<JournalEntry> $entries
     * @return array<string,list<JournalEntry>>
     */
    private function groupEntriesByDay(array $entries): array
    {
        $groupedEntries = [];
        foreach ($entries as $entry) {
            $dayKey = $entry->getCreatedAt()->format('Y-m-d');
            $groupedEntries[$dayKey] ??= [];
            $groupedEntries[$dayKey][] = $entry;
        }

        ksort($groupedEntries);

        return $groupedEntries;
    }

    /**
     * @param array<string,list<JournalEntry>> $entriesByDay
     * @return list<array{
     *     date:string,
     *     day_number:int,
     *     is_current_month:bool,
     *     is_today:bool,
     *     has_entries:bool,
     *     entry_count:int
     * }>
     */
    private function buildCalendarCells(
        \DateTimeImmutable $monthStart,
        \DateTimeImmutable $monthEnd,
        array $entriesByDay,
    ): array {
        $calendarStart = $monthStart->modify('monday this week')->setTime(0, 0, 0);
        $calendarEnd = $monthEnd->modify('sunday this week')->setTime(0, 0, 0);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $cells = [];

        for ($cursor = $calendarStart; $cursor <= $calendarEnd; $cursor = $cursor->modify('+1 day')) {
            $dayKey = $cursor->format('Y-m-d');
            $entryCount = count($entriesByDay[$dayKey] ?? []);

            $cells[] = [
                'date' => $dayKey,
                'day_number' => (int) $cursor->format('j'),
                'is_current_month' => $cursor->format('Y-m') === $monthStart->format('Y-m'),
                'is_today' => $dayKey === $today,
                'has_entries' => $entryCount > 0,
                'entry_count' => $entryCount,
            ];
        }

        return $cells;
    }

    private function resolveCalendarMonth(string $monthValue): \DateTimeImmutable
    {
        $month = $this->resolveMonthValue($monthValue);
        if ($month === null) {
            return new \DateTimeImmutable('first day of this month');
        }

        try {
            return (new \DateTimeImmutable($month . '-01'))->setTime(0, 0, 0);
        } catch (\Exception) {
            return new \DateTimeImmutable('first day of this month');
        }
    }

    private function resolveMonthValue(mixed $monthValue): ?string
    {
        if (!is_string($monthValue)) {
            return null;
        }

        $month = trim($monthValue);

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month : null;
    }

    private function redirectToJournalIndex(?string $month): RedirectResponse
    {
        $parameters = $month === null ? [] : ['month' => $month];

        return $this->redirectToRoute('user_ui_journal_entry', $parameters);
    }

    /**
     * @param list<JournalEntry> $entries
     * @return list<string>
     */
    private function extractDistinctJournalDays(array $entries): array
    {
        $days = [];
        foreach ($entries as $entry) {
            $days[$entry->getCreatedAt()->format('Y-m-d')] = true;
        }

        $distinctDays = array_keys($days);
        sort($distinctDays);

        return $distinctDays;
    }

    /**
     * @param list<string> $distinctDays
     */
    private function calculateCurrentStreak(array $distinctDays): int
    {
        if ($distinctDays === []) {
            return 0;
        }

        $daySet = array_flip($distinctDays);
        $cursor = new \DateTimeImmutable('today');
        $streak = 0;

        while (isset($daySet[$cursor->format('Y-m-d')])) {
            $streak++;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    /**
     * @param list<string> $distinctDays
     */
    private function calculateLongestStreak(array $distinctDays): int
    {
        if ($distinctDays === []) {
            return 0;
        }

        $longest = 1;
        $current = 1;

        for ($index = 1; $index < count($distinctDays); $index++) {
            $previousDay = new \DateTimeImmutable($distinctDays[$index - 1]);
            $currentDay = new \DateTimeImmutable($distinctDays[$index]);

            if ($previousDay->modify('+1 day')->format('Y-m-d') === $currentDay->format('Y-m-d')) {
                $current++;
            } else {
                $current = 1;
            }

            if ($current > $longest) {
                $longest = $current;
            }
        }

        return $longest;
    }

    /**
     * @param list<JournalEntry> $entries
     */
    private function calculateMaxEntriesOneDay(array $entries): int
    {
        $entriesPerDay = [];

        foreach ($entries as $entry) {
            $dayKey = $entry->getCreatedAt()->format('Y-m-d');
            $entriesPerDay[$dayKey] = ($entriesPerDay[$dayKey] ?? 0) + 1;
        }

        return $entriesPerDay === [] ? 0 : max($entriesPerDay);
    }

    private function applyEmotionTaggingSafely(JournalEntry $entry): void
    {
        try {
            if (!$this->journalEmotionTagger->apply($entry)) {
                return;
            }

            $this->entityManager->flush();
        } catch (\Throwable) {
            $this->addFlash('error', 'Journal entry was saved, but emotion tagging is currently unavailable.');
        }
    }
}
