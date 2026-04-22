<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\Api\AbstractApiController;
use App\Dto\Auth\ResendVerificationEmailRequest;
use App\Dto\Auth\VerifyEmailRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use App\Service\EmailVerificationService;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class EmailVerificationController extends AbstractApiController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly UserRepository $userRepository,
        private readonly AuthenticationService $authenticationService,
    ) {
    }

    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new VerifyEmailRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->emailVerificationService->verifyCode($dto->email, $dto->code);
        $status = ($result->data['error'] ?? null) === 'rate_limited' ? 429 : ($result->success ? 200 : 400);

        if (!$result->success) {
            return $this->json($result->toArray(), $status);
        }

        $user = $this->userRepository->findByEmail($dto->email);
        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Unable to complete email verification.',
                'data' => ['error' => 'verification_user_not_found'],
            ], 400);
        }

        $authPayload = $this->authenticationService->createAuthenticatedPayload($user);
        $response = $this->json([
            'success' => true,
            'message' => $result->message,
            'data' => [
                ...$authPayload,
                'redirect' => '/dashboard',
            ],
        ], 200);
        $response->headers->setCookie(new Cookie(
            'refresh_token',
            $authPayload['refreshToken'],
            time() + 604800,
            '/',
            null,
            false,
            true,
            false,
            'lax',
        ));

        return $response;
    }

    #[Route('/verify-email/resend', name: 'api_auth_verify_email_resend', methods: ['POST'])]
    public function resendVerificationCode(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new ResendVerificationEmailRequest();
        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->emailVerificationService->resendCode($dto->email, $dto->resend);
        $error = is_array($result->data) ? ($result->data['error'] ?? null) : null;
        $status = $result->success
            ? 200
            : (in_array($error, ['rate_limited', 'resend_limit_reached'], true) ? 429 : 400);

        return $this->json($result->toArray(), $status);
    }
}
