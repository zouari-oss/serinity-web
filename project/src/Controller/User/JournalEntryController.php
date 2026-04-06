<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\JournalEntry;
use App\Repository\JournalEntryRepository;
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
    ) {
    }

    #[Route('', name: 'user_ui_journal_entry', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $search = trim((string) $request->query->get('q', ''));

        $journalEntries = $this->journalEntryRepository->findForUser($user, $search === '' ? null : $search);
        $groupedEntries = $this->groupEntries($journalEntries);

        $allEntries = $this->journalEntryRepository->findForUser($user);
        $distinctDays = $this->extractDistinctJournalDays($allEntries);

        return $this->render('user/pages/journal_entry.html.twig', [
            'nav' => $this->buildNav('user_ui_journal_entry'),
            'userName' => $user->getEmail(),
            'grouped_entries' => $groupedEntries,
            'search' => $search,
            'current_streak' => $this->calculateCurrentStreak($distinctDays),
            'longest_streak' => $this->calculateLongestStreak($distinctDays),
            'max_entries_one_day' => $this->calculateMaxEntriesOneDay($allEntries),
        ]);
    }

    #[Route('/new', name: 'user_ui_journal_entry_new', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): RedirectResponse
    {
        $user = $this->currentUser();
        $title = trim((string) $request->request->get('title', ''));
        $content = trim((string) $request->request->get('content', ''));

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

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        $this->addFlash('success', 'Journal entry created successfully.');

        return $this->redirectToRoute('user_ui_journal_entry');
    }

    #[Route('/{id}/edit', name: 'user_ui_journal_entry_edit', methods: ['POST'])]
    public function edit(Request $request, int $id, ValidatorInterface $validator): RedirectResponse
    {
        $user = $this->currentUser();
        $entry = $this->journalEntryRepository->findOneOwnedByUser($user, $id);

        if ($entry === null) {
            $this->addFlash('error', 'Journal entry not found.');

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        if (!$this->isCsrfTokenValid('journal_edit_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid edit token.');

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        $entry
            ->setTitle(trim((string) $request->request->get('title', '')))
            ->setContent(trim((string) $request->request->get('content', '')))
            ->setUpdatedAt(new \DateTimeImmutable());

        $violations = $validator->validate($entry);
        if (count($violations) > 0) {
            $this->addFlash('error', $violations[0]->getMessage());

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Journal entry updated successfully.');

        return $this->redirectToRoute('user_ui_journal_entry');
    }

    #[Route('/{id}/delete', name: 'user_ui_journal_entry_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): RedirectResponse
    {
        $user = $this->currentUser();
        $entry = $this->journalEntryRepository->findOneOwnedByUser($user, $id);

        if ($entry === null) {
            $this->addFlash('error', 'Journal entry not found.');

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        if (!$this->isCsrfTokenValid('journal_delete_' . $entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid delete token.');

            return $this->redirectToRoute('user_ui_journal_entry');
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        $this->addFlash('success', 'Journal entry deleted successfully.');

        return $this->redirectToRoute('user_ui_journal_entry');
    }

    /**
     * @param list<JournalEntry> $entries
     * @return array<string,array{label:string,entries:list<JournalEntry>}>
     */
    private function groupEntries(array $entries): array
    {
        $today = new \DateTimeImmutable('today');
        $yesterday = $today->modify('-1 day');
        $groupedEntries = [];

        foreach ($entries as $entry) {
            $entryDay = $entry->getCreatedAt()->setTime(0, 0);

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

            if (!isset($groupedEntries[$groupKey])) {
                $groupedEntries[$groupKey] = ['label' => $groupLabel, 'entries' => []];
            }

            $groupedEntries[$groupKey]['entries'][] = $entry;
        }

        return $groupedEntries;
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
}
