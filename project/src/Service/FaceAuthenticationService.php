<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Common\ServiceResult;
use App\Entity\User;
use App\Entity\UserFace;
use App\Enum\AuditAction;
use App\Repository\UserRepository;
use App\Repository\UserFaceRepository;
use App\Service\AI\FaceRecognitionService;
use App\Service\Security\FaceLoginRateLimiter;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FaceAuthenticationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserFaceRepository $userFaceRepository,
        private FaceRecognitionService $faceRecognitionService,
        private SessionService $sessionService,
        private AuditLogService $auditLogService,
        private JwtService $jwtService,
        private TokenGenerator $tokenGenerator,
        private FaceLoginRateLimiter $faceLoginRateLimiter,
    ) {}

    public function setFaceAuthentication(User $user, bool $enabled): ServiceResult
    {
        $user->setFaceRecognitionEnabled($enabled);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ServiceResult::success(
            $enabled ? 'Face authentication enabled.' : 'Face authentication disabled.',
            [
                'faceRecognitionEnabled' => $enabled,
            ],
        );
    }

    /**
     * @param array<int, mixed> $tensor
     */
    public function enroll(User $user, ?string $imageBinary = null, array $tensor = []): ServiceResult
    {
        if (!$user->isFaceRecognitionEnabled()) {
            return ServiceResult::failure(
                'Face authentication is disabled for this account.',
                ['error' => 'face_auth_disabled'],
            );
        }

        $embedding = $this->resolveProbeEmbedding($imageBinary, $tensor);
        $encodedEmbedding = json_encode($embedding, JSON_THROW_ON_ERROR);
        $now = new \DateTimeImmutable();

        $face = $this->userFaceRepository->findOneByUser($user) ?? new UserFace()
            ->setId($this->tokenGenerator->generateUuidV4())
            ->setUser($user)
            ->setCreatedAt($now);
        $face
            ->setEmbedding($encodedEmbedding)
            ->setUpdatedAt($now);

        $this->entityManager->persist($face);
        $this->entityManager->flush();

        return ServiceResult::success('Face enrollment saved successfully.', [
            'faceRecognitionEnabled' => true,
            'threshold' => $this->faceRecognitionService->getSimilarityThreshold(),
        ]);
    }

    /**
     * @param array<int, mixed> $tensor
     */
    public function login(string $email, ?string $imageBinary, array $tensor, string $rateLimitKey, bool $rememberMe = false): ServiceResult
    {
        if ($this->faceLoginRateLimiter->isLimited($rateLimitKey)) {
            return ServiceResult::failure('Too many face login attempts. Please try again later.', [
                'error' => 'face_login_rate_limited',
            ]);
        }

        $probeEmbedding = $this->resolveProbeEmbedding($imageBinary, $tensor);
        $user = $this->userRepository->findByEmail($email);
        if (!$user instanceof User || !$user->isFaceRecognitionEnabled()) {
            $this->faceLoginRateLimiter->recordFailure($rateLimitKey);
            return ServiceResult::failure('Face authentication is not available for this email.', [
                'error' => 'face_not_enrolled_for_email',
            ]);
        }

        $knownFace = $this->userFaceRepository->findOneByUser($user);
        $knownEmbedding = $knownFace instanceof UserFace
            ? $this->decodeStoredEmbedding($knownFace->getEmbedding())
            : [];
        $bestScore = $knownEmbedding === []
            ? 0.0
            : $this->faceRecognitionService->cosineSimilarity($knownEmbedding, $probeEmbedding);

        if ($bestScore < $this->faceRecognitionService->getSimilarityThreshold()) {
            $this->faceLoginRateLimiter->recordFailure($rateLimitKey);
            return ServiceResult::failure('Face does not match this email account.', [
                'error' => 'face_not_recognized',
            ]);
        }

        $this->sessionService->revokeActiveSessions($user);
        $session = $this->sessionService->createSession($user);
        $this->auditLogService->log($session, AuditAction::USER_FACE_LOGIN);
        $this->entityManager->flush();

        $this->faceLoginRateLimiter->reset($rateLimitKey);

        return ServiceResult::success('Authentication successful.', $this->buildAuthPayload($user, $session->getRefreshToken(), $rememberMe));
    }

    /**
     * @return list<float>
     */
    private function decodeStoredEmbedding(mixed $payload): array
    {
        if (is_resource($payload)) {
            $payload = stream_get_contents($payload);
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }

        $embedding = [];
        foreach ($decoded as $value) {
            if (!is_numeric($value)) {
                return [];
            }
            $embedding[] = (float) $value;
        }

        return $embedding;
    }

    private function buildAuthPayload(User $user, string $refreshToken, bool $rememberMe = false): array
    {
        $ttl = $rememberMe ? 86400 : 900;

        return [
            'token' => $this->jwtService->createAccessToken($user, $ttl),
            'tokenType' => 'Bearer',
            'expiresIn' => $ttl,
            'refreshToken' => $refreshToken,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'role' => $user->getRole(),
                'accountStatus' => $user->getAccountStatus(),
                'presenceStatus' => $user->getPresenceStatus(),
                'faceRecognitionEnabled' => $user->isFaceRecognitionEnabled(),
                'isTwoFactorEnabled' => $user->isTwoFactorEnabled(),
            ],
        ];
    }

    /**
     * @param array<int, mixed> $tensor
     * @return list<float>
     */
    private function resolveProbeEmbedding(?string $imageBinary, array $tensor): array
    {
        if ($tensor !== []) {
            return $this->faceRecognitionService->extractEmbeddingFromTensor($tensor);
        }

        if ($imageBinary !== null && $imageBinary !== '') {
            return $this->faceRecognitionService->extractEmbeddingFromImage($imageBinary);
        }

        throw new \RuntimeException('A face image or tensor payload is required.');
    }
}
