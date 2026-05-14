<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForumThread;
use App\Entity\Reply;
use App\Enum\ThreadStatus;
use App\Enum\ThreadType;
use App\Form\ReplyType;
use App\Repository\CategoryRepository;
use App\Repository\ForumThreadRepository;
use App\Repository\PostInteractionRepository;
use App\Repository\ReplyRepository;
use App\Service\ForumCurrentUserService;
use App\Service\InteractionService;
use App\Service\PdfExportService;
use App\Service\ProfileLookupService;
use App\Service\ReplyService;
use App\Service\SpamRateLimiterService;
use App\Service\SummarizationService;
use App\Service\ThreadService;
use App\Service\ThreadSuggestionService;
use App\Service\TranslationService;
use App\Service\User\UserNavService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forum')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ForumController extends AbstractController
{
    #[Route('', name: 'app_forum_feed')]
    public function feed(
        Request $request,
        ThreadService $threadService,
        CategoryRepository $categoryRepository,
        ForumCurrentUserService $currentUserService,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $baseThreads = $threadService->feed(['excludeArchived' => true]);
        $this->hydrateThreadAuthors($baseThreads, $profileLookupService);
        $threads = $this->applyThreadFilters($baseThreads, $request);

        return $this->render('forum/feed.html.twig', [
            'threads' => $threads,
            'categories' => $categoryRepository->findAll(),
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'currentSort' => $this->readSort($request),
            'activeStatuses' => $this->readStatuses($request),
            'activeTypes' => $this->readTypes($request),
            'activeCategories' => $this->readCategoryIds($request),
        ]);
    }

    #[Route('/my', name: 'app_forum_my_threads')]
    public function myThreads(
        ForumCurrentUserService $currentUserService,
        ThreadService $threadService,
        CategoryRepository $categoryRepository,
        Request $request,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $baseThreads = $threadService->feed([
            'authorId' => $currentUser->getId(),
            'excludeArchived' => true,
        ]);
        $this->hydrateThreadAuthors($baseThreads, $profileLookupService);
        $threads = $this->applyThreadFilters($baseThreads, $request);

        return $this->render('forum/feed.html.twig', [
            'threads' => $threads,
            'categories' => $categoryRepository->findAll(),
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'currentSort' => $this->readSort($request),
            'activeStatuses' => $this->readStatuses($request),
            'activeTypes' => $this->readTypes($request),
            'activeCategories' => $this->readCategoryIds($request),
        ]);
    }

    #[Route('/followed', name: 'app_forum_followed')]
    public function followed(
        ForumCurrentUserService $currentUserService,
        PostInteractionRepository $interactionRepository,
        CategoryRepository $categoryRepository,
        Request $request,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $baseThreads = $interactionRepository->findFollowedThreadsForUser($currentUser->getId());
        $this->hydrateThreadAuthors($baseThreads, $profileLookupService);
        $threads = $this->applyThreadFilters($baseThreads, $request);

        return $this->render('forum/feed.html.twig', [
            'threads' => $threads,
            'categories' => $categoryRepository->findAll(),
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'currentSort' => $this->readSort($request),
            'activeStatuses' => $this->readStatuses($request),
            'activeTypes' => $this->readTypes($request),
            'activeCategories' => $this->readCategoryIds($request),
        ]);
    }

    #[Route('/archived', name: 'app_forum_archived')]
    public function archived(
        ForumThreadRepository $threadRepository,
        ForumCurrentUserService $currentUserService,
        CategoryRepository $categoryRepository,
        Request $request,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $baseThreads = $threadRepository->findBy([
            'status' => ThreadStatus::ARCHIVED,
            'authorId' => $currentUser->getId(),
        ], ['createdAt' => 'DESC']);
        $this->hydrateThreadAuthors($baseThreads, $profileLookupService);
        $threads = $this->applyThreadFilters($baseThreads, $request);

        return $this->render('forum/feed.html.twig', [
            'threads' => $threads,
            'categories' => $categoryRepository->findAll(),
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'currentSort' => $this->readSort($request),
            'activeStatuses' => $this->readStatuses($request),
            'activeTypes' => $this->readTypes($request),
            'activeCategories' => $this->readCategoryIds($request),
        ]);
    }

    #[Route('/suggested', name: 'app_forum_suggested_thread')]
    public function suggested(
        ForumCurrentUserService $currentUserService,
        ThreadSuggestionService $threadSuggestionService,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $result = $threadSuggestionService->buildSuggestion($currentUser->getId());
        $thread = $result['thread'];

        if ($thread instanceof ForumThread) {
            $this->hydrateThreadAuthors([$thread], $profileLookupService);
        }

        $positiveSignals = array_values(array_filter(
            $result['categoryScores'],
            static fn (array $row): bool => (int) ($row['score'] ?? 0) > 0
        ));

        return $this->render('forum/suggested_thread.html.twig', [
            'suggestedThread' => $thread,
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'hasCategorySignals' => $positiveSignals !== [],
            'signalCategoryCount' => count($positiveSignals),
        ]);
    }

    #[Route('/thread/{id}', name: 'app_forum_thread_detail', requirements: ['id' => '\\d+'])]
    public function detail(
        ForumThread $thread,
        Request $request,
        ReplyService $replyService,
        ForumCurrentUserService $currentUserService,
        ReplyRepository $replyRepository,
        InteractionService $interactionService,
        SummarizationService $summarizationService,
        TranslationService $translationService,
        ThreadService $threadService,
        ProfileLookupService $profileLookupService,
        UserNavService $navService,
        SpamRateLimiterService $spamRateLimiterService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

       

        $reply = new Reply();
        $prefillKey = 'thread_reply_prefill_'.(int) $thread->getId();
        if ($request->hasSession()) {
            $prefillReply = (string) $request->getSession()->get($prefillKey, '');
            if ($prefillReply !== '') {
                $reply->setContent($prefillReply);
                $request->getSession()->remove($prefillKey);
            }
        }

        $form = $this->createForm(ReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($thread->getStatus() === ThreadStatus::LOCKED) {
                throw $this->createAccessDeniedException('Cannot add replies to a locked thread.');
            }

            // Check spam rate limit for replies before creating reply
            $rateLimit = $spamRateLimiterService->checkReplyCreationSpam($currentUser->getId());
            if (!$rateLimit->isAccepted()) {
                $spamRateLimiterService->banUserForSpam($currentUser);
                $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
                $this->addFlash('danger', sprintf(
                    'You have been temporarily banned due to spam activity. Please try again in %s.',
                    $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
                ));

                return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
            }

            $parentIdRaw = $form->get('parentId')->getData();
            if (is_string($parentIdRaw) && ctype_digit($parentIdRaw)) {
                $parentReply = $replyRepository->find((int) $parentIdRaw);
                if ($parentReply instanceof Reply && $parentReply->getThread()?->getId() === $thread->getId()) {
                    $reply->setParent($parentReply);
                }
            }

            $reply->setThread($thread);
            $reply->setAuthorId($currentUser->getId());

            try {
                $replyService->add($reply, $currentUser);
            } catch (\RuntimeException $exception) {
                if ($request->hasSession()) {
                    $request->getSession()->set($prefillKey, (string) $reply->getContent());
                }
                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
            }

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        $summary = null;
        if ($request->query->getBoolean('summarize')) {
            $summary = $summarizationService->summarize((string) $thread->getContent());
        }

        $translated = null;
        if ($request->query->has('lang')) {
            try {
                $translated = $translationService->translate((string) $thread->getContent(), (string) $request->query->get('lang'));

                if (trim((string) $translated) === trim((string) $thread->getContent())) {
                    $this->addFlash('warning', 'Translation returned the same content — translation may be unavailable.');
                    $translated = null;
                }
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Translation failed: ' . $e->getMessage());
                $translated = null;
            }
        }

        $currentInteraction = $interactionService->getInteraction($thread, $currentUser);
        $currentVote = $currentInteraction?->getVote() ?? 0;
        $isFollowing = $currentInteraction?->isFollow() ?? false;

        $this->hydrateThreadAuthors([$thread], $profileLookupService);
        $replies = $replyRepository->findTopLevelByThread($thread);
        $this->hydrateReplyAuthors($replies, $profileLookupService);

        return $this->render('forum/thread_detail.html.twig', [
            'thread' => $thread,
            'replyForm' => $form,
            'replies' => $replies,
            'summary' => $summary,
            'translated' => $translated,
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'canManageThread' => $threadService->canEdit($thread, $currentUser->getId()),
            'currentVote' => $currentVote,
            'isFollowing' => $isFollowing,
        ]);
    }

    #[Route('/thread/{id}/export', name: 'app_forum_thread_export', requirements: ['id' => '\\d+'])]
    public function export(
        ForumThread $thread,
        ReplyRepository $replyRepository,
        ForumCurrentUserService $currentUserService,
        PdfExportService $pdfExportService,
        ThreadService $threadService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $replies = $replyRepository->findTopLevelByThread($thread);
        $html = $pdfExportService->exportThreadHtml($thread, $replies);

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $output = $dompdf->output();

            return new Response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="thread-%d.pdf"', $thread->getId()),
            ]);
        }

        return new Response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => sprintf('attachment; filename="thread-%d.html"', $thread->getId()),
        ]);
    }

    /**
     * @param ForumThread[] $threads
     *
     * @return ForumThread[]
     */
    protected function applyThreadFilters(array $threads, Request $request): array
    {
        $pinned = array_values(array_filter($threads, static fn (ForumThread $t): bool => $t->isPinned()));
        $filtered = array_values(array_filter($threads, static fn (ForumThread $t): bool => !$t->isPinned()));

        $search = trim((string) $request->query->get('q', ''));
        if ($search !== '') {
            $lowerSearch = mb_strtolower($search);
            $filtered = array_values(array_filter($filtered, static fn (ForumThread $t): bool => str_contains(mb_strtolower((string) $t->getTitle()), $lowerSearch)));
        }

        $statuses = $this->readStatuses($request);
        if ($statuses !== []) {
            $allowedStatuses = array_map(static fn (string $s) => ThreadStatus::from($s), $statuses);
            $filtered = array_values(array_filter($filtered, static fn (ForumThread $t): bool => in_array($t->getStatus(), $allowedStatuses, true)));
        }

        $types = $this->readTypes($request);
        if ($types !== []) {
            $allowedTypes = array_map(static fn (string $t) => ThreadType::from($t), $types);
            $filtered = array_values(array_filter($filtered, static fn (ForumThread $t): bool => in_array($t->getType(), $allowedTypes, true)));
        }

        $categoryIds = $this->readCategoryIds($request);
        if ($categoryIds !== []) {
            $allowed = array_flip($categoryIds);
            $filtered = array_values(array_filter($filtered, static fn (ForumThread $t): bool => isset($allowed[$t->getCategory()?->getId() ?? 0])));
        }

        usort($filtered, fn (ForumThread $a, ForumThread $b): int => $this->compareThreads($a, $b, $this->readSort($request)));
        usort($pinned, static fn (ForumThread $a, ForumThread $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return array_merge($pinned, $filtered);
    }

    protected function compareThreads(ForumThread $a, ForumThread $b, string $sort): int
    {
        return match ($sort) {
            'oldest' => $a->getCreatedAt() <=> $b->getCreatedAt(),
            'most_points' => ($b->getLikeCount() - $b->getDislikeCount()) <=> ($a->getLikeCount() - $a->getDislikeCount()),
            'least_points' => ($a->getLikeCount() - $a->getDislikeCount()) <=> ($b->getLikeCount() - $b->getDislikeCount()),
            'most_comments' => $b->getReplyCount() <=> $a->getReplyCount(),
            'least_comments' => $a->getReplyCount() <=> $b->getReplyCount(),
            'most_followers' => $b->getFollowCount() <=> $a->getFollowCount(),
            'least_followers' => $a->getFollowCount() <=> $b->getFollowCount(),
            default => $b->getCreatedAt() <=> $a->getCreatedAt(),
        };
    }

    /**
     * @return list<string>
     */
    protected function readStatuses(Request $request): array
    {
        $allowed = ['open', 'locked'];
        $raw = $request->query->all('status');
        $statuses = array_values(array_filter(array_map('strval', is_array($raw) ? $raw : []), static fn (string $s): bool => in_array($s, $allowed, true)));

        return array_values(array_unique($statuses));
    }

    /**
     * @return list<string>
     */
    protected function readTypes(Request $request): array
    {
        $allowed = ['discussion', 'question', 'announcement'];
        $raw = $request->query->all('type');
        $types = array_values(array_filter(array_map('strval', is_array($raw) ? $raw : []), static fn (string $t): bool => in_array($t, $allowed, true)));

        return array_values(array_unique($types));
    }

    /**
     * @return list<int>
     */
    protected function readCategoryIds(Request $request): array
    {
        $raw = $request->query->all('category');
        $ids = [];

        foreach (is_array($raw) ? $raw : [] as $item) {
            $value = (string) $item;
            if (ctype_digit($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }

    protected function readSort(Request $request): string
    {
        $allowed = ['newest', 'oldest', 'most_points', 'least_points', 'most_comments', 'least_comments', 'most_followers', 'least_followers'];
        $value = (string) $request->query->get('sort', 'most_followers');

        return in_array($value, $allowed, true) ? $value : 'most_followers';
    }

    #[Route('/reply/{id}/edit', name: 'app_reply_edit', methods: ['POST'])]
    public function editReply(Reply $reply, Request $request, ForumCurrentUserService $currentUserService, ReplyService $replyService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        if (!$replyService->canManage($reply, $currentUser->getId())) {
            throw $this->createAccessDeniedException('You cannot edit this reply.');
        }

        if (!$this->isCsrfTokenValid('edit_reply_'.$reply->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid edit token.');
        }

        $replyService->update($reply, (string) $request->request->get('content', ''));

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $reply->getThread()?->getId()]);
    }

    #[Route('/reply/{id}/delete', name: 'app_reply_delete', methods: ['POST'])]
    public function deleteReply(Reply $reply, Request $request, ForumCurrentUserService $currentUserService, ReplyService $replyService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isAdmin($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        if (!$replyService->canManage($reply, $currentUser->getId())) {
            throw $this->createAccessDeniedException('You cannot delete this reply.');
        }

        if (!$this->isCsrfTokenValid('delete_reply_'.$reply->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid delete token.');
        }

        $threadId = $reply->getThread()?->getId();
        $replyService->delete($reply);

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $threadId]);
    }

    /**
     * @param list<ForumThread> $threads
     */
    private function hydrateThreadAuthors(array $threads, ProfileLookupService $profileLookupService): void
    {
        $ids = [];
        foreach ($threads as $thread) {
            $ids[] = $thread->getAuthorId() ?? '';
        }

        $usernames = $profileLookupService->usernamesByIds($ids);

        foreach ($threads as $thread) {
            $authorId = $thread->getAuthorId() ?? '';
            $thread->setAuthorUsername($usernames[$authorId] ?? 'Unknown User');
        }
    }

    /**
     * @param list<Reply> $replies
     */
    private function hydrateReplyAuthors(array $replies, ProfileLookupService $profileLookupService): void
    {
        $ids = [];
        $stack = $replies;

        while ($stack !== []) {
            $reply = array_pop($stack);
            if (!$reply instanceof Reply) {
                continue;
            }

            $ids[] = $reply->getAuthorId() ?? '';
            foreach ($reply->getChildren() as $child) {
                $stack[] = $child;
            }
        }

        $usernames = $profileLookupService->usernamesByIds($ids);
        $stack = $replies;

        while ($stack !== []) {
            $reply = array_pop($stack);
            if (!$reply instanceof Reply) {
                continue;
            }

            $authorId = $reply->getAuthorId() ?? '';
            $reply->setAuthorUsername($usernames[$authorId] ?? 'Unknown User');

            foreach ($reply->getChildren() as $child) {
                $stack[] = $child;
            }
        }
    }
}

