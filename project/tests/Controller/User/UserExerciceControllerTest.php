<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\Api\UserExerciceController;
use App\Entity\Exercice;
use App\Entity\ExerciceControl;
use App\Entity\ExerciceFavorite;
use App\Entity\ExerciceResource;
use App\Entity\User;
use App\Enum\AccountStatus;
use App\Repository\ExerciceControlRepository;
use App\Repository\ExerciceFavoriteRepository;
use App\Repository\ExerciceRepository;
use App\Service\User\UserExerciceService;
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
final class UserExerciceControllerTest extends TestCase
{
    private ExerciceRepository&MockObject $exerciceRepository;
    private ExerciceControlRepository&MockObject $controlRepository;
    private ExerciceFavoriteRepository&MockObject $favoriteRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UserExerciceService $service;
    private UserExerciceController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Real service, mocked persistence dependencies, no kernel and no database.
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

        $this->controller = new UserExerciceController($this->service);
        $this->setAuthenticatedUser(null);
    }

    public function testAssignedReturnsUnauthorizedWhenNoUserIsAuthenticated(): void
    {
        $this->setAuthenticatedUser(null);

        $response = $this->controller->assigned();
        $payload = $this->decodeResponse($response);

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Unauthorized.', $payload['message']);
    }

    public function testAssignedReturnsMappedItems(): void
    {
        $user = $this->buildUser('patient-1');
        $exercise = $this->buildExercise(id: 61, title: 'Body Scan');
        $control = $this->buildControl(id: '601', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED);
        $favorite = $this->buildFavorite(id: 1, user: $user, type: ExerciceFavorite::TYPE_EXERCICE, itemId: 61);
        $this->setAuthenticatedUser($user);

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

        $response = $this->controller->assigned();
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Assigned exercices fetched successfully.', $payload['message']);
        self::assertTrue($payload['data']['items'][0]['exercice']['favorite']);
    }

    public function testStartReturnsForbiddenForUnsupportedRole(): void
    {
        $this->setAuthenticatedUser($this->buildUser('admin-1', role: 'ADMIN'));

        $response = $this->controller->start(701);
        $payload = $this->decodeResponse($response);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Forbidden.', $payload['message']);
    }

    public function testStartReturnsBadRequestWhenServiceFails(): void
    {
        $user = $this->buildUser('patient-start-fail');
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 702)
            ->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');

        $response = $this->controller->start(702);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Assigned exercice not found.', $payload['message']);
    }

    public function testStartReturnsSuccessPayload(): void
    {
        $user = $this->buildUser('patient-start');
        $exercise = $this->buildExercise(id: 62, title: 'Breathing Reset');
        $control = $this->buildControl(id: '602', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED, startedAt: null, keepStartedAtNull: true);
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 703)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $response = $this->controller->start(703);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice session started.', $payload['message']);
        self::assertSame('In progress', $payload['data']['statusMessage']);
    }

    public function testCompleteReturnsBadRequestForMalformedJson(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $this->setAuthenticatedUser($this->buildUser('patient-complete-json'));

        $request = Request::create(
            '/api/user/exercice/session/801/complete',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"feedback":'
        );

        $response = $this->controller->complete(801, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Malformed JSON payload.', $payload['message']);
    }

    public function testCompleteReturnsValidationErrors(): void
    {
        $validator = $this->createValidatorReturningViolations([
            new ConstraintViolation('This value should be positive or zero.', null, [], '', 'activeSeconds', -1),
        ]);
        $this->setAuthenticatedUser($this->buildUser('patient-complete-validation'));

        $request = $this->jsonRequest('POST', '/api/user/exercice/session/802/complete', [
            'feedback' => 'Good',
            'activeSeconds' => -1,
        ]);

        $response = $this->controller->complete(802, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('activeSeconds', $payload['errors'][0]['field']);
    }

    public function testCompleteReturnsSuccessPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $user = $this->buildUser('patient-complete');
        $exercise = $this->buildExercise(id: 63, title: 'Focus Walk');
        $control = $this->buildControl(id: '603', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_IN_PROGRESS, activeSeconds: 40, startedAt: null, keepStartedAtNull: true);
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 803)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $request = $this->jsonRequest('POST', '/api/user/exercice/session/803/complete', [
            'feedback' => 'Very helpful.',
            'activeSeconds' => 80,
        ]);

        $response = $this->controller->complete(803, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice session saved. Keep going to complete it.', $payload['message']);
        self::assertSame('In progress', $payload['data']['statusMessage']);
        self::assertSame(120, $payload['data']['activeSeconds']);
    }

    public function testCompleteReturnsCompletedPayloadWhenRequiredDurationIsReached(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $user = $this->buildUser('patient-complete-full');
        $exercise = $this->buildExercise(id: 631, title: 'Focus Walk');
        $control = $this->buildControl(id: '6031', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_IN_PROGRESS, activeSeconds: 40, startedAt: null, keepStartedAtNull: true);
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findOneOwnedByUser')
            ->with($user, 8031)
            ->willReturn($control);
        $this->entityManager->expects(self::once())->method('flush');

        $request = $this->jsonRequest('POST', '/api/user/exercice/session/8031/complete', [
            'feedback' => 'Very helpful.',
            'activeSeconds' => 560,
        ]);

        $response = $this->controller->complete(8031, $request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice completed successfully.', $payload['message']);
        self::assertSame('Completed', $payload['data']['statusMessage']);
        self::assertSame(600, $payload['data']['activeSeconds']);
    }

    public function testHistoryReturnsMappedItems(): void
    {
        $user = $this->buildUser('patient-history');
        $exercise = $this->buildExercise(id: 64);
        $control = $this->buildControl(id: '604', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED);
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn([$control]);

        $response = $this->controller->history();
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice history fetched successfully.', $payload['message']);
        self::assertSame('Completed', $payload['data']['items'][0]['statusMessage']);
    }

    public function testSummaryReturnsDisabledAccountError(): void
    {
        $this->setAuthenticatedUser($this->buildUser('patient-disabled', accountStatus: AccountStatus::DISABLED->value));

        $response = $this->controller->summary();
        $payload = $this->decodeResponse($response);

        self::assertSame(403, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('account_disabled', $payload['error']);
        self::assertSame('Your account is disabled.', $payload['message']);
    }

    public function testSummaryReturnsAggregatedPayload(): void
    {
        $user = $this->buildUser('patient-summary');
        $exercise = $this->buildExercise(id: 65);
        $controls = [
            $this->buildControl(id: '605', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_ASSIGNED, activeSeconds: 10),
            $this->buildControl(id: '606', user: $user, exercice: $exercise, status: ExerciceControl::STATUS_COMPLETED, activeSeconds: 20),
        ];
        $this->setAuthenticatedUser($user);

        $this->controlRepository
            ->expects(self::once())
            ->method('findAssignedForUser')
            ->with($user)
            ->willReturn($controls);

        $response = $this->controller->summary();
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Exercice summary fetched successfully.', $payload['message']);
        self::assertSame(2, $payload['data']['total']);
        self::assertEquals(50.0, $payload['data']['completionRate']);
    }

    public function testFavoriteReturnsBadRequestForMalformedJson(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $this->setAuthenticatedUser($this->buildUser('patient-favorite-json'));

        $request = Request::create(
            '/api/user/exercice/favorite',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"favoriteType":'
        );

        $response = $this->controller->favorite($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Malformed JSON payload.', $payload['message']);
    }

    public function testFavoriteReturnsValidationErrors(): void
    {
        $validator = $this->createValidatorReturningViolations([
            new ConstraintViolation('This value should be positive.', null, [], '', 'itemId', 0),
        ]);
        $this->setAuthenticatedUser($this->buildUser('patient-favorite-validation'));

        $request = $this->jsonRequest('POST', '/api/user/exercice/favorite', [
            'favoriteType' => 'RESOURCE',
            'itemId' => 0,
        ]);

        $response = $this->controller->favorite($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('itemId', $payload['errors'][0]['field']);
    }

    public function testFavoriteReturnsSuccessPayload(): void
    {
        $validator = $this->createValidatorReturningViolations([]);
        $user = $this->buildUser('patient-favorite');
        $this->setAuthenticatedUser($user);

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

        $request = $this->jsonRequest('POST', '/api/user/exercice/favorite', [
            'favoriteType' => 'RESOURCE',
            'itemId' => 88,
        ]);

        $response = $this->controller->favorite($request, $validator);
        $payload = $this->decodeResponse($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Favorite added.', $payload['message']);
        self::assertTrue($payload['data']['favorite']);
    }

    private function createValidatorReturningViolations(array $violations): ValidatorInterface
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList($violations));

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

        $this->controller->setContainer($this->buildContainer([
            'security.token_storage' => $tokenStorage,
        ]));
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

    private function buildUser(
        string $id,
        string $role = 'PATIENT',
        string $email = 'user@example.com',
        string $accountStatus = AccountStatus::ACTIVE->value,
    ): User {
        return (new User())
            ->setId($id)
            ->setEmail($email)
            ->setPassword('hashed-password')
            ->setRole($role)
            ->setPresenceStatus('OFFLINE')
            ->setAccountStatus($accountStatus)
            ->setFaceRecognitionEnabled(false)
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T09:00:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T09:00:00+00:00'));
    }

    private function buildExercise(
        int $id,
        string $title = 'Exercise Title',
        array $resources = [],
    ): Exercice {
        $exercise = (new Exercice())
            ->setTitle($title)
            ->setType('breathing')
            ->setLevel(2)
            ->setDurationMinutes(10)
            ->setDescription('Exercise description.')
            ->setBenefits('Exercise benefits.')
            ->setGuidedInstructions([
                ['title' => 'Step 1', 'description' => 'Start gently.'],
            ])
            ->setTips('Stay relaxed.')
            ->setTheme('balanced')
            ->setIsActive(true)
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
        int $activeSeconds = 120,
        ?\DateTimeImmutable $startedAt = null,
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
            ->setAssignedBy($this->buildUser('coach-1', 'THERAPIST', 'coach@example.com'))
            ->setCreatedAt(new \DateTimeImmutable('2026-05-01T11:55:00+00:00'))
            ->setUpdatedAt(new \DateTimeImmutable('2026-05-01T12:15:00+00:00'));

        self::setPrivateProperty($control, 'id', $id);

        return $control;
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
