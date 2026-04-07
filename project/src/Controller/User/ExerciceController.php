<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\Exercice\CompleteControlRequest;
use App\Service\User\UserExerciceService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/user/exercises')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ExerciceController extends AbstractUserUiController
{
    public function __construct(
        private readonly UserExerciceService $userExerciceService,
    ) {
    }

    #[Route('', name: 'user_ui_exercises', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->currentUser();
        $catalogRows = $this->userExerciceService->catalog($user)->data['items'] ?? [];
        $summary = $this->userExerciceService->summary($user)->data;

        $search = mb_strtolower(trim((string) $request->query->get('q', '')));
        $type = trim((string) $request->query->get('type', ''));
        $level = trim((string) $request->query->get('level', ''));
        $sort = trim((string) $request->query->get('sort', 'title_asc'));

        $availableTypes = array_values(array_unique(array_map(
            static fn(array $item): string => (string) ($item['exercice']['type'] ?? ''),
            $catalogRows
        )));
        sort($availableTypes);
        $availableLevels = array_values(array_unique(array_map(
            static fn(array $item): int => (int) ($item['exercice']['level'] ?? 0),
            $catalogRows
        )));
        sort($availableLevels);

        $catalog = array_values(array_filter($catalogRows, static function (array $item) use ($search, $type, $level): bool {
            $title = mb_strtolower((string) ($item['exercice']['title'] ?? ''));
            $itemType = (string) ($item['exercice']['type'] ?? '');
            $itemLevel = (string) ($item['exercice']['level'] ?? '');

            if ($search !== '' && !str_contains($title, $search)) {
                return false;
            }
            if ($type !== '' && $itemType !== $type) {
                return false;
            }
            if ($level !== '' && $itemLevel !== $level) {
                return false;
            }

            return true;
        }));

        usort($catalog, static function (array $a, array $b) use ($sort): int {
            $titleA = (string) ($a['exercice']['title'] ?? '');
            $titleB = (string) ($b['exercice']['title'] ?? '');
            $durationA = (int) ($a['exercice']['durationMinutes'] ?? 0);
            $durationB = (int) ($b['exercice']['durationMinutes'] ?? 0);
            $levelA = (int) ($a['exercice']['level'] ?? 0);
            $levelB = (int) ($b['exercice']['level'] ?? 0);

            return match ($sort) {
                'title_desc' => strcasecmp($titleB, $titleA),
                'duration_asc' => $durationA <=> $durationB,
                'duration_desc' => $durationB <=> $durationA,
                'level_asc' => $levelA <=> $levelB,
                'level_desc' => $levelB <=> $levelA,
                default => strcasecmp($titleA, $titleB),
            };
        });

        return $this->render('user/pages/exercises.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'catalog' => $catalog,
            'availableTypes' => $availableTypes,
            'availableLevels' => $availableLevels,
            'filters' => [
                'q' => $search,
                'type' => $type,
                'level' => $level,
                'sort' => $sort,
            ],
            'summary' => $summary,
        ]);
    }

    #[Route('/sessions', name: 'user_ui_exercises_sessions', methods: ['GET'])]
    public function sessions(): Response
    {
        $user = $this->currentUser();
        $history = $this->userExerciceService->history($user)->data;
        $summary = $this->userExerciceService->summary($user)->data;

        return $this->render('user/pages/exercise_sessions_history.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'history' => $history['items'] ?? [],
            'summary' => $summary,
        ]);
    }

    #[Route('/session/{id}/start', name: 'user_ui_exercises_session_start', methods: ['GET'])]
    public function sessionStart(int $id): Response
    {
        $user = $this->currentUser();
        $result = $this->userExerciceService->startByExercice($user, $id);
        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->render('user/pages/exercise_session_start.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'session' => $result->data,
        ]);
    }

    #[Route('/session/{id}/finish', name: 'user_ui_exercises_session_finish', methods: ['POST'])]
    public function sessionFinish(int $id, Request $request, ValidatorInterface $validator): Response
    {
        $user = $this->currentUser();
        $dto = new CompleteControlRequest();
        $dto->feedback = trim((string) $request->request->get('feedback', '')) ?: null;
        $dto->activeSeconds = (int) $request->request->get('activeSeconds', 0);

        $violations = $validator->validate($dto);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                /** @var ConstraintViolationInterface $violation */
                $field = $violation->getPropertyPath();
                $this->addFlash('error', ($field !== '' ? $field . ': ' : '') . $violation->getMessage());
            }

            return $this->redirectToRoute('user_ui_exercises');
        }

        $result = $this->userExerciceService->complete($user, $id, $dto->feedback, $dto->activeSeconds);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('user_ui_exercises_sessions');
    }

    #[Route('/{id}/start', name: 'user_ui_exercises_start', methods: ['POST'])]
    public function startLegacy(int $id): Response
    {
        $user = $this->currentUser();
        $result = $this->userExerciceService->start($user, $id);
        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->render('user/pages/exercise_session_start.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'session' => $result->data,
        ]);
    }

    #[Route('/{id}/complete', name: 'user_ui_exercises_complete', methods: ['POST'])]
    public function completeLegacy(int $id, Request $request, ValidatorInterface $validator): Response
    {
        return $this->sessionFinish($id, $request, $validator);
    }

    #[Route('/favorite', name: 'user_ui_exercises_favorite', methods: ['POST'])]
    public function favorite(Request $request): Response
    {
        $user = $this->currentUser();
        $favoriteType = (string) $request->request->get('favoriteType', 'EXERCICE');
        $itemId = (int) $request->request->get('itemId', 0);
        $result = $this->userExerciceService->toggleFavorite($user, $favoriteType, $itemId);
        $this->addFlash($result->success ? 'success' : 'error', $result->message);

        return $this->redirectToRoute('user_ui_exercises');
    }

    #[Route('/{id}', name: 'user_ui_exercises_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->currentUser();
        $catalogRows = $this->userExerciceService->catalog($user)->data['items'] ?? [];
        $selected = null;
        foreach ($catalogRows as $item) {
            if ((int) ($item['exercice']['id'] ?? 0) === $id) {
                $selected = $item;
                break;
            }
        }

        if ($selected === null) {
            $this->addFlash('error', 'Exercice not found.');

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->render('user/pages/exercise_show.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'item' => $selected,
        ]);
    }
}
