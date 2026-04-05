<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Service\User\UserDashboardService;
use App\Service\User\UserProfileService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SettingsController extends AbstractUserUiController
{
    public function __construct(
        private readonly UserDashboardService $userDashboardService,
        private readonly UserProfileService $userProfileService,
    ) {
    }

    #[Route('/settings', name: 'user_ui_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $user = $this->currentUser();
        $current = $this->userDashboardService->decodeSettings((string) $request->cookies->get('user_settings', ''));

        if ($request->isMethod('POST')) {
            $updated = $this->userDashboardService->sanitizeSettings([
                'theme' => (string) $request->request->get('theme', $current['theme']),
                'notifications' => (bool) $request->request->get('notifications', false),
                'compactView' => (bool) $request->request->get('compactView', false),
            ]);
            $encoded = $this->userDashboardService->encodeSettings($updated);

            $response = $this->redirectToRoute('user_ui_settings');
            $response->headers->setCookie(new Cookie(
                'user_settings',
                $encoded,
                time() + 31536000,
                '/',
                null,
                false,
                false,
                false,
                'lax',
            ));
            $this->addFlash('success', 'Settings updated successfully.');

            return $response;
        }

        return $this->render('user/pages/settings.html.twig', [
            'nav' => $this->buildNav('user_ui_settings'),
            'userName' => $user->getEmail(),
            'settings' => $current,
        ]);
    }

    #[Route('/settings/delete-account', name: 'user_ui_settings_delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request): Response
    {
        $user = $this->currentUser();
        $result = $this->userProfileService->deleteAccount(
            $user,
            trim((string) $request->request->get('currentPassword', '')),
            trim((string) $request->request->get('confirmDelete', '')),
        );

        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_settings');
        }

        $response = $this->redirectToRoute('home');
        $response->headers->clearCookie('access_token', '/');
        $response->headers->clearCookie('refresh_token', '/');
        $response->headers->clearCookie('jwt', '/');
        $response->headers->clearCookie('user_settings', '/');

        return $response;
    }

    #[Route('/settings/change-password', name: 'user_ui_settings_change_password', methods: ['POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->currentUser();
        $result = $this->userProfileService->changePassword(
            $user,
            trim((string) $request->request->get('currentPassword', '')),
            trim((string) $request->request->get('newPassword', '')),
            trim((string) $request->request->get('confirmPassword', '')),
        );

        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_settings');
        }

        $this->addFlash('success', $result->message);

        return $this->redirectToRoute('user_ui_settings');
    }
}
