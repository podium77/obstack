<?php

namespace App\EventListener;

use App\Service\RateLimitService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

/**
 * Écouteur pour appliquer le rate limiting aux endpoints sensibles.
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
class RateLimitListener
{
    public function __construct(
        private RateLimitService $rateLimitService,
        private LoggerInterface $logger,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Appliquer le rate limiting aux endpoints d'administration
        if (str_starts_with($path, '/api/admin/')) {
            $this->enforceRateLimit($event);
        }
    }

    private function enforceRateLimit(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $identifier = $this->getIdentifier($request);

        // Limites selon le type d'endpoint
        if (str_contains($request->getPathInfo(), 'query')) {
            // Requêtes personnalisées: limite stricte
            $limit = $this->rateLimitService->checkStrictLimit($identifier, 10, 60);
        } else {
            // Autres opérations: limite normale
            $limit = $this->rateLimitService->checkRelaxedLimit($identifier, 100, 60);
        }

        if (!$limit['allowed']) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'path' => $request->getPathInfo(),
                'reset_at' => $limit['resetAt'],
            ]);

            $response = new JsonResponse(
                [
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'retry_after' => $limit['resetAt'],
                ],
                429,
            );

            $response->headers->set('Retry-After', (string)$limit['resetAt']);
            $response->headers->set('X-RateLimit-Remaining', '0');

            $event->setResponse($response);
        }
    }

    private function getIdentifier(object $request): string
    {
        // Utiliser l'utilisateur authentifié si disponible
        // $request->getUser() peut ne pas être disponible en Symfony 7
        $user = null;
        try {
            $user = $request->getUser();
        } catch (\Exception $e) {
            // Ignorer si l'utilisateur n'est pas disponible
        }

        if ($user) {
            return 'user_' . $user->getUserIdentifier();
        }

        // Sinon, utiliser l'IP du client
        return 'ip_' . ($request->getClientIp() ?? 'unknown');
    }
}
