<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController extends AbstractController
{
    protected function validateDto(ValidatorInterface $validator, object $dto): ?JsonResponse
    {
        $violations = $validator->validate($dto);
        if (count($violations) === 0) {
            return null;
        }

        $errors = [];
        foreach ($violations as $violation) {
            /** @var ConstraintViolationInterface $violation */
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors,
        ], 422);
    }

    protected function hydrate(Request $request, object $dto): object
    {
        $contentType = (string) $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $payload = $request->request->all();
        }

        foreach ($payload as $key => $value) {
            if (property_exists($dto, (string) $key)) {
                $dto->{$key} = $value;
            }
        }

        return $dto;
    }

    protected function bearerToken(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization', '');

        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return trim(substr($authorization, 7));
    }
}
