<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\Exercice\CompleteControlRequest;
use App\Service\AmbientSoundService;
use App\Service\QuoteService;
use App\Service\User\ContextAwarePlanner;
use App\Service\User\FatigueResolver;
use App\Service\User\YouTubeRecommendationService;
use App\Service\User\UserExerciceService;
use App\Service\WeatherService;
use CMEN\GoogleChartsBundle\GoogleCharts\Charts\PieChart;
use Knp\Component\Pager\PaginatorInterface;
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
        private readonly AmbientSoundService $ambientSoundService,
        private readonly QuoteService $quoteService,
        private readonly WeatherService $weatherService,
        private readonly ContextAwarePlanner $contextAwarePlanner,
        private readonly FatigueResolver $fatigueResolver,
        private readonly YouTubeRecommendationService $youTubeRecommendationService,
        private readonly PaginatorInterface $paginator,
        private readonly float $defaultLatitude,
        private readonly float $defaultLongitude,
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
        $fatigue = $this->fatigueResolver->resolve($request->query->get('fatigue'));

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

        $quote = $this->quoteService->getRandomQuote();
        $weather = $this->weatherService->getCurrentWeather($this->defaultLatitude, $this->defaultLongitude);
        $plan = $this->contextAwarePlanner->build($weather, $catalogRows, $fatigue);
        $plan['recommendation']['actionUrl'] = ($plan['recommendation']['exerciseId'] ?? null) !== null
            ? $this->generateUrl('user_ui_exercises_session_start', [
                'id' => $plan['recommendation']['exerciseId'],
                'fatigue' => $fatigue,
            ])
            : $this->generateUrl('user_ui_exercises_sessions');
        $plan['videos'] = $this->youTubeRecommendationService->recommend($plan['youtubeQuery']);

        return $this->render('user/pages/exercises.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'catalog' => $this->paginator->paginate($catalog, max(1, $request->query->getInt('page', 1)), 9),
            'availableTypes' => $availableTypes,
            'availableLevels' => $availableLevels,
            'filters' => [
                'q' => $search,
                'type' => $type,
                'level' => $level,
                'sort' => $sort,
                'fatigue' => $fatigue,
            ],
            'summary' => $summary,
            'quote' => $quote,
            'weather' => $weather,
            'plan' => $plan,
            'fatigueOptions' => $this->fatigueResolver->options(),
            'progressDistributionChart' => $this->buildProgressDistributionChart($summary),
            'completionRateChart' => $this->buildCompletionRateChart($summary),
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

    #[Route('/session/{id}/start', name: 'user_ui_exercises_session_start', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function sessionStart(int $id, Request $request): Response
    {
        $user = $this->currentUser();
        $result = $this->userExerciceService->startByExercice($user, $id);
        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->renderSessionPage($user->getEmail(), $result->data, $this->fatigueResolver->resolve($request->query->get('fatigue')));
    }

    #[Route('/session/{id}/finish', name: 'user_ui_exercises_session_finish', requirements: ['id' => '\d+'], methods: ['POST'])]
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

    #[Route('/{id}/start', name: 'user_ui_exercises_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function startLegacy(int $id, Request $request): Response
    {
        $user = $this->currentUser();
        $result = $this->userExerciceService->start($user, $id);
        if (!$result->success) {
            $this->addFlash('error', $result->message);

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->renderSessionPage($user->getEmail(), $result->data, $this->fatigueResolver->resolve($request->query->get('fatigue')));
    }

    #[Route('/{id}/complete', name: 'user_ui_exercises_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
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

    #[Route('/{id}', name: 'user_ui_exercises_show', requirements: ['id' => '\d+'], methods: ['GET'])]
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
            $this->addFlash('error', 'Exercise not found.');

            return $this->redirectToRoute('user_ui_exercises');
        }

        return $this->render('user/pages/exercise_show.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $user->getEmail(),
            'item' => $selected,
        ]);
    }

    /**
     * @param array<string,mixed> $sessionData
     */
    private function renderSessionPage(string $userName, array $sessionData, string $fatigue): Response
    {
        $weather = $this->weatherService->getCurrentWeather($this->defaultLatitude, $this->defaultLongitude);
        $moment = $this->resolveMoment((string) ($weather['localTime'] ?? '12:00'));
        $ambientSound = $this->ambientSoundService->getAmbientSound([
            'moment' => $moment,
            'fatigue' => $fatigue,
            'exerciseType' => (string) ($sessionData['exercice']['type'] ?? ''),
            'recommendationType' => (string) ($sessionData['exercice']['type'] ?? ''),
            'weatherLabel' => (string) ($weather['weatherLabel'] ?? ''),
        ]);

        return $this->render('user/pages/exercise_session_start.html.twig', [
            'nav' => $this->buildNav('user_ui_exercises'),
            'userName' => $userName,
            'session' => $sessionData,
            'audioUrl' => (string) ($ambientSound['audioUrl'] ?? ''),
            'audioTitle' => (string) ($ambientSound['title'] ?? ''),
            'audioType' => (string) ($ambientSound['type'] ?? ''),
            'audioMood' => ucfirst($moment),
        ]);
    }

    private function resolveMoment(string $localTime): string
    {
        $hour = (int) strtok($localTime, ':');

        return match (true) {
            $hour >= 21 || $hour < 5 => 'night',
            $hour >= 18 => 'evening',
            $hour >= 12 => 'afternoon',
            default => 'morning',
        };
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function buildProgressDistributionChart(array $summary): PieChart
    {
        $rows = [];
        foreach ([
            'Assigned' => (int) ($summary['assigned'] ?? 0),
            'In progress' => (int) ($summary['inProgress'] ?? 0),
            'Completed' => (int) ($summary['completed'] ?? 0),
        ] as $label => $count) {
            if ($count > 0) {
                $rows[] = [$label, $count];
            }
        }

        $chart = new PieChart();
        $chart->getData()->setArrayToDataTable([
            ['Status', 'Sessions'],
            ...($rows !== [] ? $rows : [['No sessions yet', 0]]),
        ]);
        $chart->getOptions()
            ->setTitle('Session status')
            ->setHeight(320)
            ->setWidth(520)
            ->setPieHole(0.45)
            ->setPieSliceText('value')
            ->setColors(['#cfe1df', '#88bdbc', '#2f6f6d']);
        $chart->getOptions()->getLegend()->setPosition('bottom');

        return $chart;
    }

    /**
     * @param array<string,mixed> $summary
     */
    private function buildCompletionRateChart(array $summary): PieChart
    {
        $completionRate = max(0.0, min(100.0, (float) ($summary['completionRate'] ?? 0.0)));
        $remainingRate = max(0.0, 100.0 - $completionRate);

        $chart = new PieChart();
        $chart->getData()->setArrayToDataTable([
            ['Progress', 'Percent'],
            ['Completed', $completionRate],
            ['Remaining', $remainingRate],
        ]);
        $chart->getOptions()
            ->setTitle('Completion rate')
            ->setHeight(320)
            ->setWidth(520)
            ->setPieHole(0.62)
            ->setPieSliceText('percentage')
            ->setColors(['#2f6f6d', '#e4eeee']);
        $chart->getOptions()->getLegend()->setPosition('bottom');

        return $chart;
    }
}
