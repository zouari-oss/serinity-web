<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\ForumCurrentUserService;
use App\Service\NotificationService;
use App\Service\User\UserNavService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications')]
    public function index(ForumCurrentUserService $currentUserService, NotificationRepository $notificationRepository, UserNavService $navService): Response
    {
        $user = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($user)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        return $this->render('notification/index.html.twig', [
            'notifications' => $notificationRepository->findForUser($user->getId()),
            'unreadCount' => $notificationRepository->countUnread($user->getId()),
            'currentUser' => $user,
            'nav' => $navService->build('user_ui_forum'),
            'userName' => $user->getUsername(),
        ]);
    }

    #[Route('/widget', name: 'app_notifications_widget')]
    public function widget(ForumCurrentUserService $currentUserService, NotificationRepository $notificationRepository): Response
    {
        $user = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($user)) {
            return new Response('');
        }

        return $this->render('notification/_floating_widget.html.twig', [
            'notifications' => $notificationRepository->findForUser($user->getId()),
            'unreadCount' => $notificationRepository->countUnread($user->getId()),
        ]);
    }

    #[Route('/{id}/seen', name: 'app_notification_seen')]
    public function seen(Notification $notification, NotificationService $notificationService, ForumCurrentUserService $currentUserService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyAccessUnlessOwner($notification, $currentUser->getId());
        $notificationService->markAsSeen($notification);

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/{id}/open', name: 'app_notification_open')]
    public function open(Notification $notification, NotificationService $notificationService, ForumCurrentUserService $currentUserService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyAccessUnlessOwner($notification, $currentUser->getId());
        $thread = $notification->getThread();

        $notificationService->markAsSeen($notification);

        if ($thread === null) {
            return $this->redirectToRoute('app_forum_feed');
        }

        return $this->redirectToRoute('app_forum_thread_detail', ['id' => $thread->getId()]);
    }

    #[Route('/seen-all', name: 'app_notification_seen_all')]
    public function seenAll(Request $request, ForumCurrentUserService $currentUserService, NotificationService $notificationService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $notificationService->markAllAsSeen($currentUser->getId());

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_forum_feed');
    }

    #[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(Notification $notification, EntityManagerInterface $entityManager, ForumCurrentUserService $currentUserService): Response
    {
        $currentUser = $currentUserService->requireUser();
        if ($currentUserService->isBackofficeUser($currentUser)) {
            return $this->redirectToRoute('app_admin_forum');
        }

        $this->denyAccessUnlessOwner($notification, $currentUser->getId());
        $entityManager->remove($notification);
        $entityManager->flush();

        return $this->redirectToRoute('app_notifications');
    }

    private function denyAccessUnlessOwner(Notification $notification, ?string $currentUserId): void
    {
        $recipientMatches = $notification->getRecipientId() === $currentUserId;
        $threadOwnerMatches = $notification->getThread()?->getAuthorId() === $currentUserId;

        if (!$recipientMatches || !$threadOwnerMatches) {
            throw $this->createAccessDeniedException();
        }
    }
}
