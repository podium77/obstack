<?php

namespace App\Service;

use App\Entity\LocalUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Service de gestion des tokens JWT
 */
class JwtTokenService
{
    private string $jwtSecret;
    private string $jwtAlgorithm = 'HS256';
    private int $tokenExpiry = 3600; // 1 heure
    private int $refreshTokenExpiry = 604800; // 7 jours

    public function __construct(
        string $jwtSecret,
        ?string $jwtAlgorithm = null,
        ?int $tokenExpiry = null,
    ) {
        $this->jwtSecret = $jwtSecret ?: 'dev-secret-key-change-in-production';
        if ($jwtAlgorithm) {
            $this->jwtAlgorithm = $jwtAlgorithm;
        }
        if ($tokenExpiry) {
            $this->tokenExpiry = $tokenExpiry;
        }
    }

    /**
     * Génère un token JWT pour l'utilisateur
     */
    public function generateToken(LocalUser $user): array
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $expiresAt = (clone $now)->modify("+{$this->tokenExpiry} seconds");

        $payload = [
            'iss' => 'obstack',
            'sub' => (string)$user->getId(),
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'roles' => $user->getRoles(),
        ];

        $token = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);

        return [
            'token' => $token,
            'refreshToken' => $this->generateRefreshToken($user),
            'expiresAt' => $expiresAt->format(\DateTime::ATOM),
            'expiresIn' => $this->tokenExpiry,
        ];
    }

    /**
     * Vérifie et décode un token JWT
     */
    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Token invalide: {$e->getMessage()}");
        }
    }

    /**
     * Génère un refresh token
     */
    private function generateRefreshToken(LocalUser $user): string
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $expiresAt = (clone $now)->modify("+{$this->refreshTokenExpiry} seconds");

        $payload = [
            'iss' => 'obstack',
            'sub' => (string)$user->getId(),
            'type' => 'refresh',
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ];

        return JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * Valide un refresh token
     */
    public function verifyRefreshToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));

            if (($decoded->type ?? null) !== 'refresh') {
                throw new \InvalidArgumentException('Token invalide: ce n\'est pas un refresh token');
            }

            return (array)$decoded;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Refresh token invalide: {$e->getMessage()}");
        }
    }

    /**
     * Génère seulement un token d'accès (pour le refresh)
     */
    public function generateAccessToken(LocalUser $user): array
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $expiresAt = (clone $now)->modify("+{$this->tokenExpiry} seconds");

        $payload = [
            'iss' => 'obstack',
            'sub' => (string)$user->getId(),
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'roles' => $user->getRoles(),
        ];

        $token = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt->format(\DateTime::ATOM),
            'expiresIn' => $this->tokenExpiry,
        ];
    }
}
