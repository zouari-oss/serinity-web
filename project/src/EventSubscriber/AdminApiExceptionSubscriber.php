<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/admin')) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        $errorCode = match (true) {
            $statusCode === Response::HTTP_UNAUTHORIZED => 'ADMIN_UNAUTHORIZED',
            $exception instanceof AccessDeniedException, $statusCode === Response::HTTP_FORBIDDEN => 'ADMIN_FORBIDDEN',
            $statusCode === Response::HTTP_UNPROCESSABLE_ENTITY => 'ADMIN_VALIDATION_ERROR',
            $statusCode === Response::HTTP_BAD_REQUEST => 'ADMIN_BAD_REQUEST',
            default => 'ADMIN_INTERNAL_ERROR',
        };

        $event->setResponse(new JsonResponse([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'An unexpected error occurred.',
            ],
        ], $statusCode));
    }
}
