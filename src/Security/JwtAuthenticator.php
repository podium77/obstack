<?php

namespace App\Security;

use App\Service\JwtTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Authenticateur JWT pour les requêtes API
 * 
 * Valide les tokens Bearer JWT dans le header Authorization
 * Extrait l'ID utilisateur du token et le charge via UserProvider
 */
class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JwtTokenService $tokenService,
    ) {}

    /**
     * Vérifie si cette requête doit être authentifiée avec JWT
     */
    public function supports(Request $request): ?bool
    {
        // Vérifier si la requête a un header Authorization
        return $request->headers->has('Authorization');
    }

    /**
     * Authentifie la requête avec le token JWT
     */
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationException('En-tête Authorization invalide');
        }

        $token = substr($authHeader, 7); // Retirer "Bearer "

        try {
            $decoded = $this->tokenService->verifyToken($token);
            $userId = $decoded['sub'] ?? null;

            if (!$userId) {
                throw new AuthenticationException('Identifiant utilisateur manquant dans le token');
            }
        } catch (\Exception $e) {
            throw new AuthenticationException("Token JWT invalide: {$e->getMessage()}");
        }

        // Retourner un Passport qui charge l'utilisateur via UserProvider
        return new SelfValidatingPassport(
            new UserBadge($userId)
        );
    }

    /**
     * Appelé après une authentification réussie
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Laisser la requête continuer normalement
        return null;
    }

    /**
     * Appelé après une échec d'authentification
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Authentification échouée: ' . $exception->getMessageKey(),
            'error' => 'authentication_failed'
        ], Response::HTTP_UNAUTHORIZED);
    }
}
