<?php

namespace App\Controller\Admin\API;

use App\Entity\LocalUser;
use App\Repository\LocalUserRepository;
use App\Service\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Contrôleur API pour l'authentification JWT
 * 
 * Fournit des endpoints JSON pour l'authentification des applications mobiles et SPA
 */
#[Route('/api', name: 'api_')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JwtTokenService $jwtTokenService,
        private LocalUserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * Endpoint de login pour les applications mobiles/SPA
     * 
     * POST /api/login
     * Content-Type: application/json
     * 
     * Request:
     * {
     *   "username": "admin@obstack.local",
     *   "password": "password123"
     * }
     * 
     * Response (200 OK):
     * {
     *   "success": true,
     *   "data": {
     *     "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
     *     "refreshToken": "...",
     *     "expiresAt": "2026-07-02T12:00:00+00:00",
     *     "expiresIn": 3600
     *   },
     *   "user": {
     *     "id": 1,
     *     "email": "admin@obstack.local",
     *     "displayName": "Admin User"
     *   }
     * }
     * 
     * Response (401 Unauthorized):
     * {
     *   "success": false,
     *   "message": "Credentials invalides",
     *   "error": "invalid_credentials"
     * }
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Récupérer les données JSON
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Nom d\'utilisateur ou mot de passe manquant',
                    'error' => 'missing_credentials'
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $username = trim($data['username']);
        $password = trim($data['password']);

        // Chercher l'utilisateur par username ou email
        /** @var LocalUser|null $user */
        $user = $this->userRepository->findOneBy(['username' => $username]);
        
        // Si pas trouvé par username, chercher par email
        if (!$user) {
            $user = $this->userRepository->findOneBy(['email' => $username]);
        }

        // Vérifier que l'utilisateur existe, est actif, et que le mot de passe est correct
        if (!$user || !$user->isActive() || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Nom d\'utilisateur ou mot de passe invalide',
                    'error' => 'invalid_credentials'
                ],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        try {
            // Générer les tokens JWT
            $tokens = $this->jwtTokenService->generateToken($user);

            // Mettre à jour lastLoginAt
            $user->setLastLoginAt(new \DateTimeImmutable());
            $this->userRepository->save($user, true);

            return $this->json([
                'success' => true,
                'data' => $tokens,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'displayName' => $user->getDisplayName(),
                    'isGlobalAdmin' => $user->isGlobalAdmin(),
                    'roles' => $user->getRoles(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Erreur lors de la génération du token',
                    'error' => 'token_generation_error',
                    'details' => $e->getMessage()
                ],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Valide un token JWT
     * 
     * GET /api/validate-token
     * Authorization: Bearer <token>
     * 
     * Response (200 OK):
     * {
     *   "success": true,
     *   "user": {
     *     "id": 1,
     *     "email": "admin@obstack.local",
     *     "displayName": "Admin User",
     *     "isGlobalAdmin": true,
     *     "roles": ["ROLE_USER", "ROLE_ADMIN"]
     *   }
     * }
     * 
     * Response (401 Unauthorized):
     * {
     *   "success": false,
     *   "message": "Token invalide",
     *   "error": "invalid_token"
     * }
     */
    #[Route('/validate-token', name: 'validate_token', methods: ['GET'])]
    public function validateToken(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Token invalide ou expiré',
                    'error' => 'invalid_token'
                ],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'isGlobalAdmin' => in_array('ROLE_GLOBAL_ADMIN', $user->getRoles()),
                'roles' => $user->getRoles(),
            ]
        ]);
    }

    /**
     * Logout endpoint
     * 
     * POST /api/logout
     * Authorization: Bearer <token>
     * 
     * Response (200 OK):
     * {
     *   "success": true,
     *   "message": "Déconnexion réussie"
     * }
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        // Les tokens JWT sont stateless - pas besoin d'action spéciale
        // Le frontend supprimera simplement le token du localStorage

        if (!$user) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Pas d\'authentification active',
                    'error' => 'not_authenticated'
                ],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        return $this->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Renouvelle un token JWT avec un refresh token
     * 
     * POST /api/refresh-token
     * Content-Type: application/json
     * 
     * Request:
     * {
     *   "refreshToken": "..."
     * }
     * 
     * Response (200 OK):
     * {
     *   "success": true,
     *   "data": {
     *     "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
     *     "expiresAt": "2026-07-02T12:00:00+00:00",
     *     "expiresIn": 3600
     *   }
     * }
     * 
     * Response (401 Unauthorized):
     * {
     *   "success": false,
     *   "message": "Refresh token invalide",
     *   "error": "invalid_refresh_token"
     * }
     */
    #[Route('/refresh-token', name: 'refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['refreshToken'])) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Refresh token manquant',
                    'error' => 'missing_refresh_token'
                ],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        try {
            $decoded = $this->jwtTokenService->verifyRefreshToken($data['refreshToken']);
            
            return $this->json([
                'success' => false,
                'message' => 'Renouvellement de token non implémenté',
                'error' => 'not_implemented'
            ]);
        } catch (\Exception $e) {
            return $this->json(
                [
                    'success' => false,
                    'message' => 'Refresh token invalide: ' . $e->getMessage(),
                    'error' => 'invalid_refresh_token'
                ],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }
    }
}
