<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Controller\Admin\Api\ExerciceController;
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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
final class ExerciceControllerTest extends TestCase
{
    private ExerciceRepository&MockObject $exerciceRepository;
    private ExerciceControlRepository&MockObject $controlRepository;
    private ExerciceResourceRepository&MockObject $resourceRepository;
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private GuidedInstructionsFormatter $guidedInstructionsFormatter;
    private AdminExerciceService $service;
    private ExerciceController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Build real service with mocked infrastructure so controller tests stay kernel-free.
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

        $this->controller = new ExerciceController($this->service);
        $this->setAuthenticatedUser(null);
    }

    public function testListReturnsItemsAndSummary(): void
    {
        $exercise = $this->buildExercise(id: 10, title: 'Body Scan');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('findCatalog')
            ->with('body', 'mindfulness', true)
            ->willReturn([$exercise]);
        $this->exerciceRepository->method('count')->willReturnMap([
            [[], 2],
            [['isActive' => true], 1],
        ]);
        $this->controlRepository->expects(self::once())->method('count')->with([])->willReturn(4);
        $this->controlRepository->method('countByStatus')->willReturnMap([
            [ExerciceControl::STATUS_IN_PROGRESS, 1],
            [ExerciceControl::STATUS_COMPLETED, 3],
        ]);

        $request = Request::create('/api/admin/exercice', 'GET', [
            'search' => 'body',
            'type' => 'mindfulness',
            'active' => 'true',
        ]);

        $response = $this->controller->list($request);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Body Scan', $payload['data']['items'][0]['title']);
        self::assertEquals(75.0, $payload['data']['summary']['completionRate']);
    }

    public function testCreateReturnsBadRequestForMalformedJson(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');

        $request = Request::create(
            '/api/admin/exercice',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"title":'
        );

        $response = $this->controller->create($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Malformed JSON payload.', $payload['message']);
    }

    public function testCreateReturnsValidationErrorsForInvalidPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([
            new ConstraintViolation('This value should not be blank.', null, [], '', 'title', ''),
        ]);

        $request = $this->jsonRequest('POST', '/api/admin/exercice', [
            'title' => '',
            'type' => 'breathing',
        ]);

        $response = $this->controller->create($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Validation failed.', $payload['message']);
        self::assertSame('title', $payload['errors'][0]['field']);
    }

    public function testCreateReturnsCreatedResponseOnSuccess(): void
    {
        $validator = $this->createValidatorReturningViolations([]);

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $exercise): bool {
                self::assertInstanceOf(Exercice::class, $exercise);
                self::assertSame('Breathing Reset', $exercise->getTitle());
                self::setPrivateProperty($exercise, 'id', 25);

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $request = $this->jsonRequest('POST', '/api/admin/exercice', [
            'title' => 'Breathing Reset',
            'type' => 'breathing',
            'level' => 2,
            'durationMinutes' => 12,
            'description' => 'A short reset exercise.',
            'benefits' => 'Supports focus.',
            'guidedInstructionsText' => 'Prepare: Sit comfortably.',
            'tips' => 'Move gently.',
            'theme' => 'balanced',
            'isActive' => true,
        ]);

        $response = $this->controller->create($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice created successfully.', $payload['message']);
        self::assertSame(25, $payload['data']['id']);
    }

    public function testUpdateReturnsBadRequestForMalformedJson(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');

        $request = Request::create(
            '/api/admin/exercice/99',
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"title":'
        );

        $response = $this->controller->update(99, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Malformed JSON payload.', $payload['message']);
    }

    public function testUpdateReturnsValidationErrorsForInvalidPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([
            new ConstraintViolation('This value is too short.', null, [], '', 'type', 'ab'),
        ]);

        $request = $this->jsonRequest('PUT', '/api/admin/exercice/15', [
            'title' => 'Okay title',
            'type' => 'ab',
        ]);

        $response = $this->controller->update(15, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('type', $payload['errors'][0]['field']);
    }

    public function testUpdateReturnsSuccessPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $exercise = $this->buildExercise(id: 15, title: 'Old Title');

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(15)
            ->willReturn($exercise);
        $this->entityManager->expects(self::once())->method('flush');

        $request = $this->jsonRequest('PUT', '/api/admin/exercice/15', [
            'title' => 'Updated Breath Reset',
            'type' => 'breathing',
            'level' => 2,
            'durationMinutes' => 12,
            'description' => 'Updated description.',
            'benefits' => 'Updated benefits.',
            'guidedInstructionsText' => 'Focus: Slow your breath.',
            'tips' => 'Stay relaxed.',
            'theme' => 'balanced',
            'isActive' => true,
        ]);

        $response = $this->controller->update(15, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice updated successfully.', $payload['message']);
        self::assertSame('Updated Breath Reset', $payload['data']['title']);
    }

    public function testDeleteReturnsBadRequestWhenExerciseIsMissing(): void
    {
        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(404)
            ->willReturn(null);
        $this->entityManager->expects(self::never())->method('remove');

        $response = $this->controller->delete(404);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Exercice not found.', $payload['message']);
    }

    public function testDeleteReturnsSuccessResponse(): void
    {
        $exercise = $this->buildExercise(id: 16);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(16)
            ->willReturn($exercise);
        $this->entityManager->expects(self::once())->method('remove')->with($exercise);
        $this->entityManager->expects(self::once())->method('flush');

        $response = $this->controller->delete(16);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice deleted successfully.', $payload['message']);
    }

    public function testAssignReturnsBadRequestForMalformedJson(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');

        $request = Request::create(
            '/api/admin/exercice/assign',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"userId":'
        );

        $response = $this->controller->assign($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Malformed JSON payload.', $payload['message']);
    }

    public function testAssignReturnsValidationErrorsForInvalidPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([
            new ConstraintViolation('This value should be positive.', null, [], '', 'exerciceId', 0),
        ]);

        $request = $this->jsonRequest('POST', '/api/admin/exercice/assign', [
            'userId' => 'patient-1',
            'exerciceId' => 0,
        ]);

        $response = $this->controller->assign($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('exerciceId', $payload['errors'][0]['field']);
    }

    public function testAssignReturnsUnauthorizedWhenNoAdminIsAuthenticated(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $this->setAuthenticatedUser(null);

        $request = $this->jsonRequest('POST', '/api/admin/exercice/assign', [
            'userId' => 'patient-1',
            'exerciceId' => 18,
        ]);

        $response = $this->controller->assign($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Unauthorized.', $payload['message']);
    }

    public function testAssignReturnsCreatedResponseOnSuccess(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $admin = $this->buildUser(id: 'admin-1', role: 'ADMIN', email: 'admin@example.com');
        $patient = $this->buildUser(id: 'patient-1', role: 'PATIENT', email: 'patient@example.com');
        $exercise = $this->buildExercise(id: 18, title: 'Focus Walk');
        $this->setAuthenticatedUser($admin);

        $this->exerciceRepository
            ->expects(self::once())
            ->method('find')
            ->with(18)
            ->willReturn($exercise);
        $this->userRepository
            ->expects(self::once())
            ->method('find')
            ->with('patient-1')
            ->willReturn($patient);
        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignment')
            ->with($patient, $exercise)
            ->willReturn(null);
        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (mixed $control) use ($patient, $exercise, $admin): bool {
                self::assertInstanceOf(ExerciceControl::class, $control);
                self::assertSame($patient, $control->getUser());
                self::assertSame($exercise, $control->getExercice());
                self::assertSame($admin, $control->getAssignedBy());
                self::setPrivateProperty($control, 'id', '321');

                return true;
            }));
        $this->entityManager->expects(self::once())->method('flush');

        $request = $this->jsonRequest('POST', '/api/admin/exercice/assign', [
            'userId' => 'patient-1',
            'exerciceId' => 18,
        ]);

        $response = $this->controller->assign($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice assigned successfully.', $payload['message']);
        self::assertSame('Assigned', $payload['data']['statusMessage']);
    }

    public function testControlsReturnsMonitorItems(): void
    {
        $exercise = $this->buildExercise(id: 22);
        $user = $this->buildUser(id: 'patient-22', role: 'PATIENT', email: 'patient22@example.com');
        $control = $this->buildControl(id: '444', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAdminControls')
            ->with('COMPLETED', 'PATIENT')
            ->willReturn([$control]);

        $request = Request::create('/api/admin/exercice/controls', 'GET', [
            'status' => 'COMPLETED',
            'role' => 'PATIENT',
        ]);

        $response = $this->controller->controls($request);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Completed', $payload['data']['items'][0]['statusMessage']);
    }

    private function createValidatorReturningViolations(array $violations): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->method('validate')
            ->willReturn(new ConstraintViolationList($violations));

        return $validator;
    }

    private function setAuthenticatedUser(?User $user): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        if ($user === null) {
            $tokenStorage->method('getToken')->willReturn(null);
        } else {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
        }

        $container = $this->buildContainer([
            'security.token_storage' => $tokenStorage,
        ]);

        $this->controller->setContainer($container);
    }

    private function buildContainer(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public function __construct(private array $services)
            {
            }

            public function get(string $id): mixed
            {
                if (!$this->has($id)) {
                    throw new \RuntimeException(sprintf('Unknown service "%s".', $id));
                }

                return $this->services[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->services);
            }
        };
    }

    private function jsonRequest(string $method, string $uri, array $payload): Request
    {
        return Request::create(
            $uri,
            $method,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function decodeResponse(JsonResponse $response): array
    {
        $content = $response->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function buildExercise(
        int $id,
        string $title = 'Exercise Title',
        string $type = 'breathing',
        int $level = 2,
        int $duration = 10,
        bool $isActive = true,
        array $resources = [],
    ): Exercice {
        $exercise = (new Exercice())
            ->setTitle($title)
            ->setType($type)
            ->setLevel($level)
            ->setDurationMinutes($duration)
            ->setDescription('Exercise description.')
            ->setBenefits('Exercise benefits.')
            ->setGuidedInstructions([
                ['title' => 'Step 1', 'description' => 'Start gently.'],
            ])
            ->setTips('Stay relaxed.')
            ->setTheme('balanced')
            ->setIsActive($isActive)
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T10:00:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T11:00:00+00:00'));

        self::setPrivateProperty($exercise, 'id', $id);
        self::setPrivateProperty($exercise, 'resources', new ArrayCollection($resources));

        return $exercise;
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

    private function buildControl(string $id, User $user, Exercice $exercice, string $status = ExerciceControl::STATUS_ASSIGNED): ExerciceControl
    {
        $control = (new ExerciceControl())
            ->setUser($user)
            ->setExercice($exercice)
            ->setStatus($status)
            ->setStartedAt(new \DateTimeImmutable('2026-05-01T12:00:00+00:00'))
            ->setCompletedAt($status === ExerciceControl::STATUS_COMPLETED ? new \DateTimeImmutable('2026-05-01T12:15:00+00:00') : null)
            ->setActiveSeconds(120)
            ->setFeedback('Helpful session.')
            ->setAssignedBy($this->buildUser('coach-1', 'THERAPIST', 'coach@example.com'))
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T11:55:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T12:15:00+00:00'));

        self::setPrivateProperty($control, 'id', $id);

        return $control;
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
