<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Dto\Common\ServiceResult;
use App\Dto\Exercice\ExerciceUpsertRequest;
use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\ExerciceResource;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceRepository;
use App\Repository\ExerciceResourceRepository;
use App\Repository\UserRepository;
use App\Service\Exercice\GuidedInstructionsFormatter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AdminExerciceService
{
    public function __construct(
        private ExerciceRepository $exerciceRepository,
        private ExerciceControlRepository $controlRepository,
        private ExerciceResourceRepository $resourceRepository,
        private UserRepository $userRepository,
        private GuidedInstructionsFormatter $guidedInstructionsFormatter,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function listExercices(?string $search = null, ?string $type = null, ?bool $active = null): array
    {
        return array_map(
            fn(Exercice $exercice): array => $this->toExerciceArray($exercice),
            $this->exerciceRepository->findCatalog($search, $type, $active),
        );
    }

    public function getExercice(int $id): ?array
    {
        $exercice = $this->exerciceRepository->find($id);
        if (!$exercice instanceof Exercice) {
            return null;
        }

        return $this->toExerciceArray($exercice);
    }

    public function createExercice(ExerciceUpsertRequest $request): ServiceResult
    {
        $exercice = (new Exercice())
            ->setTitle($request->title)
            ->setType($request->type)
            ->setLevel($request->level)
            ->setDurationMinutes($request->durationMinutes)
            ->setDescription($request->description)
            ->setBenefits($request->benefits)
            ->setTips($request->tips)
            ->setTheme($request->theme)
            ->setGuidedInstructions($this->normalizeGuidedInstructions($request->guidedInstructionsText))
            ->setIsActive($request->isActive);

        $this->entityManager->persist($exercice);
        $this->entityManager->flush();

        return ServiceResult::success('Exercice created successfully.', $this->toExerciceArray($exercice));
    }

    public function updateExercice(int $id, ExerciceUpsertRequest $request): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($id);
        if (!$exercice instanceof Exercice) {
            return ServiceResult::failure('Exercice not found.');
        }

        $exercice
            ->setTitle($request->title)
            ->setType($request->type)
            ->setLevel($request->level)
            ->setDurationMinutes($request->durationMinutes)
            ->setDescription($request->description)
            ->setBenefits($request->benefits)
            ->setTips($request->tips)
            ->setTheme($request->theme)
            ->setGuidedInstructions($this->normalizeGuidedInstructions($request->guidedInstructionsText))
            ->setIsActive($request->isActive)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return ServiceResult::success('Exercice updated successfully.', $this->toExerciceArray($exercice));
    }

    public function deleteExercice(int $id): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($id);
        if (!$exercice instanceof Exercice) {
            return ServiceResult::failure('Exercice not found.');
        }

        $this->entityManager->remove($exercice);
        $this->entityManager->flush();

        return ServiceResult::success('Exercice deleted successfully.');
    }

    public function addResource(int $exerciceId, string $title, string $resourceType, string $resourceUrl): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($exerciceId);
        if (!$exercice instanceof Exercice) {
            return ServiceResult::failure('Exercice not found.');
        }

        $title = trim($title);
        $resourceType = trim($resourceType);
        $resourceUrl = trim($resourceUrl);
        if ($title === '' || $resourceType === '' || $resourceUrl === '') {
            return ServiceResult::failure('Title, resource type and URL are required.');
        }

        $resource = (new ExerciceResource())
            ->setExercice($exercice)
            ->setTitle($title)
            ->setResourceType($resourceType)
            ->setResourceUrl($resourceUrl);

        $this->entityManager->persist($resource);
        $this->entityManager->flush();

        return ServiceResult::success('Resource added successfully.', $this->toExerciceArray($exercice));
    }

    public function resourcesForExercice(int $exerciceId): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($exerciceId);
        if (!$exercice instanceof Exercice) {
            return ServiceResult::failure('Exercice not found.');
        }

        return ServiceResult::success('Resources fetched successfully.', [
            'exercice' => $this->toExerciceArray($exercice),
            'resources' => array_map(
                fn(ExerciceResource $resource): array => $this->toResourceArray($resource),
                $this->resourceRepository->findForExercice($exercice),
            ),
        ]);
    }

    public function getResource(int $resourceId): ?array
    {
        $resource = $this->resourceRepository->find($resourceId);
        if (!$resource instanceof ExerciceResource) {
            return null;
        }

        return $this->toResourceArray($resource);
    }

    public function updateResource(int $resourceId, string $title, string $resourceType, string $resourceUrl): ServiceResult
    {
        $resource = $this->resourceRepository->find($resourceId);
        if (!$resource instanceof ExerciceResource) {
            return ServiceResult::failure('Resource not found.');
        }

        $title = trim($title);
        $resourceType = trim($resourceType);
        $resourceUrl = trim($resourceUrl);
        if ($title === '' || $resourceType === '' || $resourceUrl === '') {
            return ServiceResult::failure('Title, resource type and URL are required.');
        }

        $resource
            ->setTitle($title)
            ->setResourceType($resourceType)
            ->setResourceUrl($resourceUrl);

        $this->entityManager->flush();

        return ServiceResult::success('Resource updated successfully.', $this->toResourceArray($resource));
    }

    public function deleteResource(int $resourceId): ServiceResult
    {
        $resource = $this->resourceRepository->find($resourceId);
        if (!$resource instanceof ExerciceResource) {
            return ServiceResult::failure('Resource not found.');
        }

        $exerciceId = (int) $resource->getExercice()->getId();
        $this->entityManager->remove($resource);
        $this->entityManager->flush();

        return ServiceResult::success('Resource deleted successfully.', [
            'exerciceId' => $exerciceId,
        ]);
    }

    public function assignExercice(int $exerciceId, string $userId, User $assignedBy): ServiceResult
    {
        $exercice = $this->exerciceRepository->find($exerciceId);
        if (!$exercice instanceof Exercice) {
            return ServiceResult::failure('Exercice not found.');
        }
        if (!$exercice->isActive()) {
            return ServiceResult::failure('Cannot assign inactive exercice.');
        }

        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            return ServiceResult::failure('User not found.');
        }
        if (!in_array($user->getRole(), ['PATIENT', 'THERAPIST'], true)) {
            return ServiceResult::failure('Only patient and therapist users can receive assignments.');
        }

        $existing = $this->controlRepository->findAssignment($user, $exercice);
        if ($existing instanceof ExerciceControl && $existing->getStatus() !== ExerciceControl::STATUS_CANCELLED) {
            return ServiceResult::failure('Exercice already assigned to this user.');
        }

        $control = $existing instanceof ExerciceControl ? $existing : new ExerciceControl();
        $control
            ->setUser($user)
            ->setExercice($exercice)
            ->setStatus(ExerciceControl::STATUS_ASSIGNED)
            ->setAssignedBy($assignedBy)
            ->setStartedAt(null)
            ->setCompletedAt(null)
            ->setActiveSeconds(0)
            ->setFeedback(null)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($control);
        $this->entityManager->flush();

        return ServiceResult::success('Exercice assigned successfully.', $this->toControlArray($control));
    }

    public function monitor(?string $status = null, ?string $role = null): array
    {
        return array_map(
            fn(ExerciceControl $control): array => $this->toControlArray($control),
            $this->controlRepository->findAdminControls($status, $role),
        );
    }

    public function summary(): array
    {
        $totalExercices = $this->exerciceRepository->count([]);
        $activeExercices = $this->exerciceRepository->count(['isActive' => true]);
        $totalAssignments = $this->controlRepository->count([]);
        $inProgress = $this->controlRepository->countByStatus(ExerciceControl::STATUS_IN_PROGRESS);
        $completed = $this->controlRepository->countByStatus(ExerciceControl::STATUS_COMPLETED);

        return [
            'totalExercices' => $totalExercices,
            'activeExercices' => $activeExercices,
            'totalAssignments' => $totalAssignments,
            'inProgress' => $inProgress,
            'completed' => $completed,
            'completionRate' => $totalAssignments > 0 ? round(($completed / $totalAssignments) * 100, 1) : 0.0,
        ];
    }

    private function toExerciceArray(Exercice $exercice): array
    {
        $resources = [];
        foreach ($exercice->getResources() as $resource) {
            $resources[] = $this->toResourceArray($resource, includeExercice: false);
        }

        return [
            'id' => $exercice->getId(),
            'title' => $exercice->getTitle(),
            'type' => $exercice->getType(),
            'level' => $exercice->getLevel(),
            'durationMinutes' => $exercice->getDurationMinutes(),
            'description' => $exercice->getDescription(),
            'benefits' => $exercice->getBenefits(),
            'tips' => $exercice->getTips(),
            'theme' => $exercice->getTheme(),
            'guidedInstructions' => $exercice->getGuidedInstructions() ?? [],
            'guidedInstructionsText' => $this->guidedInstructionsFormatter->structuredToText($exercice->getGuidedInstructions()),
            'isActive' => $exercice->isActive(),
            'resources' => $resources,
            'createdAt' => $exercice->getCreatedAt()->format('c'),
            'updatedAt' => $exercice->getUpdatedAt()->format('c'),
        ];
    }

    /**
     * @return list<array{0:string,1:int}>
     */
    public function countExercicesByType(): array
    {
        $rows = $this->exerciceRepository->createQueryBuilder('exercice')
            ->select('exercice.type AS label, COUNT(exercice.id) AS total')
            ->groupBy('exercice.type')
            ->orderBy('exercice.type', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $row): array => [(string) $row['label'], (int) $row['total']],
            $rows,
        );
    }

    /**
     * @return list<array{0:string,1:int}>
     */
    public function countControlsByStatus(): array
    {
        $rows = $this->controlRepository->createQueryBuilder('control')
            ->select('control.status AS label, COUNT(control.id) AS total')
            ->groupBy('control.status')
            ->orderBy('control.status', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $row): array => [ucfirst(strtolower(str_replace('_', ' ', (string) $row['label']))), (int) $row['total']],
            $rows,
        );
    }

    /**
     * @return list<array{0:string,1:int}>
     */
    public function countResourcesByExercise(int $limit = 10): array
    {
        $rows = $this->exerciceRepository->createQueryBuilder('exercice')
            ->select('exercice.title AS title, COUNT(resource.id) AS total')
            ->leftJoin('exercice.resources', 'resource')
            ->groupBy('exercice.id')
            ->addGroupBy('exercice.title')
            ->orderBy('total', 'DESC')
            ->addOrderBy('exercice.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn(array $row): array => [(string) $row['title'], (int) $row['total']],
            $rows,
        );
    }

    private function toResourceArray(ExerciceResource $resource, bool $includeExercice = true): array
    {
        $data = [
            'id' => $resource->getId(),
            'title' => $resource->getTitle(),
            'resourceType' => $resource->getResourceType(),
            'resourceUrl' => $resource->getResourceUrl(),
            'createdAt' => $resource->getCreatedAt()->format('c'),
        ];

        if ($includeExercice) {
            $exercice = $resource->getExercice();
            $data['exercice'] = [
                'id' => $exercice->getId(),
                'title' => $exercice->getTitle(),
                'type' => $exercice->getType(),
            ];
        }

        return $data;
    }

    private function toControlArray(ExerciceControl $control): array
    {
        return [
            'id' => $control->getId(),
            'status' => $control->getStatus(),
            'statusMessage' => $this->statusMessage($control->getStatus()),
            'startedAt' => $control->getStartedAt()?->format('c'),
            'completedAt' => $control->getCompletedAt()?->format('c'),
            'activeSeconds' => $control->getActiveSeconds(),
            'feedback' => $control->getFeedback(),
            'user' => [
                'id' => $control->getUser()->getId(),
                'email' => $control->getUser()->getEmail(),
                'role' => $control->getUser()->getRole(),
            ],
            'exercice' => $this->toExerciceArray($control->getExercice()),
            'assignedBy' => $control->getAssignedBy()?->getEmail(),
            'createdAt' => $control->getCreatedAt()->format('c'),
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

    /**
     * Admins write plain text lines; the user UI consumes structured JSON steps.
     *
     * @return list<array{title: string, description: string}>|null
     */
    private function normalizeGuidedInstructions(?string $text): ?array
    {
        $rows = $this->guidedInstructionsFormatter->textToStructured($text);

        return $rows !== [] ? $rows : null;
    }
}
