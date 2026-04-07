<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Dto\Common\ServiceResult;
use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\ExerciceFavorite;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceFavoriteRepository;
use App\Repository\ExerciceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserExerciceService
{
    public function __construct(
        private ExerciceRepository $exerciceRepository,
        private ExerciceControlRepository $controlRepository,
        private ExerciceFavoriteRepository $favoriteRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function catalog(User $user): ServiceResult
    {
        $exercices = $this->exerciceRepository->findCatalog(active: true);
        $controls = $this->controlRepository->findAssignedForUser($user);
        $controlsByExercice = [];
        foreach ($controls as $control) {
            $exerciceId = (int) $control->getExercice()->getId();
            if ($exerciceId <= 0) {
                continue;
            }
            $existing = $controlsByExercice[$exerciceId] ?? null;
            if (!$existing instanceof ExerciceControl || $control->getCreatedAt() > $existing->getCreatedAt()) {
                $controlsByExercice[$exerciceId] = $control;
            }
        }

        $favorites = $this->favoriteRepository->findForUser($user);
        $favoriteMap = [];
        foreach ($favorites as $favorite) {
            $favoriteMap[$favorite->getFavoriteType() . ':' . $favorite->getItemId()] = true;
        }

        $items = [];
        foreach ($exercices as $exercice) {
            $items[] = $this->toCatalogItem($exercice, $controlsByExercice[(int) $exercice->getId()] ?? null, $favoriteMap);
        }

        return ServiceResult::success('Exercice catalogue fetched successfully.', ['items' => $items]);
    }

    public function assigned(User $user): ServiceResult
    {
        $controls = $this->controlRepository->findAssignedForUser($user);
        $favorites = $this->favoriteRepository->findForUser($user);
        $favoriteMap = [];
        foreach ($favorites as $favorite) {
            $favoriteMap[$favorite->getFavoriteType() . ':' . $favorite->getItemId()] = true;
        }

        return ServiceResult::success('Assigned exercices fetched successfully.', [
            'items' => array_map(fn(ExerciceControl $control): array => $this->toControlArray($control, $favoriteMap), $controls),
        ]);
    }

    public function start(User $user, int $controlId): ServiceResult
    {
        $control = $this->controlRepository->findOneOwnedByUser($user, $controlId);
        if (!$control instanceof ExerciceControl) {
            return ServiceResult::failure('Assigned exercice not found.');
        }
        if ($control->getStatus() === ExerciceControl::STATUS_COMPLETED) {
            return ServiceResult::failure('Completed exercice cannot be restarted.');
        }

        $now = new \DateTimeImmutable();
        $control->setStatus(ExerciceControl::STATUS_IN_PROGRESS);
        if ($control->getStartedAt() === null) {
            $control->setStartedAt($now);
        }
        $control->setUpdatedAt($now);
        $this->entityManager->flush();

        return ServiceResult::success('Exercice session started.', $this->toControlArray($control));
    }

    public function complete(User $user, int $controlId, ?string $feedback, int $activeSeconds): ServiceResult
    {
        $control = $this->controlRepository->findOneOwnedByUser($user, $controlId);
        if (!$control instanceof ExerciceControl) {
            return ServiceResult::failure('Assigned exercice not found.');
        }
        if ($control->getStatus() === ExerciceControl::STATUS_COMPLETED) {
            return ServiceResult::failure('Exercice is already completed.');
        }

        $now = new \DateTimeImmutable();
        $control->setStatus(ExerciceControl::STATUS_COMPLETED);
        if ($control->getStartedAt() === null) {
            $control->setStartedAt($now);
        }
        $control->setCompletedAt($now);
        $control->setFeedback($feedback);
        $control->setActiveSeconds($control->getActiveSeconds() + max(0, $activeSeconds));
        $control->setUpdatedAt($now);
        $this->entityManager->flush();

        return ServiceResult::success('Exercice completed successfully.', $this->toControlArray($control));
    }

    public function history(User $user): ServiceResult
    {
        $controls = $this->controlRepository->findAssignedForUser($user);

        return ServiceResult::success('Exercice history fetched successfully.', [
            'items' => array_map(fn(ExerciceControl $control): array => $this->toControlArray($control), $controls),
        ]);
    }

    public function summary(User $user): ServiceResult
    {
        $controls = $this->controlRepository->findAssignedForUser($user);
        $total = count($controls);
        $completed = count(array_filter($controls, static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_COMPLETED));
        $inProgress = count(array_filter($controls, static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_IN_PROGRESS));
        $assigned = count(array_filter($controls, static fn(ExerciceControl $control): bool => $control->getStatus() === ExerciceControl::STATUS_ASSIGNED));
        $activeSeconds = array_sum(array_map(static fn(ExerciceControl $control): int => $control->getActiveSeconds(), $controls));

        return ServiceResult::success('Exercice summary fetched successfully.', [
            'total' => $total,
            'assigned' => $assigned,
            'inProgress' => $inProgress,
            'completed' => $completed,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0.0,
            'totalActiveSeconds' => $activeSeconds,
        ]);
    }

    public function toggleFavorite(User $user, string $favoriteType, int $itemId): ServiceResult
    {
        $type = strtoupper(trim($favoriteType));
        if (!in_array($type, [ExerciceFavorite::TYPE_EXERCICE, ExerciceFavorite::TYPE_RESOURCE], true)) {
            return ServiceResult::failure('Invalid favorite type.');
        }

        $existing = $this->favoriteRepository->findOneForUser($user, $type, $itemId);
        if ($existing instanceof ExerciceFavorite) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();

            return ServiceResult::success('Favorite removed.', ['favorite' => false]);
        }

        $favorite = (new ExerciceFavorite())
            ->setUser($user)
            ->setFavoriteType($type)
            ->setItemId($itemId);

        $this->entityManager->persist($favorite);
        $this->entityManager->flush();

        return ServiceResult::success('Favorite added.', ['favorite' => true]);
    }

    public function startByExercice(User $user, int $exerciceId): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($exerciceId);
        if (!$exercice instanceof Exercice || !$exercice->isActive()) {
            return ServiceResult::failure('Exercice not found.');
        }

        $control = (new ExerciceControl())
            ->setUser($user)
            ->setExercice($exercice)
            ->setStatus(ExerciceControl::STATUS_ASSIGNED);

        $this->entityManager->persist($control);
        $this->entityManager->flush();

        return $this->start($user, (int) $control->getId());
    }

    private function toControlArray(ExerciceControl $control, array $favoriteMap = []): array
    {
        $exercice = $control->getExercice();
        $resourceRows = [];
        foreach ($exercice->getResources() as $resource) {
            $resourceRows[] = [
                'id' => $resource->getId(),
                'title' => $resource->getTitle(),
                'resourceType' => $resource->getResourceType(),
                'resourceUrl' => $resource->getResourceUrl(),
                'favorite' => isset($favoriteMap[ExerciceFavorite::TYPE_RESOURCE . ':' . $resource->getId()]),
            ];
        }

        return [
            'controlId' => $control->getId(),
            'status' => $control->getStatus(),
            'statusMessage' => $this->statusMessage($control->getStatus()),
            'startedAt' => $control->getStartedAt()?->format('c'),
            'completedAt' => $control->getCompletedAt()?->format('c'),
            'activeSeconds' => $control->getActiveSeconds(),
            'feedback' => $control->getFeedback(),
            'assignedAt' => $control->getCreatedAt()->format('c'),
            'exercice' => [
                'id' => $exercice->getId(),
                'title' => $exercice->getTitle(),
                'type' => $exercice->getType(),
                'level' => $exercice->getLevel(),
                'durationMinutes' => $exercice->getDurationMinutes(),
                'description' => $exercice->getDescription(),
                'isActive' => $exercice->isActive(),
                'favorite' => isset($favoriteMap[ExerciceFavorite::TYPE_EXERCICE . ':' . $exercice->getId()]),
                'resources' => $resourceRows,
            ],
        ];
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            ExerciceControl::STATUS_ASSIGNED => 'Assigned',
            ExerciceControl::STATUS_IN_PROGRESS => 'In progress',
            ExerciceControl::STATUS_COMPLETED => 'Completed',
            ExerciceControl::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(strtolower(str_replace('_', ' ', $status))),
        };
    }

    private function toCatalogItem(Exercice $exercice, ?ExerciceControl $control, array $favoriteMap): array
    {
        $resourceRows = [];
        foreach ($exercice->getResources() as $resource) {
            $resourceRows[] = [
                'id' => $resource->getId(),
                'title' => $resource->getTitle(),
                'resourceType' => $resource->getResourceType(),
                'resourceUrl' => $resource->getResourceUrl(),
                'favorite' => isset($favoriteMap[ExerciceFavorite::TYPE_RESOURCE . ':' . $resource->getId()]),
            ];
        }

        $status = $control?->getStatus() ?? 'NOT_STARTED';

        return [
            'controlId' => $control?->getId() !== null ? (int) $control->getId() : null,
            'status' => $status,
            'statusMessage' => $status === 'NOT_STARTED' ? 'Not started' : $this->statusMessage($status),
            'startedAt' => $control?->getStartedAt()?->format('c'),
            'completedAt' => $control?->getCompletedAt()?->format('c'),
            'activeSeconds' => $control?->getActiveSeconds() ?? 0,
            'feedback' => $control?->getFeedback(),
            'exercice' => [
                'id' => $exercice->getId(),
                'title' => $exercice->getTitle(),
                'type' => $exercice->getType(),
                'level' => $exercice->getLevel(),
                'durationMinutes' => $exercice->getDurationMinutes(),
                'description' => $exercice->getDescription(),
                'isActive' => $exercice->isActive(),
                'favorite' => isset($favoriteMap[ExerciceFavorite::TYPE_EXERCICE . ':' . $exercice->getId()]),
                'resources' => $resourceRows,
            ],
        ];
    }
}
