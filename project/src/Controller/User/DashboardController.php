<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\Mood\MoodSummaryRequest;
use App\Repository\MoodEntryRepository;
use App\Repository\CategoryRepository;
use App\Service\Api\ZenQuotesClient;
use App\Service\ProfileLookupService;
use App\Service\ThreadService;
use App\Service\User\UserDashboardService;
use App\Service\User\UserMoodService;
use App\Service\User\RecoveryPlanService;
use App\Model\CurrentUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DashboardController extends AbstractUserUiController
{
    public function __construct(
        private readonly UserDashboardService $userDashboardService,
        private readonly UserMoodService $userMoodService,
        private readonly RecoveryPlanService $recoveryPlanService,
        private readonly MoodEntryRepository $moodEntryRepository,
        private readonly ZenQuotesClient $zenQuotesClient,
        private readonly ThreadService $threadService,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProfileLookupService $profileLookupService,
    ) {
    }

    #[Route('/dashboard', name: 'user_ui_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->currentUser();

        return $this->render('user/pages/dashboard.html.twig', [
            'nav' => $this->buildNav('user_ui_dashboard'),
            'userName' => $user->getEmail(),
            'summary' => $this->userDashboardService->getSummary($user),
        ]);
    }

    #[Route('/consultations', name: 'user_ui_consultations', methods: ['GET'])]
    public function consultations(): RedirectResponse
    {
        $user = $this->currentUser();

        if ($user->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_therapist_rdv');
        }

        // Redirect users to the rendez-vous (RDV) page by route name
        return $this->redirectToRoute('app_patient_rdv');
    }

    #[Route('/forum', name: 'user_ui_forum', methods: ['GET'])]
    public function forum(): Response
    {
        $user = $this->currentUser();

        if ($user->getRole() === 'THERAPIST') {
            return $this->redirectToRoute('app_admin_forum');
        }

        $baseThreads = $this->threadService->feed(['excludeArchived' => true]);
        $this->hydrateThreadAuthors($baseThreads);

        $displayUser = new CurrentUser(
            $user->getId(),
            $user->getProfile()?->getUsername() ?? $user->getEmail(),
            'User',
            $user->getRoles(),
        );

        return $this->render('user/pages/forum.html.twig', [
            'nav' => $this->buildNav('user_ui_forum'),
            'userName' => $user->getEmail(),
            'threads' => $baseThreads,
            'categories' => $this->categoryRepository->findAll(),
            'currentUser' => $displayUser,
            'currentSort' => 'most_followers',
            'activeStatuses' => [],
            'activeTypes' => [],
            'activeCategories' => [],
        ]);
    }

    private function hydrateThreadAuthors(array $threads): void
    {
        $ids = [];
        foreach ($threads as $thread) {
            $ids[] = $thread->getAuthorId() ?? '';
        }

        $usernames = $this->profileLookupService->usernamesByIds($ids);

        foreach ($threads as $thread) {
            $authorId = $thread->getAuthorId() ?? '';
            $thread->setAuthorUsername($usernames[$authorId] ?? 'Unknown User');
        }
    }

    #[Route('/mood', name: 'user_ui_mood', methods: ['GET'])]
    public function mood(): Response
    {
        $user = $this->currentUser();

        return $this->render('user/pages/mood.html.twig', [
            'nav' => $this->buildNav('user_ui_mood'),
            'userName' => $user->getEmail(),
            'emotionOptions' => $this->userMoodService->getEmotionOptions(),
            'influenceOptions' => $this->userMoodService->getInfluenceOptions(),
        ]);
    }

    #[Route('/mood/recovery-plan', name: 'user_ui_mood_recovery_plan', methods: ['GET'])]
    public function recoveryPlan(Request $request): Response
    {
        $user = $this->currentUser();
        $weeklyTrendReviewed = $this->isWeeklyTrendReviewed($request);

        return $this->render('user/pages/mood_recovery_plan.html.twig', [
            'nav' => $this->buildNav('user_ui_mood_recovery_plan'),
            'userName' => $user->getEmail(),
            'plan' => $this->recoveryPlanService->generate($user, $weeklyTrendReviewed),
        ]);
    }

    #[Route('/mood/insights', name: 'user_ui_mood_insights', methods: ['GET'])]
    public function moodInsights(Request $request): Response
    {
        $user = $this->currentUser();
        $summaryRequest = new MoodSummaryRequest();
        $summaryRequest->days = 7;
        $summary = $this->userMoodService->getSummary($user, $summaryRequest);
        $fromDate = new \DateTimeImmutable($summary['fromDate'] . ' 00:00:00');
        $toDate = new \DateTimeImmutable($summary['toDate'] . ' 23:59:59');

        $emotionDistribution = $this->moodEntryRepository->findEmotionDistributionWithinRange($user, $fromDate, $toDate);
        $influenceDistribution = $this->moodEntryRepository->findInfluenceDistributionWithinRange($user, $fromDate, $toDate);
        $dayTypeCount = $this->moodEntryRepository->countTypeWithinRange($user, $fromDate, $toDate, 'DAY');
        $momentTypeCount = $this->moodEntryRepository->countTypeWithinRange($user, $fromDate, $toDate, 'MOMENT');
        $criticalStatus = (string) ($summary['criticalPeriod']['status'] ?? 'stable');
        $supportiveQuote = in_array($criticalStatus, ['warning', 'critical'], true)
            ? $this->zenQuotesClient->fetchRandomQuote()
            : null;

        return $this->render('user/pages/mood_insights.html.twig', [
            'nav' => $this->buildNav('user_ui_mood_insights'),
            'userName' => $user->getEmail(),
            'criticalPeriod' => $summary['criticalPeriod'],
            'resilienceScore' => $summary['resilienceScore'],
            'weeklyTrendReviewed' => $this->isWeeklyTrendReviewed($request),
            'emotionDistribution' => $emotionDistribution,
            'influenceDistribution' => $influenceDistribution,
            'entryTypeBalance' => [
                'day' => $dayTypeCount,
                'moment' => $momentTypeCount,
            ],
            'supportiveQuote' => $supportiveQuote,
        ]);
    }

    #[Route('/mood/insights/review-weekly-trend', name: 'user_ui_mood_insights_review_weekly_trend', methods: ['POST'])]
    public function reviewWeeklyTrend(Request $request): RedirectResponse
    {
        $this->currentUser();

        if (!$this->isCsrfTokenValid('mood_review_weekly_trend', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid review token.');

            return $this->redirectToRoute('user_ui_mood_insights');
        }

        if ($request->hasSession()) {
            $request->getSession()->set($this->weeklyTrendReviewKey(), true);
        }

        $this->addFlash('success', 'Weekly mood trend marked as reviewed.');

        return $this->redirectToRoute('user_ui_mood_insights');
    }

    #[Route('/mood/insights/rereview-weekly-trend', name: 'user_ui_mood_insights_rereview_weekly_trend', methods: ['POST'])]
    public function rereviewWeeklyTrend(Request $request): RedirectResponse
    {
        $this->currentUser();

        if (!$this->isCsrfTokenValid('mood_rereview_weekly_trend', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid re-review token.');

            return $this->redirectToRoute('user_ui_mood_insights');
        }

        if ($request->hasSession()) {
            $request->getSession()->remove($this->weeklyTrendReviewKey());
        }

        $this->addFlash('success', 'Weekly mood trend reset. You can review it again.');

        return $this->redirectToRoute('user_ui_mood_insights');
    }

    #[Route('/sleep', name: 'user_ui_sleep', methods: ['GET'])]
    public function sleep(): Response
    {
        $this->currentUser();

        return $this->redirectToRoute('app_sommeil_list');
    }

    private function isWeeklyTrendReviewed(Request $request): bool
    {
        if (!$request->hasSession()) {
            return false;
        }

        return (bool) $request->getSession()->get($this->weeklyTrendReviewKey(), false);
    }

    private function weeklyTrendReviewKey(): string
    {
        $weekStart = new \DateTimeImmutable('monday this week');

        return 'mood_weekly_trend_reviewed_' . $weekStart->format('Y-m-d');
    }
}
