<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Service\User\CoachChatService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/exercises/coach')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CoachChatController extends AbstractUserUiController
{
    private const SESSION_COACH_REPORT_KEY = 'serinity.latest_coach_report';

    public function __construct(private readonly CoachChatService $coachChatService)
    {
    }

    #[Route('/chat', name: 'user_exercise_coach_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $message = is_array($payload) && is_string($payload['message'] ?? null) ? trim($payload['message']) : '';

        if ($message === '') {
            return $this->json([
                'reply' => 'Please enter a question for your coach assistant.',
                'source' => 'local_fallback',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $coachContext = $request->getSession()->get(self::SESSION_COACH_REPORT_KEY);
        $reply = $this->coachChatService->reply(
            $this->currentUser(),
            mb_substr($message, 0, 600),
            is_array($coachContext) ? $coachContext : null,
        );

        return $this->json($reply);
    }
}
