<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\Api\AbstractApiController;
use App\Dto\Auth\ResetPasswordConfirmRequest;
use App\Dto\Auth\ResetPasswordSendRequest;
use App\Dto\Auth\VerifyResetCodeRequest;
use App\Service\PasswordResetService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
final class PasswordResetController extends AbstractApiController
{
    public function __construct(private readonly PasswordResetService $passwordResetService)
    {
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    #[Route('/reset/send', name: 'api_auth_reset_send', methods: ['POST'])]
    public function requestResetCode(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new ResetPasswordSendRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->passwordResetService->requestResetCode($dto->email, $request->getClientIp(), $dto->resend);
        $error = is_array($result->data) ? ($result->data['error'] ?? null) : null;
        $status = $result->success
            ? 200
            : (in_array($error, ['rate_limited', 'resend_limit_reached'], true) ? 429 : 400);

        return $this->json($result->toArray(), $status);
    }

    #[Route('/verify-reset-code', name: 'api_auth_verify_reset_code', methods: ['POST'])]
    public function verifyResetCode(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new VerifyResetCodeRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->passwordResetService->verifyResetCode($dto->email, $dto->code);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    #[Route('/reset/confirm', name: 'api_auth_reset_confirm', methods: ['POST'])]
    public function resetPassword(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new ResetPasswordConfirmRequest();

        try {
            $this->hydrate($request, $dto);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON payload.');
        }

        if (($errors = $this->validateDto($validator, $dto)) !== null) {
            return $errors;
        }

        $result = $this->passwordResetService->resetPassword($dto->email, $dto->code, $dto->newPassword);

        return $this->json($result->toArray(), $result->success ? 200 : 400);
    }
}
