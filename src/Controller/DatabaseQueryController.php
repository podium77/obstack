<?php

namespace App\Controller;

use App\Entity\DatabaseConnection;
use App\Repository\DatabaseConnectionRepository;
use App\Service\PasswordEncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur API pour le Query Builder
 * Permet de construire et exécuter des requêtes SQL visuellement
 */
#[Route('/api/database', name: 'api_database_')]
class DatabaseQueryController extends AbstractController
{
    public function __construct(
        private DatabaseConnectionRepository $connectionRepository,
        private PasswordEncryptionService $passwordEncryption,
    ) {
    }

    /**
     * Liste toutes les connexions de base de données accessibles
     * 
     * GET /api/database/connections
     */
    #[Route('/connections', name: 'connections_list', methods: ['GET'])]
    public function listConnections(): JsonResponse
    {
        try {
            $connections = $this->connectionRepository->findBy(['active' => true]);

            $data = array_map(function (DatabaseConnection $conn) {
                return [
                    'id' => $conn->getId(),
                    'name' => $conn->getName(),
                    'type' => $conn->getType(),
                    'host' => $conn->getHost(),
                    'port' => $conn->getPort(),
                    'database' => $conn->getDatabase(),
                ];
            }, $connections);

            return $this->json($data);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des connexions',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les tables d'une connexion
     * 
     * GET /api/database/{connectionId}/tables
     */
    #[Route('/{connectionId}/tables', name: 'tables_list', methods: ['GET'])]
    public function listTables(int $connectionId): JsonResponse
    {
        try {
            $connection = $this->connectionRepository->find($connectionId);
            
            if (!$connection || !$connection->isActive()) {
                return $this->json([
                    'error' => 'Connexion non trouvée ou inactive'
                ], Response::HTTP_NOT_FOUND);
            }

            $pdo = $this->getDatabaseConnection($connection);
            
            $tables = match ($connection->getType()) {
                'mysql' => $this->getMysqlTables($pdo, $connection->getDatabase()),
                'postgresql' => $this->getPostgresqlTables($pdo),
                'sqlite' => $this->getSqliteTables($pdo),
                default => throw new \Exception("Type de base de données non supporté: {$connection->getType()}")
            };

            return $this->json([
                'tables' => $tables,
                'count' => count($tables),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération des tables',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère la structure d'une table (colonnes et types)
     * 
     * GET /api/database/{connectionId}/table/{tableName}/structure
     */
    #[Route('/{connectionId}/table/{tableName}/structure', name: 'table_structure', methods: ['GET'])]

    public function getTableStructure(int $connectionId, string $tableName): JsonResponse
    {
        try {
            $connection = $this->connectionRepository->find($connectionId);
            
            if (!$connection || !$connection->isActive()) {
                return $this->json([
                    'error' => 'Connexion non trouvée ou inactive'
                ], Response::HTTP_NOT_FOUND);
            }

            $pdo = $this->getDatabaseConnection($connection);
            
            $columns = match ($connection->getType()) {
                'mysql' => $this->getMysqlTableColumns($pdo, $connection->getDatabase(), $tableName),
                'postgresql' => $this->getPostgresqlTableColumns($pdo, $tableName),
                'sqlite' => $this->getSqliteTableColumns($pdo, $tableName),
                default => throw new \Exception("Type de base de données non supporté")
            };

            return $this->json([
                'table' => $tableName,
                'columns' => $columns,
                'count' => count($columns),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération de la structure',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Exécute une requête SQL générée par le Query Builder
     * 
     * POST /api/database/query/execute
     * Body: { connectionId: int, query: string }
     */
    #[Route('/query/execute', name: 'query_execute', methods: ['POST'])]
    public function executeQuery(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['connectionId']) || !isset($data['query'])) {
                return $this->json([
                    'error' => 'Paramètres manquants: connectionId et query requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $connectionId = (int)$data['connectionId'];
            $query = trim($data['query']);

            // Validation basique de la requête
            if (!preg_match('/^SELECT\s/i', $query)) {
                return $this->json([
                    'error' => 'Seules les requêtes SELECT sont autorisées'
                ], Response::HTTP_BAD_REQUEST);
            }

            $connection = $this->connectionRepository->find($connectionId);
            
            if (!$connection || !$connection->isActive()) {
                return $this->json([
                    'error' => 'Connexion non trouvée ou inactive'
                ], Response::HTTP_NOT_FOUND);
            }

            $pdo = $this->getDatabaseConnection($connection);
            
            // Exécuter la requête avec une limite de sécurité
            $statement = $pdo->query($query . ' LIMIT 1000');
            $results = $statement->fetchAll(\PDO::FETCH_ASSOC);

            return $this->json([
                'results' => $results,
                'count' => count($results),
                'query' => $query,
            ]);
        } catch (\PDOException $e) {
            return $this->json([
                'error' => 'Erreur SQL',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'exécution de la requête',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Méthodes privées pour la gestion des bases de données
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Crée une connexion PDO à la base de données
     */
    private function getDatabaseConnection(DatabaseConnection $dbConnection): \PDO
    {
        $dsn = match ($dbConnection->getType()) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $dbConnection->getHost(),
                $dbConnection->getPort(),
                $dbConnection->getDatabase()
            ),
            'postgresql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $dbConnection->getHost(),
                $dbConnection->getPort(),
                $dbConnection->getDatabase()
            ),
            'sqlite' => sprintf('sqlite:%s', $dbConnection->getHost()),
            default => throw new \Exception("Type de base de données non supporté")
        };

        $password = $dbConnection->getEncryptedPassword()
            ? $this->passwordEncryption->decrypt($dbConnection->getEncryptedPassword())
            : '';

        return new \PDO(
            $dsn,
            $dbConnection->getUsername(),
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Récupère les tables MySQL
     */
    private function getMysqlTables(\PDO $pdo, string $database): array
    {
        $statement = $pdo->prepare(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ?"
        );
        $statement->execute([$database]);
        $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map(fn($row) => $row['TABLE_NAME'], $tables);
    }

    /**
     * Récupère les tables PostgreSQL
     */
    private function getPostgresqlTables(\PDO $pdo): array
    {
        $statement = $pdo->query(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
        );
        
        $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($row) => $row['tablename'], $tables);
    }

    /**
     * Récupère les tables SQLite
     */
    private function getSqliteTables(\PDO $pdo): array
    {
        $statement = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
        
        $tables = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($row) => $row['name'], $tables);
    }

    /**
     * Récupère les colonnes d'une table MySQL
     */
    private function getMysqlTableColumns(\PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            "SELECT COLUMN_NAME as name, COLUMN_TYPE as type FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table"
        );
        
        $statement->execute([':db' => $database, ':table' => $table]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les colonnes d'une table PostgreSQL
     */
    private function getPostgresqlTableColumns(\PDO $pdo, string $table): array
    {
        $statement = $pdo->prepare(
            "SELECT column_name as name, data_type as type FROM information_schema.columns 
             WHERE table_schema = 'public' AND table_name = :table ORDER BY ordinal_position"
        );
        
        $statement->execute([':table' => $table]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les colonnes d'une table SQLite
     */
    private function getSqliteTableColumns(\PDO $pdo, string $table): array
    {
        $statement = $pdo->query("PRAGMA table_info($table)");
        $columns = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map(fn($col) => [
            'name' => $col['name'],
            'type' => $col['type']
        ], $columns);
    }

    /**
     * Récupère le schéma complet avec tables, colonnes et relations
     * 
     * GET /api/database/{connectionId}/schema
     */
    #[Route('/{connectionId}/schema', name: 'schema', methods: ['GET'])]
    public function getSchema(int $connectionId): JsonResponse
    {
        try {
            $connection = $this->connectionRepository->find($connectionId);
            
            if (!$connection || !$connection->isActive()) {
                return $this->json([
                    'error' => 'Connexion non trouvée ou inactive'
                ], Response::HTTP_NOT_FOUND);
            }

            $pdo = $this->getDatabaseConnection($connection);
            
            // Récupérer les tables
            $tables = match ($connection->getType()) {
                'mysql' => $this->getMysqlTables($pdo, $connection->getDatabase()),
                'postgresql' => $this->getPostgresqlTables($pdo),
                'sqlite' => $this->getSqliteTables($pdo),
                default => throw new \Exception("Type non supporté")
            };

            // Pour chaque table, récupérer colonnes et relations
            $schema = [];
            foreach ($tables as $tableName) {
                $columns = match ($connection->getType()) {
                    'mysql' => $this->getMysqlTableColumns($pdo, $connection->getDatabase(), $tableName),
                    'postgresql' => $this->getPostgresqlTableColumns($pdo, $tableName),
                    'sqlite' => $this->getSqliteTableColumns($pdo, $tableName),
                    default => []
                };

                $relations = match ($connection->getType()) {
                    'mysql' => $this->getMysqlTableRelations($pdo, $connection->getDatabase(), $tableName),
                    'postgresql' => $this->getPostgresqlTableRelations($pdo, $tableName),
                    'sqlite' => $this->getSqliteTableRelations($pdo, $tableName),
                    default => []
                };

                $schema[$tableName] = [
                    'name' => $tableName,
                    'columns' => $columns,
                    'relations' => $relations,
                ];
            }

            return $this->json([
                'tables' => $schema,
                'count' => count($schema),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération du schéma',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les relations (clés étrangères) d'une table MySQL
     */
    private function getMysqlTableRelations(\PDO $pdo, string $database, string $table): array
    {
        $statement = $pdo->prepare(
            "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND REFERENCED_TABLE_NAME IS NOT NULL"
        );
        
        $statement->execute([':db' => $database, ':table' => $table]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les relations (clés étrangères) d'une table PostgreSQL
     */
    private function getPostgresqlTableRelations(\PDO $pdo, string $table): array
    {
        $statement = $pdo->prepare(
            "SELECT 
                a.attname as column_name,
                c.relname as referenced_table_name,
                af.attname as referenced_column_name
            FROM pg_attribute a
            JOIN pg_class t ON a.attrelid = t.oid
            JOIN pg_constraint fk ON fk.conrelid = t.oid AND a.attnum = ANY(fk.conkey)
            JOIN pg_class c ON fk.confrelid = c.oid
            JOIN pg_attribute af ON af.attrelid = c.oid AND af.attnum = ANY(fk.confkey)
            WHERE t.relname = :table AND t.relkind = 'r'"
        );
        
        $statement->execute([':table' => $table]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les relations (clés étrangères) d'une table SQLite
     */
    private function getSqliteTableRelations(\PDO $pdo, string $table): array
    {
        $statement = $pdo->query("PRAGMA foreign_key_list($table)");
        $relations = $statement->fetchAll(\PDO::FETCH_ASSOC);
        
        return array_map(fn($rel) => [
            'column_name' => $rel['from'],
            'referenced_table_name' => $rel['table'],
            'referenced_column_name' => $rel['to'],
        ], $relations);
    }
}
