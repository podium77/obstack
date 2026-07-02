<?php

namespace App\Service;

/**
 * Service de rate limiting pour les endpoints d'administration.
 * 
 * Limite les requêtes par IP et par utilisateur.
 */
class RateLimitService
{
    private array $limits = [];
    private const MAX_REQUESTS_PER_MINUTE = 100;
    private const MAX_REQUESTS_PER_STRICT = 10;

    /**
     * Vérifie si une requête est autorisée (rate limiting).
     * 
     * @param string $identifier IP ou user ID
     * @param int $limit Nombre de requêtes autorisées
     * @param int $interval Intervalle en secondes
     * 
     * @return array{allowed: bool, remaining: int, resetAt: int}
     */
    public function checkLimit(string $identifier, int $limit = 100, int $interval = 60): array
    {
        $now = time();
        $key = md5($identifier . ':' . floor($now / $interval));

        if (!isset($this->limits[$key])) {
            $this->limits[$key] = 0;
        }

        $this->limits[$key]++;
        $remaining = max(0, $limit - $this->limits[$key]);
        $resetAt = $now + $interval;

        return [
            'allowed' => $this->limits[$key] <= $limit,
            'remaining' => $remaining,
            'resetAt' => $resetAt,
        ];
    }

    /**
     * Limite stricte pour les opérations critiques.
     */
    public function checkStrictLimit(string $identifier): array
    {
        return $this->checkLimit($identifier, self::MAX_REQUESTS_PER_STRICT, 60);
    }

    /**
     * Limite relâchée pour les opérations courantes.
     */
    public function checkRelaxedLimit(string $identifier): array
    {
        return $this->checkLimit($identifier, self::MAX_REQUESTS_PER_MINUTE, 60);
    }

    /**
     * Nettoie les anciennes entrées (à appeler périodiquement).
     */
    public function cleanup(): void
    {
        $now = time();
        $cutoff = $now - 300; // 5 minutes

        $this->limits = array_filter($this->limits, function ($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
    }
}
