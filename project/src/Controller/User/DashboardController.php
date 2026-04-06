<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Service\User\UserDashboardService;
use App\Service\User\UserMoodService;
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
    public function consultations(): Response
    {
        $user = $this->currentUser();

        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('user_ui_consultations'),
            'userName' => $user->getEmail(),
            'title' => 'Consultations',
            'subtitle' => 'User consultations module will be available soon.',
        ]);
    }

    #[Route('/exercises', name: 'user_ui_exercises', methods: ['GET'])]
    public function exercises(): Response
    {
        $user = $this->currentUser();

        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'title' => 'Exercises',
            'subtitle' => 'User exercises module will be available soon.',
        ]);
    }

    #[Route('/forum', name: 'user_ui_forum', methods: ['GET'])]
    public function forum(): Response
    {
        $user = $this->currentUser();

        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('user_ui_forum'),
            'userName' => $user->getEmail(),
            'title' => 'Forum',
            'subtitle' => 'User forum module will be available soon.',
        ]);
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

    #[Route('/sleep', name: 'user_ui_sleep', methods: ['GET'])]
    public function sleep(): Response
    {
        $user = $this->currentUser();

        return $this->render('access_control/pages/coming_soon.html.twig', [
            'nav' => $this->buildNav('user_ui_sleep'),
            'userName' => $user->getEmail(),
            'title' => 'Sleep',
            'subtitle' => 'Sleep tracking module will be available soon.',
        ]);
    }
}
