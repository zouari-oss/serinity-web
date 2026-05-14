<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Dto\Exercice\ExerciceUpsertRequest;
use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\ExerciceResource;
use App\Entity\User;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceRepository;
use App\Repository\ExerciceResourceRepository;
use App\Repository\UserRepository;
use App\Service\Admin\AdminExerciceService;
use App\Service\Exercice\GuidedInstructionsFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class AdminExerciceServiceTest extends TestCase
{
    private ExerciceRepository&MockObject $exerciceRepository;
    private ExerciceControlRepository&MockObject $controlRepository;
    private ExerciceResourceRepository&MockObject $resourceRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private GuidedInstructionsFormatter $guidedInstructionsFormatter;
    private AdminExerciceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        
        $this->exerciceRepository = $this->getMockBuilder(ExerciceRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findCatalog', 'find', 'count', 'createQueryBuilder'])
            ->getMock();
        $this->controlRepository = $this->getMockBuilder(ExerciceControlRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAssignment', 'findAdminControls', 'count', 'countByStatus', 'createQueryBuilder'])
            ->getMock();
        $this->resourceRepository = $this->getMockBuilder(ExerciceResourceRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findForExercice', 'find'])
            ->getMock();
        $this->userRepository = $this->getMockBuilder(UserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find'])
            ->getMock();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->guidedInstructionsFormatter = new GuidedInstructionsFormatter();

        $this->service = new AdminExerciceService(
            $this->exerciceRepository,
            $this->controlRepository,
            $this->resourceRepository,
            $this->userRepository,
            $this->guidedInstructionsFormatter,
            $this->entityManager,
        );
    }

    public function testListExercicesReturnsMappedExerciseRows(): void
    {
        $exercise = $this->buildExercise(id: 10, title: 'Body Scan');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('findCatalog')
            ->with('body', 'mindfulness', true)
            ->willReturn([$exercise]);

        $result = $this->service->listExercices('body', 'mindfulness', true);

        self::assertCount(1, $result);
        self::assertSame(10, $result[0]['id']);
        self::assertSame('Body Scan', $result[0]['title']);
    }

    public function testGetExerciceReturnsNullWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(404)
            ->willReturn(null);

        self::assertNull($this->service->getExercice(404));
    }

    public function testGetExerciceReturnsMappedExerciseWhenFound(): void
    {
        $exercise = $this->buildExercise(id: 11, title: 'Gentle Stretch');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(11)
            ->willReturn($exercise);

        $result = $this->service->getExercice(11);

        self::assertIsArray($result);
        self::assertSame('Gentle Stretch', $result['title']);
        self::assertSame('balanced', $result['theme']);
    }

    public function testCreateExercicePersistsAndFlushesNewExercise(): void
    {
        $request = $this->buildUpsertRequest();

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $exercise) use ($request): bool {
                self::assertInstanceOf(Exercice::class, $exercise);
                self::assertSame($request->title, $exercise->getTitle());
                self::assertSame($request->description, $exercise->getDescription());
                self::assertSame($request->benefits, $exercise->getBenefits());
                self::assertSame($request->theme, $exercise->getTheme());

                self::setPrivateProperty($exercise, 'id', 25);

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->createExercice($request);

        self::assertTrue($result->success);
        self::assertSame('Exercice created successfully.', $result->message);
        self::assertSame(25, $result->data['id']);
        self::assertSame('Prepare', $result->data['guidedInstructions'][0]['title']);
    }

    public function testUpdateExerciceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(13)
            ->willReturn(null);

        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->updateExercice(13, $this->buildUpsertRequest());

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testUpdateExerciceFlushesUpdatedExercise(): void
    {
        $exercise = $this->buildExercise(id: 14, title: 'Old Title');
        $request = $this->buildUpsertRequest(title: 'Updated Breath Reset');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(14)
            ->willReturn($exercise);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->updateExercice(14, $request);

        self::assertTrue($result->success);
        self::assertSame('Exercice updated successfully.', $result->message);
        self::assertSame('Updated Breath Reset', $exercise->getTitle());
        self::assertSame($request->tips, $exercise->getTips());
    }

    public function testDeleteExerciceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $this->entityManager->expects(self::never())->method('remove');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->deleteExercice(99);

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testDeleteExerciceRemovesAndFlushesExercise(): void
    {
        $exercise = $this->buildExercise(id: 15);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(15)
            ->willReturn($exercise);
        $this->entityManager->expects(self::once())->method('remove')->with($exercise);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->deleteExercice(15);

        self::assertTrue($result->success);
        self::assertSame('Exercice deleted successfully.', $result->message);
    }

    public function testAddResourceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(16)
            ->willReturn(null);

        $this->entityManager->expects(self::never())->method('persist');

        $result = $this->service->addResource(16, 'Guide', 'VIDEO', 'https://example.com');

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testAddResourceReturnsFailureWhenRequiredFieldsAreBlank(): void
    {
        $exercise = $this->buildExercise(id: 17);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(17)
            ->willReturn($exercise);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->addResource(17, '  ', 'VIDEO', 'https://example.com');

        self::assertFalse($result->success);
        self::assertSame('Title, resource type and URL are required.', $result->message);
    }

    public function testAddResourcePersistsResourceAndReturnsExerciseData(): void
    {
        $exercise = $this->buildExercise(id: 18);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(18)
            ->willReturn($exercise);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $resource) use ($exercise): bool {
                self::assertInstanceOf(ExerciceResource::class, $resource);
                self::assertSame($exercise, $resource->getExercice());
                self::assertSame('Audio Guide', $resource->getTitle());
                self::setPrivateProperty($resource, 'id', 301);

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->addResource(18, 'Audio Guide', 'AUDIO', 'https://example.com/audio.mp3');

        self::assertTrue($result->success);
        self::assertSame('Resource added successfully.', $result->message);
        self::assertSame(18, $result->data['id']);
    }

    public function testResourcesForExerciceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(19)
            ->willReturn(null);

        $result = $this->service->resourcesForExercice(19);

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testResourcesForExerciceReturnsExerciseAndResourceRows(): void
    {
        $exercise = $this->buildExercise(id: 20, title: 'Mindful Walk');
        $resource = $this->buildResource(id: 401, exercice: $exercise, title: 'Walking Audio');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(20)
            ->willReturn($exercise);
        $this->resourceRepository
            ->expects(self::once())
            ->method('findForExercice')
            ->with($exercise)
            ->willReturn([$resource]);

        $result = $this->service->resourcesForExercice(20);

        self::assertTrue($result->success);
        self::assertSame('Resources fetched successfully.', $result->message);
        self::assertSame('Mindful Walk', $result->data['exercice']['title']);
        self::assertSame('Walking Audio', $result->data['resources'][0]['title']);
        self::assertSame(20, $result->data['resources'][0]['exercice']['id']);
    }

    public function testGetResourceReturnsNullWhenResourceDoesNotExist(): void
    {
        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(88)
            ->willReturn(null);

        self::assertNull($this->service->getResource(88));
    }

    public function testGetResourceReturnsMappedResourceWhenFound(): void
    {
        $exercise = $this->buildExercise(id: 21, title: 'Breath Reset');
        $resource = $this->buildResource(id: 402, exercice: $exercise, title: 'Focus Audio');

        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(402)
            ->willReturn($resource);

        $result = $this->service->getResource(402);

        self::assertIsArray($result);
        self::assertSame('Focus Audio', $result['title']);
        self::assertSame(21, $result['exercice']['id']);
    }

    public function testUpdateResourceReturnsFailureWhenResourceDoesNotExist(): void
    {
        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(500)
            ->willReturn(null);

        $result = $this->service->updateResource(500, 'Guide', 'AUDIO', 'https://example.com');

        self::assertFalse($result->success);
        self::assertSame('Resource not found.', $result->message);
    }

    public function testUpdateResourceReturnsFailureWhenInputIsBlank(): void
    {
        $resource = $this->buildResource(id: 403, exercice: $this->buildExercise(id: 22));

        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(403)
            ->willReturn($resource);

        $this->entityManager->expects(self::never())->method('flush');

        $result = $this->service->updateResource(403, '', 'AUDIO', 'https://example.com');

        self::assertFalse($result->success);
        self::assertSame('Title, resource type and URL are required.', $result->message);
    }

    public function testUpdateResourceFlushesAndReturnsMappedResource(): void
    {
        $exercise = $this->buildExercise(id: 23);
        $resource = $this->buildResource(id: 404, exercice: $exercise, title: 'Old Guide');

        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(404)
            ->willReturn($resource);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->updateResource(404, 'New Guide', 'VIDEO', 'https://example.com/video');

        self::assertTrue($result->success);
        self::assertSame('Resource updated successfully.', $result->message);
        self::assertSame('New Guide', $resource->getTitle());
        self::assertSame('VIDEO', $result->data['resourceType']);
    }

    public function testDeleteResourceReturnsFailureWhenResourceDoesNotExist(): void
    {
        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(405)
            ->willReturn(null);

        $this->entityManager->expects(self::never())->method('remove');

        $result = $this->service->deleteResource(405);

        self::assertFalse($result->success);
        self::assertSame('Resource not found.', $result->message);
    }

    public function testDeleteResourceRemovesResourceAndReturnsExerciseId(): void
    {
        $exercise = $this->buildExercise(id: 24);
        $resource = $this->buildResource(id: 406, exercice: $exercise);

        $this->resourceRepository
            ->expects(self::once())
            ->method('find')
            ->with(406)
            ->willReturn($resource);
        $this->entityManager->expects(self::once())->method('remove')->with($resource);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->deleteResource(406);

        self::assertTrue($result->success);
        self::assertSame('Resource deleted successfully.', $result->message);
        self::assertSame(24, $result->data['exerciceId']);
    }

    public function testAssignExerciceReturnsFailureWhenExerciseDoesNotExist(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(26)
            ->willReturn(null);

        $result = $this->service->assignExercice(26, 'patient-1', $this->buildUser(id: 'admin-1', role: 'ADMIN'));

        self::assertFalse($result->success);
        self::assertSame('Exercice not found.', $result->message);
    }

    public function testAssignExerciceReturnsFailureForInactiveExercise(): void
    {
        $exercise = $this->buildExercise(id: 27, isActive: false);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(27)
            ->willReturn($exercise);

        $result = $this->service->assignExercice(27, 'patient-1', $this->buildUser(id: 'admin-1', role: 'ADMIN'));

        self::assertFalse($result->success);
        self::assertSame('Cannot assign inactive exercice.', $result->message);
    }

    public function testAssignExerciceReturnsFailureWhenUserDoesNotExist(): void
    {
        $exercise = $this->buildExercise(id: 28);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(28)
            ->willReturn($exercise);
        $this->userRepository
            ->expects(self::once())
            ->method('find')
            ->with('missing-user')
            ->willReturn(null);

        $result = $this->service->assignExercice(28, 'missing-user', $this->buildUser(id: 'admin-1', role: 'ADMIN'));

        self::assertFalse($result->success);
        self::assertSame('User not found.', $result->message);
    }

    public function testAssignExerciceReturnsFailureWhenUserRoleIsNotAssignable(): void
    {
        $exercise = $this->buildExercise(id: 29);
        $user = $this->buildUser(id: 'admin-user', role: 'ADMIN');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(29)
            ->willReturn($exercise);
        $this->userRepository
            ->expects(self::once())
            ->method('find')
            ->with('admin-user')
            ->willReturn($user);

        $result = $this->service->assignExercice(29, 'admin-user', $this->buildUser(id: 'owner-admin', role: 'ADMIN'));

        self::assertFalse($result->success);
        self::assertSame('Only patient and therapist users can receive assignments.', $result->message);
    }

    public function testAssignExerciceReturnsFailureWhenActiveAssignmentAlreadyExists(): void
    {
        $exercise = $this->buildExercise(id: 30);
        $user = $this->buildUser(id: 'patient-30');
        $existingControl = $this->buildControl(id: '700', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(30)
            ->willReturn($exercise);
        $this->userRepository
            ->expects(self::once())
            ->method('find')
            ->with('patient-30')
            ->willReturn($user);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignment')
            ->with($user, $exercise)
            ->willReturn($existingControl);

        $result = $this->service->assignExercice(30, 'patient-30', $this->buildUser(id: 'admin-30', role: 'ADMIN'));

        self::assertFalse($result->success);
        self::assertSame('Exercice already assigned to this user.', $result->message);
    }

    public function testAssignExercicePersistsNewControlAndReturnsSuccess(): void
    {
        $exercise = $this->buildExercise(id: 31, title: 'Focus Walk');
        $user = $this->buildUser(id: 'patient-31', role: 'PATIENT', email: 'patient31@example.com');
        $admin = $this->buildUser(id: 'admin-31', role: 'ADMIN', email: 'admin31@example.com');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(31)
            ->willReturn($exercise);
        $this->userRepository
            ->expects(self::once())
            ->method('find')
            ->with('patient-31')
            ->willReturn($user);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignment')
            ->with($user, $exercise)
            ->willReturn(null);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $control) use ($user, $exercise, $admin): bool {
                self::assertInstanceOf(ExerciceControl::class, $control);
                self::assertSame($user, $control->getUser());
                self::assertSame($exercise, $control->getExercice());
                self::assertSame(ExerciceControl::STATUS_ASSIGNED, $control->getStatus());
                self::assertSame($admin, $control->getAssignedBy());
                self::assertSame(0, $control->getActiveSeconds());
                self::setPrivateProperty($control, 'id', '900');

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->assignExercice(31, 'patient-31', $admin);

        self::assertTrue($result->success);
        self::assertSame('Exercice assigned successfully.', $result->message);
        self::assertSame('Assigned', $result->data['statusMessage']);
        self::assertSame('Focus Walk', $result->data['exercice']['title']);
    }

    public function testMonitorReturnsMappedControlRows(): void
    {
        $exercise = $this->buildExercise(id: 32);
        $user = $this->buildUser(id: 'patient-32', role: 'PATIENT');
        $control = $this->buildControl(id: '901', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAdminControls')
            ->with('COMPLETED', 'PATIENT')
            ->willReturn([$control]);

        $result = $this->service->monitor('COMPLETED', 'PATIENT');

        self::assertCount(1, $result);
        self::assertSame('Completed', $result[0]['statusMessage']);
        self::assertSame('patient32@example.com', $result[0]['user']['email']);
    }

    public function testSummaryReturnsAggregatedCounts(): void
    {
        $this->exerciceRepository->method('count')->willReturnMap([
            [[], 12],
            [['isActive' => true], 9],
        ]);
        $this->controlRepository
            ->expects(self::once())
            ->method('count')
            ->with([])
            ->willReturn(20);
        $this->controlRepository->method('countByStatus')->willReturnMap([
            [ExerciceControl::STATUS_IN_PROGRESS, 4],
            [ExerciceControl::STATUS_COMPLETED, 10],
        ]);

        $result = $this->service->summary();

        self::assertSame(12, $result['totalExercices']);
        self::assertSame(9, $result['activeExercices']);
        self::assertSame(20, $result['totalAssignments']);
        self::assertSame(50.0, $result['completionRate']);
    }

    public function testCountExercicesByTypeReturnsMappedRows(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->with('exercice')
            ->willReturn($this->buildQueryBuilderReturningArray([
                ['label' => 'breathing', 'total' => '3'],
                ['label' => 'stretch', 'total' => '2'],
            ], ['select', 'groupBy', 'orderBy']));

        $result = $this->service->countExercicesByType();

        self::assertSame([['breathing', 3], ['stretch', 2]], $result);
    }

    public function testCountControlsByStatusReturnsHumanReadableLabels(): void
    {
        $this->controlRepository
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->with('control')
            ->willReturn($this->buildQueryBuilderReturningArray([
                ['label' => 'IN_PROGRESS', 'total' => '5'],
                ['label' => 'COMPLETED', 'total' => '8'],
            ], ['select', 'groupBy', 'orderBy']));

        $result = $this->service->countControlsByStatus();

        self::assertSame([['In progress', 5], ['Completed', 8]], $result);
    }

    public function testCountResourcesByExerciseReturnsTopResourceCounts(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->with('exercice')
            ->willReturn($this->buildQueryBuilderReturningArray([
                ['title' => 'Body Scan', 'total' => '6'],
                ['title' => 'Breath Reset', 'total' => '4'],
            ], ['select', 'leftJoin', 'groupBy', 'addGroupBy', 'orderBy', 'addOrderBy', 'setMaxResults']));

        $result = $this->service->countResourcesByExercise(5);

        self::assertSame([['Body Scan', 6], ['Breath Reset', 4]], $result);
    }

    private function buildUpsertRequest(string $title = 'Breath Reset'): ExerciceUpsertRequest
    {
        $dto = new ExerciceUpsertRequest();
        $dto->title = $title;
        $dto->type = 'breathing';
        $dto->level = 2;
        $dto->durationMinutes = 12;
        $dto->description = 'A short reset exercise.';
        $dto->benefits = 'Supports focus and calm breathing.';
        $dto->guidedInstructionsText = "Prepare: Sit comfortably and relax your shoulders.\nFocus: Bring your attention to your breathing.";
        $dto->tips = 'Move at a pace that feels natural.';
        $dto->theme = 'balanced';
        $dto->isActive = true;

        return $dto;
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

    private function buildUser(
        string $id,
        string $role = 'PATIENT',
        string $email = 'patient32@example.com',
    ): User {
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

    private function buildControl(
        string $id,
        User $user,
        Exercice $exercice,
        string $status = ExerciceControl::STATUS_ASSIGNED,
    ): ExerciceControl {
        $control = (new ExerciceControl())
            ->setUser($user)
            ->setExercice($exercice)
            ->setStatus($status)
            ->setStartedAt(new \DateTimeImmutable('2026-05-01T12:00:00+00:00'))
            ->setCompletedAt($status === ExerciceControl::STATUS_COMPLETED ? new \DateTimeImmutable('2026-05-01T12:15:00+00:00') : null)
            ->setActiveSeconds(120)
            ->setFeedback('Helpful session.')
            ->setAssignedBy($this->buildUser(id: 'coach-1', role: 'THERAPIST', email: 'coach@example.com'))
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T11:55:00+00:00'))
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

    /**
     * Build a lightweight QueryBuilder mock that supports fluent repository reporting calls.
     *
     * @param list<array<string, string>> $rows
     * @param list<string> $chainMethods
     */
    private function buildQueryBuilderReturningArray(array $rows, array $chainMethods): QueryBuilder
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getArrayResult'])
            ->getMock();
        $query->method('getArrayResult')->willReturn($rows);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(array_merge($chainMethods, ['getQuery']))
            ->getMock();

        foreach ($chainMethods as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }

        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
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
