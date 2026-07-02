<?php

namespace App\Controller\Admin\API;

use App\Entity\DatabaseConnection;
use App\Service\AdminService;
use App\Service\AuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur API pour la gestion des connexions de bases de données.
 * 
 * Tous les endpoints requièrent le rôle ROLE_ADMIN et la permission admin.manage_database_connections.
 */
#[Route('/api/admin/database-connections', name: 'api_admin_database_connections_')]
#[IsGranted('ROLE_ADMIN')]
class DatabaseConnectionController extends AbstractController
{
    public function __construct(
        private AdminService $adminService,
        private AuditService $auditService,
    ) {
    }

    /**
     * Liste toutes les connexions de base de données.
     * 
     * GET /api/admin/database-connections
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        try {
            $connections = $this->adminService->getAllConnections();

            // Préparer les données (sans révéler le password)
            $data = array_map(function (DatabaseConnection $conn) {
                return [
                    'id' => $conn->getId(),
                    'name' => $conn->getName(),
                    'type' => $conn->getType(),
                    'host' => $conn->getHost(),
                    'port' => $conn->getPort(),
                    'database' => $conn->getDatabase(),
                    'username' => $conn->getUsername(),
                    'active' => $conn->isActive(),
                    'tested' => $conn->isTested(),
                    'lastTestedAt' => $conn->getLastTestedAt()?->format('c'),
                    'createdAt' => $conn->getCreatedAt()->format('c'),
                ];
            }, $connections);

            return $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des connexions: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère une connexion spécifique.
     * 
     * GET /api/admin/database-connections/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(DatabaseConnection $connection): JsonResponse
    {
        try {
            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $connection->getId(),
                    'name' => $connection->getName(),
                    'type' => $connection->getType(),
                    'host' => $connection->getHost(),
                    'port' => $connection->getPort(),
                    'database' => $connection->getDatabase(),
                    'username' => $connection->getUsername(),
                    'active' => $connection->isActive(),
                    'tested' => $connection->isTested(),
                    'lastTestedAt' => $connection->getLastTestedAt()?->format('c'),
                    'advancedOptions' => $connection->getAdvancedOptions() ?? [],
                    'createdAt' => $connection->getCreatedAt()->format('c'),
                    'updatedAt' => $connection->getUpdatedAt()->format('c'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crée une nouvelle connexion de base de données.
     * 
     * POST /api/admin/database-connections
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Valider les données requises
            $required = ['name', 'type', 'host', 'port', 'database', 'username', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->json([
                        'success' => false,
                        'error' => "Le champ '$field' est requis",
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Types autorisés
            $allowedTypes = ['mysql', 'postgresql', 'neo4j', 'arangodb'];
            if (!in_array($data['type'], $allowedTypes)) {
                return $this->json([
                    'success' => false,
                    'error' => "Type de base de données non supporté: {$data['type']}",
                ], Response::HTTP_BAD_REQUEST);
            }

            $connection = $this->adminService->createDatabaseConnection(
                $data['name'],
                $data['type'],
                $data['host'],
                (int)$data['port'],
                $data['database'],
                $data['username'],
                $data['password'],
                $data['advancedOptions'] ?? [],
            );

            return $this->json([
                'success' => true,
                'message' => 'Connexion créée avec succès',
                'data' => [
                    'id' => $connection->getId(),
                    'name' => $connection->getName(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la création: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour une connexion de base de données.
     * 
     * PUT /api/admin/database-connections/{id}
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(DatabaseConnection $connection, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $updated = $this->adminService->updateDatabaseConnection(
                $connection,
                $data['name'] ?? $connection->getName(),
                $data['host'] ?? $connection->getHost(),
                (int)($data['port'] ?? $connection->getPort()),
                $data['database'] ?? $connection->getDatabase(),
                $data['username'] ?? $connection->getUsername(),
                $data['password'] ?? null,
                $data['advancedOptions'] ?? [],
            );

            return $this->json([
                'success' => true,
                'message' => 'Connexion mise à jour',
                'data' => [
                    'id' => $updated->getId(),
                    'name' => $updated->getName(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime une connexion de base de données.
     * 
     * DELETE /api/admin/database-connections/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(DatabaseConnection $connection): JsonResponse
    {
        try {
            $this->adminService->deleteDatabaseConnection($connection);

            return $this->json([
                'success' => true,
                'message' => 'Connexion supprimée',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Teste une connexion de base de données.
     * 
     * POST /api/admin/database-connections/{id}/test
     */
    #[Route('/{id}/test', name: 'test', methods: ['POST'])]
    public function test(DatabaseConnection $connection): JsonResponse
    {
        try {
            $result = $this->adminService->testDatabaseConnection($connection);

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du test: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
