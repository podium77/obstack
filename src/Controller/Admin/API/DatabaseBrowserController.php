<?php

namespace App\Controller\Admin\API;

use App\Entity\DatabaseConnection;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur API pour l'exploration et l'interrogation des bases de données.
 * 
 * Tous les endpoints requièrent le rôle ROLE_ADMIN et la permission admin.execute_queries.
 */
#[Route('/api/admin/database', name: 'api_admin_database_')]
#[IsGranted('ROLE_ADMIN')]
class DatabaseBrowserController extends AbstractController
{
    public function __construct(
        private AdminService $adminService,
    ) {
    }

    /**
     * Liste les structures (schémas/tables/collections) d'une base de données.
     * 
     * GET /api/admin/database/{id}/structures
     */
    #[Route('/{id}/structures', name: 'structures', methods: ['GET'])]
    public function structures(DatabaseConnection $connection): JsonResponse
    {
        try {
            $result = $this->adminService->listDatabaseStructures($connection);

            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Erreur inconnue',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => $result['structures'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Liste les données d'une table/collection avec pagination.
     * 
     * GET /api/admin/database/{id}/data?structure=users&limit=50&offset=0
     */
    #[Route('/{id}/data', name: 'data', methods: ['GET'])]
    public function data(DatabaseConnection $connection, Request $request): JsonResponse
    {
        try {
            $structure = $request->query->get('structure');
            if (empty($structure)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Le paramètre "structure" est requis',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Valider le nom de structure (prévenir les injections)
            if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $structure)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Nom de structure invalide',
                ], Response::HTTP_BAD_REQUEST);
            }

            $limit = (int)$request->query->get('limit', 50);
            $offset = (int)$request->query->get('offset', 0);

            $result = $this->adminService->listDatabaseData(
                $connection,
                $structure,
                [
                    'limit' => min($limit, 1000),
                    'offset' => $offset,
                ],
            );

            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Erreur inconnue',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => $result['data'],
                'metadata' => $result['metadata'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Exécute une requête SQL/Cypher/AQL personnalisée sur la base de données.
     * 
     * POST /api/admin/database/{id}/query
     * 
     * Body JSON:
     * {
     *   "query": "SELECT * FROM users WHERE id = ?",
     *   "params": [123]
     * }
     */
    #[Route('/{id}/query', name: 'query', methods: ['POST'])]
    public function query(DatabaseConnection $connection, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['query'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Le champ "query" est requis',
                ], Response::HTTP_BAD_REQUEST);
            }

            $query = $data['query'];
            $params = $data['params'] ?? [];

            // Sécurité: empêcher les opérations dangereuses sur base de production
            // (Cette vérification peut être étendue selon vos politiques)
            if ($connection->getDatabase() === 'production') {
                $dangerousOps = ['DROP', 'TRUNCATE', 'DELETE', 'ALTER'];
                foreach ($dangerousOps as $op) {
                    if (stripos($query, $op) !== false) {
                        return $this->json([
                            'success' => false,
                            'error' => 'Opérations destructrices interdites sur la base de données de production',
                        ], Response::HTTP_FORBIDDEN);
                    }
                }
            }

            $result = $this->adminService->executeQuery($connection, $query, $params);

            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Erreur inconnue',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => $result['results'] ?? null,
                'affectedRows' => $result['affectedRows'] ?? null,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
