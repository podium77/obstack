<?php

namespace App\EventListener;

use App\Exception\AdminOperationException;
use App\Exception\DatabaseConnectionException;
use App\Exception\DatabaseQueryException;
use App\Exception\RbacException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Écouteur pour gérer les exceptions et les transformer en réponses JSON cohérentes.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Ignorer si ce n'est pas une requête API
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $this->createErrorResponse($exception);
        if ($response) {
            $event->setResponse($response);
        }
    }

    private function createErrorResponse(\Throwable $exception): ?JsonResponse
    {
        // Exceptions métier
        if ($exception instanceof AdminOperationException) {
            $this->logger->warning('Admin operation failed: ' . $exception->getMessage());
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $exception->getMessage(),
                ],
                400,
            );
        }

        if ($exception instanceof DatabaseConnectionException) {
            $this->logger->warning('Database connection failed: ' . $exception->getMessage());
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Erreur de connexion à la base de données',
                    'details' => $exception->getMessage(),
                ],
                400,
            );
        }

        if ($exception instanceof DatabaseQueryException) {
            $this->logger->warning('Database query failed: ' . $exception->getMessage());
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Erreur lors de l\'exécution de la requête',
                    'details' => $exception->getMessage(),
                ],
                400,
            );
        }

        if ($exception instanceof RbacException) {
            $this->logger->warning('RBAC check failed: ' . $exception->getMessage());
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Accès refusé',
                    'details' => $exception->getMessage(),
                ],
                403,
            );
        }

        // Validation errors
        if ($exception instanceof \Symfony\Component\Validator\Exception\ValidationFailedException) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Validation failed',
                    'violations' => $this->formatViolations($exception->getViolations()),
                ],
                400,
            );
        }

        // Logging pour autres exceptions
        $this->logger->error('Unhandled exception: ' . $exception->getMessage(), [
            'exception' => $exception,
        ]);

        return null;
    }

    private function formatViolations($violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        return $errors;
    }
}
