<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Écouteur pour capturer le contexte de la requête HTTP.
 * 
 * Enregistre l'adresse IP, la méthode HTTP, l'endpoint pour chaque requête
 * afin que les services d'audit puissent les utiliser.
 */
#[AsEventListener(event: 'kernel.request', method: 'onRequest')]
class RequestContextListener
{
    // Clés pour stocker le contexte dans le thread-local
    private static ?string $clientIp = null;
    private static ?string $userAgent = null;
    private static ?string $method = null;
    private static ?string $endpoint = null;

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Déterminer l'IP du client
        $clientIp = $this->getClientIp($request);
        self::$clientIp = $clientIp;

        // Capturer User-Agent
        self::$userAgent = $request->headers->get('User-Agent', '');

        // Capturer méthode et endpoint
        self::$method = $request->getMethod();
        self::$endpoint = $request->getPathInfo();
    }

    /**
     * Détermine l'IP du client, en tenant compte des proxies.
     */
    private function getClientIp(\Symfony\Component\HttpFoundation\Request $request): string
    {
        // Vérifier X-Forwarded-For (priorité haute car utilisé par les proxies)
        if ($forwarded = $request->headers->get('X-Forwarded-For')) {
            $ips = array_map('trim', explode(',', $forwarded));
            return $ips[0]; // Prendre la première IP (client original)
        }

        // Fallback sur X-Real-IP (utilisé par nginx)
        if ($realIp = $request->headers->get('X-Real-IP')) {
            return $realIp;
        }

        // Derniers recours: getClientIp() de Symfony
        return $request->getClientIp() ?? 'unknown';
    }

    /**
     * Récupère l'IP du client actuel.
     */
    public static function getClientIpFromContext(): string
    {
        return self::$clientIp ?? 'unknown';
    }

    /**
     * Récupère le User-Agent du client actuel.
     */
    public static function getUserAgentFromContext(): string
    {
        return self::$userAgent ?? '';
    }

    /**
     * Récupère la méthode HTTP actuelle.
     */
    public static function getMethodFromContext(): string
    {
        return self::$method ?? 'UNKNOWN';
    }

    /**
     * Récupère l'endpoint actuel.
     */
    public static function getEndpointFromContext(): string
    {
        return self::$endpoint ?? '/';
    }
}
