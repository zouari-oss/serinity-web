<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Auth\FaceEnrollRequest;
use App\Dto\Auth\FaceLoginRequest;
use App\Dto\Auth\FaceToggleRequest;
use App\Entity\User;
use App\Service\FaceAuthenticationService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth/face')]
final class FaceAuthController extends AbstractApiController
{
    public function __construct(
        private readonly FaceAuthenticationService $faceAuthenticationService,
    ) {
    }

    #[Route('/enable', name: 'api_auth_face_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $dto = new FaceToggleRequest();
        if (str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            try {
                $this->hydrate($request, $dto);
            } catch (\JsonException) {
                throw new BadRequestHttpException('Malformed JSON payload.');
            }
        }

        $result = $this->faceAuthenticationService->setFaceAuthentication($user, $dto->enabled);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/disable', name: 'api_auth_face_disable', methods: ['POST'])]
    public function disable(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $result = $this->faceAuthenticationService->setFaceAuthentication($user, false);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/enroll', name: 'api_auth_face_enroll', methods: ['POST'])]
    public function enroll(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $dto = new FaceEnrollRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        $imageBinary = $this->extractImageBinary($request, $dto->image);
        $tensor = $this->extractTensor($dto->tensor);
        if ($imageBinary === null && $tensor === []) {
            return $this->json([
                'success' => false,
                'error' => 'invalid_face_image',
                'message' => 'A valid image or face tensor is required.',
            ], 422);
        }

        try {
            $result = $this->faceAuthenticationService->enroll($user, $imageBinary, $tensor);
        } catch (\RuntimeException|\JsonException $exception) {
            return $this->json([
                'success' => false,
                'error' => 'face_enrollment_failed',
                'message' => $exception->getMessage(),
            ], 400);
        }

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/login', name: 'api_auth_face_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $dto = new FaceLoginRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        $imageBinary = $this->extractImageBinary($request, $dto->image);
        $tensor = $this->extractTensor($dto->tensor);
        $email = mb_strtolower(trim((string) $dto->email));
        if ($email === '') {
            return $this->json([
                'success' => false,
                'error' => 'invalid_face_login_email',
                'message' => 'Email is required for face authentication.',
            ], 422);
        }
        if ($imageBinary === null && $tensor === []) {
            return $this->json([
                'success' => false,
                'error' => 'invalid_face_image',
                'message' => 'A valid image or face tensor is required.',
            ], 422);
        }

        $rateLimitKey = sprintf(
            '%s|%s|%s|%d',
            $email,
            (string) ($request->getClientIp() ?? 'unknown'),
            mb_substr((string) $request->headers->get('User-Agent', ''), 0, 120),
            (int) floor(time() / 120),
        );

        try {
            $result = $this->faceAuthenticationService->login($email, $imageBinary, $tensor, $rateLimitKey, $dto->rememberMe);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'success' => false,
                'error' => 'face_auth_runtime_error',
                'message' => $exception->getMessage(),
            ], 400);
        }
        $status = match ($result->data['error'] ?? null) {
            'face_login_rate_limited' => 429,
            'face_not_recognized' => 401,
            'face_not_enrolled_for_email' => 401,
            default => ($result->success ? 200 : 400),
        };

        $response = $this->json($result->toArray(), $status);

        if ($result->success && is_string($result->data['refreshToken'] ?? null)) {
            $maxAge = $dto->rememberMe ? 2592000 : 604800;
            $response->headers->setCookie(new Cookie(
                'refresh_token',
                $result->data['refreshToken'],
                time() + $maxAge,
                '/',
                null,
                false,
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }

    private function extractImageBinary(Request $request, ?string $base64Image): ?string
    {
        $uploaded = $request->files->get('image');
        if ($uploaded instanceof UploadedFile) {
            $content = file_get_contents($uploaded->getPathname());

            return is_string($content) && $content !== '' ? $content : null;
        }

        if ($base64Image === null || trim($base64Image) === '') {
            return null;
        }

        $normalized = trim($base64Image);
        if (str_starts_with($normalized, 'data:image/')) {
            $parts = explode(',', $normalized, 2);
            $normalized = $parts[1] ?? '';
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    /**
     * @param array<int, mixed>|mixed $tensor
     * @return array<int, mixed>
     */
    private function extractTensor(mixed $tensor): array
    {
        if (!is_array($tensor)) {
            return [];
        }

        $flat = $this->flatten($tensor);
        if ($flat === []) {
            return [];
        }

        foreach ($flat as $value) {
            if (!is_numeric($value)) {
                return [];
            }
        }

        return $tensor;
    }

    /**
     * @return list<mixed>
     */
    private function flatten(mixed $value): array
    {
        if (!is_array($value)) {
            return [$value];
        }

        $result = [];
        foreach ($value as $item) {
            array_push($result, ...$this->flatten($item));
        }

        return $result;
    }
}
