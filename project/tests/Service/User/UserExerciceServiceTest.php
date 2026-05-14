<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\ExerciceFavorite;
use App\Entity\ExerciceResource;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceFavoriteRepository;
use App\Repository\ExerciceRepository;
use App\Service\User\UserExerciceService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class UserExerciceServiceTest extends TestCase
{
    private ExerciceRepository&MockObject $exerciceRepository;
    private ExerciceControlRepository&MockObject $controlRepository;
    private ExerciceFavoriteRepository&MockObject $favoriteRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UserExerciceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Shared dependency mocks for each isolated unit-test scenario.
        $this->exerciceRepository = $this->getMockBuilder(ExerciceRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findCatalog', 'find'])
            ->getMock();
        $this->controlRepository = $this->getMockBuilder(ExerciceControlRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAssignedForUser', 'findOneOwnedByUser'])
            ->getMock();
        $this->favoriteRepository = $this->getMockBuilder(ExerciceFavoriteRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findForUser', 'findOneForUser'])
            ->getMock();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new UserExerciceService(
            $this->exerciceRepository,
            $this->controlRepository,
            $this->favoriteRepository,
            $this->entityManager,
        );
    }

    public function testCatalogReturnsExerciseItemsWithLatestControlAndFavorites(): void
    {
        // Arrange a catalog exercise with a linked resource and two assignments for the same exercise.
        $user = $this->buildUser('patient-catalog', 'PATIENT', 'catalog@example.com');
        $resource = $this->buildResource(id: 501, exercice: $this->buildExercise(id: 999));
        $exercise = $this->buildExercise(
            id: 41,
            title: 'Breathing Reset',
            resources: [$resource],
        );
        $this->setPrivateProperty($resource, 'exercice', $exercise);

        $olderControl = $this->buildControl(
            id: '1001',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_ASSIGNED,
            createdAt: '2026-05-01T10:00:00+00:00',
        );
        $newerControl = $this->buildControl(
            id: '1002',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_IN_PROGRESS,
            createdAt: '2026-05-01T11:00:00+00:00',
        );
        $exerciseFavorite = $this->buildFavorite(id: 1, user: $user, type: ExerciceFavorite::TYPE_EXERCICE, itemId: 41);
        $resourceFavorite = $this->buildFavorite(id: 2, user: $user, type: ExerciceFavorite::TYPE_RESOURCE, itemId: 501);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('findCatalog')
            ->with(null, null, true)
            ->willReturn([$exercise]);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([$olderControl, $newerControl]);
        $this->favoriteRepository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([$exerciseFavorite, $resourceFavorite]);

        // Act.
        $result = $this->service->catalog($user);

        // Assert the latest control and favorite flags are surfaced in the payload.
        self::assertTrue($result->success);
        self::assertSame('Exercice catalogue fetched successfully.', $result->message);
        self::assertCount(1, $result->data['items']);
        self::assertSame(1002, $result->data['items'][0]['controlId']);
        self::assertSame(ExerciceControl::STATUS_IN_PROGRESS, $result->data['items'][0]['status']);
        self::assertTrue($result->data['items'][0]['exercice']['favorite']);
        self::assertTrue($result->data['items'][0]['exercice']['resources'][0]['favorite']);
    }

    public function testAssignedReturnsMappedAssignedExercises(): void
    {
        // Arrange a user assignment and an exercise-level favorite.
        $user = $this->buildUser('patient-assigned');
        $exercise = $this->buildExercise(id: 42, title: 'Gentle Stretch');
        $control = $this->buildControl(id: '1003', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED);
        $favorite = $this->buildFavorite(id: 3, user: $user, type: ExerciceFavorite::TYPE_EXERCICE, itemId: 42);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([$control]);
        $this->favoriteRepository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([$favorite]);

        $result = $this->service->assigned($user);

        self::assertTrue($result->success);
        self::assertSame('Assigned exercices fetched successfully.', $result->message);
        self::assertCount(1, $result->data['items']);
        self::assertSame('Not started', $result->data['items'][0]['statusMessage']);
        self::assertTrue($result->data['items'][0]['exercice']['favorite']);
    }

    public function testCatalogReturnsNotStartedWhenNoControlExists(): void
    {
        $user = $this->buildUser('patient-catalog-empty');
        $exercise = $this->buildExercise(id: 410, title: 'Grounding');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('findCatalog')
            ->with(null, null, true)
            ->willReturn([$exercise]);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([]);
        $this->favoriteRepository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([]);

        $result = $this->service->catalog($user);

        self::assertTrue($result->success);
        self::assertSame('NOT_STARTED', $result->data['items'][0]['status']);
        self::assertSame('Not started', $result->data['items'][0]['statusMessage']);
    }

    public function testCatalogKeepsAssignedControlAsNotStartedUntilSessionBegins(): void
    {
        $user = $this->buildUser('patient-catalog-assigned');
        $exercise = $this->buildExercise(id: 411, title: 'Body Reset');
        $control = $this->buildControl(
            id: '1411',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_ASSIGNED,
            activeSeconds: 0,
            startedAt: null,
            keepStartedAtNull: true,
        );

        $this->exerciceRepository
            ->expects(self::once())
            ->method('findCatalog')
            ->with(null, null, true)
            ->willReturn([$exercise]);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([$control]);
        $this->favoriteRepository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([]);

        $result = $this->service->catalog($user);

        self::assertTrue($result->success);
        self::assertSame('NOT_STARTED', $result->data['items'][0]['status']);
        self::assertSame('Not started', $result->data['items'][0]['statusMessage']);
    }

    public function testStartReturnsFailureWhenAssignmentIsMissing(): void
    {
        $user = $this->buildUser('patient-start-missing');

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 401)
            ->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->start($user, 401);

        self::assertFalse($result->success);
        self::assertSame('Assigned exercice not found.', $result->message);
    }

    public function testStartReturnsFailureWhenAssignmentIsAlreadyCompleted(): void
    {
        $user = $this->buildUser('patient-start-completed');
        $exercise = $this->buildExercise(id: 43);
        $control = $this->buildControl(id: '1004', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 402)
            ->willReturn($control);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->start($user, 402);

        self::assertFalse($result->success);
        self::assertSame('Completed exercice cannot be restarted.', $result->message);
    }

    public function testStartFlushesAndStartsTheExerciseSession(): void
    {
        $user = $this->buildUser('patient-start-success');
        $exercise = $this->buildExercise(id: 44);
        $control = $this->buildControl(
            id: '1005',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_ASSIGNED,
            startedAt: null,
            keepStartedAtNull: true,
        );

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 403)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->start($user, 403);

        self::assertTrue($result->success);
        self::assertSame('Exercice session started.', $result->message);
        self::assertSame(ExerciceControl::STATUS_IN_PROGRESS, $control->getStatus());
        self::assertNotNull($control->getStartedAt());
        self::assertSame('In progress', $result->data['statusMessage']);
    }

    public function testCompleteReturnsFailureWhenAssignmentIsMissing(): void
    {
        $user = $this->buildUser('patient-complete-missing');

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 501)
            ->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->complete($user, 501, 'Nice session', 120);

        self::assertFalse($result->success);
        self::assertSame('Assigned exercice not found.', $result->message);
    }

    public function testCompleteReturnsFailureWhenAssignmentIsAlreadyCompleted(): void
    {
        $user = $this->buildUser('patient-complete-completed');
        $exercise = $this->buildExercise(id: 45);
        $control = $this->buildControl(id: '1006', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 502)
            ->willReturn($control);
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->complete($user, 502, 'Done', 90);

        self::assertFalse($result->success);
        self::assertSame('Exercice is already completed.', $result->message);
    }

    public function testCompleteEarlyFinishKeepsExerciseInProgress(): void
    {
        $user = $this->buildUser('patient-complete-early');
        $exercise = $this->buildExercise(id: 46);
        $control = $this->buildControl(
            id: '1007',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_IN_PROGRESS,
            activeSeconds: 60,
            startedAt: null,
            keepStartedAtNull: true,
        );

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 503)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->complete($user, 503, 'Very calming.', 150);

        self::assertTrue($result->success);
        self::assertSame('Exercice session saved. Keep going to complete it.', $result->message);
        self::assertSame(ExerciceControl::STATUS_IN_PROGRESS, $control->getStatus());
        self::assertSame(210, $control->getActiveSeconds());
        self::assertSame('Very calming.', $control->getFeedback());
        self::assertNotNull($control->getStartedAt());
        self::assertNull($control->getCompletedAt());
        self::assertSame('In progress', $result->data['statusMessage']);
    }

    public function testCompleteFullDurationMarksExerciseCompleted(): void
    {
        $user = $this->buildUser('patient-complete-full');
        $exercise = $this->buildExercise(id: 460, duration: 3);
        $control = $this->buildControl(
            id: '1060',
            user: $user,
            exercice: $exercise,
            status: ExerciceControl::STATUS_IN_PROGRESS,
            activeSeconds: 60,
            startedAt: null,
            keepStartedAtNull: true,
        );

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 560)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->complete($user, 560, 'Very calming.', 120);

        self::assertTrue($result->success);
        self::assertSame('Exercice completed successfully.', $result->message);
        self::assertSame(ExerciceControl::STATUS_COMPLETED, $control->getStatus());
        self::assertSame(180, $control->getActiveSeconds());
        self::assertSame('Very calming.', $control->getFeedback());
        self::assertNotNull($control->getStartedAt());
        self::assertNotNull($control->getCompletedAt());
        self::assertSame('Completed', $result->data['statusMessage']);
    }

    public function testHistoryReturnsMappedExerciseHistory(): void
    {
        $user = $this->buildUser('patient-history');
        $exercise = $this->buildExercise(id: 47);
        $control = $this->buildControl(id: '1008', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([$control]);

        $result = $this->service->history($user);

        self::assertTrue($result->success);
        self::assertSame('Exercice history fetched successfully.', $result->message);
        self::assertCount(1, $result->data['items']);
        self::assertSame('Completed', $result->data['items'][0]['statusMessage']);
    }

    public function testSummaryReturnsAggregatedExerciseMetrics(): void
    {
        $user = $this->buildUser('patient-summary');
        $exercise = $this->buildExercise(id: 48);
        $controls = [
            $this->buildControl(id: '1009', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED, activeSeconds: 10),
            $this->buildControl(id: '1010', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_IN_PROGRESS, activeSeconds: 30),
            $this->buildControl(id: '1011', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED, activeSeconds: 50),
        ];

        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn($controls);

        $result = $this->service->summary($user);

        self::assertTrue($result->success);
        self::assertSame('Exercice summary fetched successfully.', $result->message);
        self::assertSame(3, $result->data['total']);
        self::assertSame(1, $result->data['assigned']);
        self::assertSame(1, $result->data['inProgress']);
        self::assertSame(1, $result->data['completed']);
        self::assertSame(33.3, $result->data['completionRate']);
        self::assertSame(90, $result->data['totalActiveSeconds']);
    }

    public function testToggleFavoriteReturnsFailureForUnsupportedType(): void
    {
        $user = $this->buildUser('patient-favorite-invalid');

        $this->favoriteRepository->expects(self::never())->method('findOneForUser');

        $result = $this->service->toggleFavorite($user, 'invalid', 77);

        self::assertFalse($result->success);
        self::assertSame('Invalid favorite type.', $result->message);
    }

    public function testToggleFavoriteRemovesExistingFavorite(): void
    {
        $user = $this->buildUser('patient-favorite-remove');
        $favorite = $this->buildFavorite(id: 4, user: $user, type: ExerciceFavorite::TYPE_EXERCICE, itemId: 49);

        $this->favoriteRepository
            ->expects(self::once())
            ->method('findOneForUser')
            ->with($user, ExerciceFavorite::TYPE_EXERCICE, 49)
            ->willReturn($favorite);
        $this->entityManager->expects(self::once())->method('remove')->with($favorite);
        $this->entityManager->expects(self::once())->method('flush');
        $this->entityManager->expects(self::never())->method('persist');

        $result = $this->service->toggleFavorite($user, 'exercice', 49);

        self::assertTrue($result->success);
        self::assertSame('Favorite removed.', $result->message);
        self::assertFalse($result->data['favorite']);
    }

    public function testToggleFavoritePersistsNewFavorite(): void
    {
        $user = $this->buildUser('patient-favorite-add');

        $this->favoriteRepository
            ->expects(self::once())
            ->method('findOneForUser')
            ->with($user, ExerciceFavorite::TYPE_RESOURCE, 88)
            ->willReturn(null);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $favorite) use ($user): bool {
                self::assertInstanceOf(ExerciceFavorite::class, $favorite);
                self::assertSame($user, $favorite->getUser());
                self::assertSame(ExerciceFavorite::TYPE_RESOURCE, $favorite->getFavoriteType());
                self::assertSame(88, $favorite->getItemId());

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');
        $this->entityManager->expects(self::never())->method('remove');

        $result = $this->service->toggleFavorite($user, 'resource', 88);

        self::assertTrue($result->success);
        self::assertSame('Favorite added.', $result->message);
        self::assertTrue($result->data['favorite']);
    }

    public function testStartByExerciceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $user = $this->buildUser('patient-startby-missing');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(51)
            ->willReturn(null);
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->startByExercice($user, 51);

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testStartByExerciceReturnsFailureWhenExerciseIsInactive(): void
    {
        $user = $this->buildUser('patient-startby-inactive');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(52)
            ->willReturn($this->buildExercise(id: 52, isActive: false));
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->startByExercice($user, 52);

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testStartByExercicePersistsAssignmentAndStartsSession(): void
    {
        $user = $this->buildUser('patient-startby-success');
        $exercise = $this->buildExercise(id: 53, title: 'Body Scan');
        $persistedControl = null;

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(53)
            ->willReturn($exercise);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $control) use ($user, $exercise, &$persistedControl): bool {
                self::assertInstanceOf(ExerciceControl::class, $control);
                self::assertSame($user, $control->getUser());
                self::assertSame($exercise, $control->getExercice());
                self::assertSame(ExerciceControl::STATUS_ASSIGNED, $control->getStatus());

                self::setPrivateProperty($control, 'id', '2001');
                $persistedControl = $control;

                return true;
            }));
        $this->entityManager->expects(self::exactly(2))->method('flush');
        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 2001)
            ->willReturnCallback(static function () use (&$persistedControl): ?ExerciceControl {
                return $persistedControl;
            });

        $result = $this->service->startByExercice($user, 53);

        self::assertTrue($result->success);
        self::assertSame('Exercice session started.', $result->message);
        self::assertSame('In progress', $result->data['statusMessage']);
        self::assertSame('Body Scan', $result->data['exercice']['title']);
    }

    private function buildUser(string $id, string $role = 'PATIENT', string $email = 'user@example.com'): User
    {
        return (new User())
            ->setId($id)
            ->setEmail($email)
            ->setPassword('hashed-password')
            ->setRole($role)
            ->setPresenceStatus('OFFLINE')
            ->setAccountStatus('ACTIVE')
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T09:00:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T09:00:00+00:00'));
    }

    private function buildExercise(
        int $id,
        string $title = 'Exercise Title',
        string $type = 'breathing',
        int $level = 2,
        int $duration = 10,
        ?string $description = 'Exercise description.',
        ?string $benefits = 'Exercise benefits.',
        ?array $guidedInstructions = null,
        ?string $tips = 'Stay relaxed.',
        ?string $theme = 'balanced',
        bool $isActive = true,
        array $resources = [],
    ): Exercice {
        $exercise = (new Exercice())
            ->setTitle($title)
            ->setType($type)
            ->setLevel($level)
            ->setDurationMinutes($duration)
            ->setDescription($description)
            ->setBenefits($benefits)
            ->setGuidedInstructions($guidedInstructions ?? [
                ['title' => 'Step 1', 'description' => 'Start gently.'],
            ])
            ->setTips($tips)
            ->setTheme($theme)
            ->setIsActive($isActive)
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T10:00:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T11:00:00+00:00'));

        self::setPrivateProperty($exercise, 'id', $id);
        self::setPrivateProperty($exercise, 'resources', new ArrayCollection($resources));

        return $exercise;
    }

    private function buildControl(
        string $id,
        User $user,
        Exercice $exercice,
        string $status = ExerciceControl::STATUS_ASSIGNED,
        ?string $createdAt = '2026-05-01T11:55:00+00:00',
        ?\DateTimeImmutable $startedAt = null,
        int $activeSeconds = 120,
        bool $keepStartedAtNull = false,
    ): ExerciceControl {
        $control = (new ExerciceControl())
            ->setUser($user)
            ->setExercice($exercice)
            ->setStatus($status)
            ->setStartedAt($keepStartedAtNull ? null : ($startedAt ?? new \DateTimeImmutable('2026-05-01T12:00:00+00:00')))
            ->setCompletedAt($status === ExerciceControl::STATUS_COMPLETED ? new \DateTimeImmutable('2026-05-01T12:15:00+00:00') : null)
            ->setActiveSeconds($activeSeconds)
            ->setFeedback('Helpful session.')
            ->setAssignedBy($this->buildUser(id: 'coach-1', role: 'THERAPIST', email: 'coach@example.com'))
            ->setCreatedAt(new \DateTimeImmutable($createdAt ?? '2026-05-01T11:55:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T12:15:00+00:00'));

        self::setPrivateProperty($control, 'id', $id);

        return $control;
    }

    private function buildResource(
        int $id,
        Exercice $exercice,
        string $title = 'Resource Title',
        string $type = 'AUDIO',
        string $url = 'https://example.com/resource',
    ): ExerciceResource {
        $resource = (new ExerciceResource())
            ->setExercice($exercice)
            ->setTitle($title)
            ->setResourceType($type)
            ->setResourceUrl($url);

        self::setPrivateProperty($resource, 'id', $id);

        return $resource;
    }

    private function buildFavorite(int $id, User $user, string $type, int $itemId): ExerciceFavorite
    {
        $favorite = (new ExerciceFavorite())
            ->setUser($user)
            ->setFavoriteType($type)
            ->setItemId($itemId);

        self::setPrivateProperty($favorite, 'id', $id);

        return $favorite;
    }

    private static function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($object);
        while (!$reflection->hasProperty($property) && $reflection->getParentClass() !== false) {
            $reflection = $reflection->getParentClass();
        }

        $reflectionProperty = $reflection->getProperty($property);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}
