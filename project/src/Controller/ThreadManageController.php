<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForumThread;
use App\Enum\ThreadStatus;
use App\Form\ForumThreadType;
use App\Service\ForumCurrentUserService;
use App\Service\ForumImageUploadService;
use App\Service\ForumRateLimitService;
use App\Service\InteractionService;
use App\Service\SpamRateLimiterService;
use App\Service\ThreadDuplicateRadarService;
use App\Service\ThreadService;
use App\Service\User\UserNavService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/threads')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ThreadManageController extends AbstractController
{
    #[Route('/new', name: 'app_thread_new')]
    public function new(
        Request $request,
        ThreadService $threadService,
        ForumCurrentUserService $currentUserService,
        ForumImageUploadService $uploadService,
        ThreadDuplicateRadarService $threadDuplicateRadarService,
        UserNavService $navService,
        SpamRateLimiterService $spamRateLimiterService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

       
 // Check if user is currently banned
        if ($spamRateLimiterService->isUserBannedForSpam($currentUser)) {
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $this->addFlash('danger', sprintf(
                'Your account is temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            ));

            return $this->redirectToRoute('app_forum_feed');
        }

        $threadError = null;
        $duplicateRadarResults = [];
        $thread = new ForumThread();
        $form = $this->createForm(ForumThreadType::class, $thread);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check spam rate limit before creating thread
            $rateLimit = $spamRateLimiterService->checkThreadCreationSpam($currentUser->getId());
            if (!$rateLimit->isAccepted()) {
                // Ban user for 12 hours due to spam
                $spamRateLimiterService->banUserForSpam($currentUser);
                $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
                $this->addFlash('danger', sprintf(
                    'You have been temporarily banned due to spam activity. Please try again in %s.',
                    $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
                ));

                return $this->redirectToRoute('app_forum_feed');
            }

            $forcePublish = $request->request->getBoolean('force_publish');

            if (!$forcePublish) {
                $duplicateRadarResults = $threadDuplicateRadarService->findNearDuplicates(
                    $currentUser->getId(),
                    (string) $thread->getTitle(),
                    (string) $thread->getContent(),
                );

                if ($duplicateRadarResults !== []) {
                    $this->addFlash('warning', 'Possible duplicate threads detected. Choose Continue, Merge, or Revive.');

                    return $this->render('thread/form.html.twig', [
                        'form' => $form,
                        'mode' => 'create',
                        'currentUser' => $currentUser,
                        'nav' => $navService->build('user_ui_forum'),
                        'userName' => $currentUser->getUsername(),
                        'threadError' => $threadError,
                        'duplicateRadarResults' => $duplicateRadarResults,
                    ]);
                }
            }

            $thread->setAuthorId($currentUser->getId());
            $image = $form->get('imageFile')->getData();
            $thread->setImageUrl($uploadService->upload($image));

            try {
                $threadService->saveThread($thread);

                return $this->redirectToRoute('app_forum_feed');
            } catch (\RuntimeException $exception) {
                $this->addFlash('danger', $exception->getMessage());
                $threadError = $exception->getMessage();
            }
        }

        return $this->render('thread/form.html.twig', [
            'form' => $form,
            'mode' => 'create',
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'threadError' => $threadError,
            'duplicateRadarResults' => $duplicateRadarResults,
        ]);
    }

    #[Route('/new/duplicate-radar', name: 'app_thread_duplicate_radar', methods: ['POST'])]
    public function duplicateRadar(Request $request, ForumCurrentUserService $currentUserService, ThreadDuplicateRadarService $threadDuplicateRadarService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->json([
                'ok' => false,
                'message' => 'Backoffice users do not use this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('duplicate_radar', (string) $request->request->get('_token'))) {
            return $this->json([
                'ok' => false,
                'message' => 'Invalid duplicate radar token.',
            ], Response::HTTP_FORBIDDEN);
        }

        $title = (string) $request->request->get('title', '');
        $content = (string) $request->request->get('content', '');

        $duplicates = $threadDuplicateRadarService->findNearDuplicates($currentUser->getId(), $title, $content);

        return $this->json([
            'ok' => true,
            'duplicates' => $duplicates,
        ]);
    }

    #[Route('/new/merge/{id}', name: 'app_thread_duplicate_merge', methods: ['POST'])]
    public function mergeDuplicate(ForumThread $thread, Request $request, ForumCurrentUserService $currentUserService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        if (!$this->isCsrfTokenValid('duplicate_flow', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid duplicate merge token.');
        }

        $this->storeReplyPrefill($request, $thread, (string) $request->request->get('draft_content', ''));
        $this->addFlash('warning', 'Draft moved to reply composer so you can merge into this thread.');

        return $this->redirectToRoute('app_forum_thread_detail', [
            'id' => $thread->getId(),
            'compose' => 1,
        ]);
    }

    #[Route('/new/revive/{id}', name: 'app_thread_duplicate_revive', methods: ['POST'])]
    public function reviveDuplicate(ForumThread $thread, Request $request, ForumCurrentUserService $currentUserService, ThreadService $threadService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        if (!$this->isCsrfTokenValid('duplicate_flow', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid duplicate revive token.');
        }

        if ($thread->getStatus() === ThreadStatus::ARCHIVED) {
            $threadService->updateStatus($thread, ThreadStatus::OPEN);
            $this->addFlash('warning', 'Archived thread revived and reopened.');
        }

        $this->storeReplyPrefill($request, $thread, (string) $request->request->get('draft_content', ''));
        $this->addFlash('warning', 'Draft moved to reply composer so you can revive this thread.');

        return $this->redirectToRoute('app_forum_thread_detail', [
            'id' => $thread->getId(),
            'compose' => 1,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_thread_edit', requirements: ['id' => '\\d+'])]
    public function edit(ForumThread $thread, Request $request, ThreadService $threadService, ForumCurrentUserService $currentUserService, UserNavService $navService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $threadError = null;
        $this->denyUnlessCanManage($thread, $threadService, $currentUserService);

        $form = $this->createForm(ForumThreadType::class, $thread);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $threadService->saveThread($thread);

                return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
            } catch (\RuntimeException $exception) {
                $this->addFlash('danger', $exception->getMessage());
                $threadError = $exception->getMessage();
            }
        }

        return $this->render('thread/form.html.twig', [
            'form' => $form,
            'mode' => 'edit',
            'thread' => $thread,
            'currentUser' => $currentUser,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $currentUser->getUsername(),
            'threadError' => $threadError,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_thread_delete', methods: ['POST'])]
    public function delete(ForumThread $thread, Request $request, ThreadService $threadService, ForumCurrentUserService $currentUserService): Response
    {
        if ($currentUserService->isBackofficeUser($currentUserService->requireUser())) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyUnlessCanManage($thread, $threadService, $currentUserService);

        if (!$this->isCsrfTokenValid('delete_thread_'.$thread->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid delete token.');
        }

        $threadService->deleteThread($thread);

        return $this->redirectToRoute('app_forum_feed');
    }

    #[Route('/{id}/status/{status}', name: 'app_thread_status')]
    public function status(ForumThread $thread, string $status, ThreadService $threadService, ForumCurrentUserService $currentUserService): Response
    {
        if ($currentUserService->isBackofficeUser($currentUserService->requireUser())) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyUnlessCanManage($thread, $threadService, $currentUserService);

        $allowedStatuses = [
            ThreadStatus::OPEN,
            ThreadStatus::LOCKED,
            ThreadStatus::ARCHIVED,
        ];

        $targetStatus = ThreadStatus::tryFrom($status);
        if ($targetStatus === null || !in_array($targetStatus, $allowedStatuses, true)) {
            throw $this->createNotFoundException('Invalid thread status action.');
        }

        $threadService->updateStatus($thread, $targetStatus);

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
    }

    #[Route('/{id}/pin', name: 'app_thread_pin')]
    public function pin(ForumThread $thread, ThreadService $threadService, ForumCurrentUserService $currentUserService): Response
    {
        if ($currentUserService->isBackofficeUser($currentUserService->requireUser())) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyUnlessCanManage($thread, $threadService, $currentUserService);
        $threadService->togglePin($thread);

        return $this->redirectToRoute('app_forum_feed');
    }

    #[Route('/{id}/upvote', name: 'app_thread_upvote')]
    public function upvote(
        ForumThread $thread,
        InteractionService $interactionService,
        ForumCurrentUserService $currentUserService,
        SpamRateLimiterService $spamRateLimiterService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        // Check if user is banned
        if ($spamRateLimiterService->isUserBannedForSpam($currentUser)) {
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $this->addFlash('danger', sprintf(
                'Your account is temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            ));

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        // Check spam rate limit for interactions
        $rateLimit = $spamRateLimiterService->checkInteractionSpam($currentUser->getId());
        if (!$rateLimit->isAccepted()) {
            // Ban user for 12 hours due to spam
            $spamRateLimiterService->banUserForSpam($currentUser);
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $this->addFlash('danger', sprintf(
                'You have been temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            ));

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        $interactionService->toggleUpvote($thread, $currentUser);

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
    }

    #[Route('/{id}/downvote', name: 'app_thread_downvote')]
    public function downvote(
        ForumThread $thread,
        InteractionService $interactionService,
        ForumCurrentUserService $currentUserService,
        SpamRateLimiterService $spamRateLimiterService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        // Check if user is banned
        if ($spamRateLimiterService->isUserBannedForSpam($currentUser)) {
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $this->addFlash('danger', sprintf(
                'Your account is temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            ));

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        // Check spam rate limit for interactions
        $rateLimit = $spamRateLimiterService->checkInteractionSpam($currentUser->getId());
        if (!$rateLimit->isAccepted()) {
            // Ban user for 12 hours due to spam
            $spamRateLimiterService->banUserForSpam($currentUser);
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $this->addFlash('danger', sprintf(
                'You have been temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            ));

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        $interactionService->toggleDownvote($thread, $currentUser);

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
    }

    #[Route('/{id}/follow', name: 'app_thread_follow')]
    public function follow(
        ForumThread $thread,
        Request $request,
        InteractionService $interactionService,
        ForumCurrentUserService $currentUserService,
        ForumRateLimitService $forumRateLimitService,
        SpamRateLimiterService $spamRateLimiterService,
    ): Response {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        // Check if user is banned for spam
        if ($spamRateLimiterService->isUserBannedForSpam($currentUser)) {
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $message = sprintf(
                'Your account is temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            );

            if ($this->expectsJson($request)) {
                return $this->json([
                    'ok' => false,
                    'banned' => true,
                    'message' => $message,
                    'remainingSeconds' => $remainingSeconds,
                ], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('danger', $message);

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        // Check spam rate limit for interactions (before the follow-specific limiter)
        $spamRateLimit = $spamRateLimiterService->checkInteractionSpam($currentUser->getId());
        if (!$spamRateLimit->isAccepted()) {
            // Ban user for 12 hours due to spam
            $spamRateLimiterService->banUserForSpam($currentUser);
            $remainingSeconds = $spamRateLimiterService->getRemainingBanSeconds($currentUser);
            $message = sprintf(
                'You have been temporarily banned due to spam activity. Please try again in %s.',
                $spamRateLimiterService->formatRemainingBanTime($remainingSeconds)
            );

            if ($this->expectsJson($request)) {
                return $this->json([
                    'ok' => false,
                    'banned' => true,
                    'message' => $message,
                    'remainingSeconds' => $remainingSeconds,
                ], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('danger', $message);

            return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
        }

        

        $interactionService->toggleFollow($thread, $currentUser);

        $isFollowing = $interactionService->getInteraction($thread, $currentUser)?->isFollow() ?? false;

        if ($this->expectsJson($request)) {
            return $this->json([
                'ok' => true,
                'limited' => false,
                'isFollowing' => $isFollowing,
            ]);
        }

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
    }

    private function expectsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }

    private function storeReplyPrefill(Request $request, ForumThread $thread, string $content): void
    {
        $text = trim($content);
        if ($text === '' || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set($this->replyPrefillKey((int) $thread->getId()), mb_substr($text, 0, 5000));
    }

    private function replyPrefillKey(int $threadId): string
    {
        return 'thread_reply_prefill_'.$threadId;
    }

    private function denyUnlessCanManage(ForumThread $thread, ThreadService $threadService, ForumCurrentUserService $currentUserService): void
    {
        if (!$threadService->canEdit($thread, $currentUserService->requireUser()->getId())) {
            throw $this->createAccessDeniedException('You cannot modify this thread.');
        }
    }
}