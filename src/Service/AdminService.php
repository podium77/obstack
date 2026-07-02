<?php

namespace App\Service;

use App\Entity\DatabaseConnection;
use App\Repository\DatabaseConnectionRepository;
use App\Service\DatabaseConnector\DatabaseConnectorFactory;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour les opérations d'administration de la base de données.
 * 
 * Gère les connexions de bases de données externes, l'exploration des structures,
 * et l'exécution de requêtes (admin uniquement).
 */
class AdminService
{
    public function __construct(
        private DatabaseConnectionRepository $connectionRepository,
        private DatabaseConnectorFactory $connectorFactory,
        private PasswordEncryptionService $passwordEncryption,
        private EntityManagerInterface $em,
        private AuditService $auditService,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Crée une nouvelle connexion de base de données.
     */
    public function createDatabaseConnection(
        string $name,
        string $type,
        string $host,
        int $port,
        string $database,
        string $username,
        string $plainPassword,
        array $advancedOptions = [],
    ): DatabaseConnection {
        $connection = new DatabaseConnection();
        $connection->setName($name);
        $connection->setType($type);
        $connection->setHost($host);
        $connection->setPort($port);
        $connection->setDatabase($database);
        $connection->setUsername($username);

        // Chiffrer le mot de passe
        $encryptedPassword = $this->passwordEncryption->encrypt($plainPassword);
        $connection->setEncryptedPassword($encryptedPassword);

        if (!empty($advancedOptions)) {
            $connection->setAdvancedOptions($advancedOptions);
        }

        $this->em->persist($connection);
        $this->em->flush();

        // Audit
        $this->auditService->log(
            'database_connection_created',
            'DatabaseConnection',
            $connection->getId(),
            "Nouvelle connexion de base de données: $name ($type://$host:$port/$database)",
            'success',
        );

        return $connection;
    }

    /**
     * Met à jour une connexion de base de données.
     */
    public function updateDatabaseConnection(
        DatabaseConnection $connection,
        string $name,
        string $host,
        int $port,
        string $database,
        string $username,
        ?string $plainPassword = null,
        array $advancedOptions = [],
    ): DatabaseConnection {
        $old = [
            'name' => $connection->getName(),
            'host' => $connection->getHost(),
            'port' => $connection->getPort(),
            'database' => $connection->getDatabase(),
            'username' => $connection->getUsername(),
        ];

        $connection->setName($name);
        $connection->setHost($host);
        $connection->setPort($port);
        $connection->setDatabase($database);
        $connection->setUsername($username);

        if ($plainPassword !== null) {
            $encryptedPassword = $this->passwordEncryption->encrypt($plainPassword);
            $connection->setEncryptedPassword($encryptedPassword);
        }

        if (!empty($advancedOptions)) {
            $connection->setAdvancedOptions($advancedOptions);
        }

        $this->em->flush();

        // Audit
        $new = [
            'name' => $connection->getName(),
            'host' => $connection->getHost(),
            'port' => $connection->getPort(),
            'database' => $connection->getDatabase(),
            'username' => $connection->getUsername(),
        ];

        $this->auditService->log(
            'database_connection_updated',
            'DatabaseConnection',
            $connection->getId(),
            'Connexion de base de données mise à jour',
            'success',
            $old,
            $new,
        );

        return $connection;
    }

    /**
     * Supprime une connexion de base de données.
     */
    public function deleteDatabaseConnection(DatabaseConnection $connection): void
    {
        $connectionData = [
            'name' => $connection->getName(),
            'type' => $connection->getType(),
            'host' => $connection->getHost(),
            'database' => $connection->getDatabase(),
        ];

        $this->em->remove($connection);
        $this->em->flush();

        // Audit
        $this->auditService->log(
            'database_connection_deleted',
            'DatabaseConnection',
            $connection->getId(),
            'Connexion de base de données supprimée: ' . $connection->getName(),
            'success',
            $connectionData,
            [],
        );
    }

    /**
     * Teste une connexion de base de données.
     * 
     * @return array{success: bool, message: string, error?: string}
     */
    public function testDatabaseConnection(DatabaseConnection $connection): array
    {
        try {
            $connector = $this->connectorFactory->create($connection);
            $connector->connect();
            $success = $connector->testConnection();
            $connector->disconnect();

            if ($success) {
                // Marquer comme testée avec succès
                $connection->setLastTestedAt(new \DateTimeImmutable());
                $connection->setTested(true);
                $this->em->flush();

                // Audit
                $this->auditService->log(
                    'database_connection_tested',
                    'DatabaseConnection',
                    $connection->getId(),
                    'Test de connexion réussi',
                    'success',
                );

                return [
                    'success' => true,
                    'message' => 'Connexion testée avec succès',
                ];
            }

            return [
                'success' => false,
                'message' => 'Erreur de connexion',
                'error' => 'La connexion n\'a pas pu être établie',
            ];
        } catch (\Exception $e) {
            // Audit de l'échec
            $this->auditService->log(
                'database_connection_tested',
                'DatabaseConnection',
                $connection->getId(),
                'Échec du test de connexion',
                'failure',
                errorMessage: $e->getMessage(),
            );

            $this->logger->error('Database connection test failed', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors du test de connexion',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Liste les structures (schémas/tables/collections) d'une base de données.
     * 
     * @return array{success: bool, structures?: array, error?: string}
     */
    public function listDatabaseStructures(DatabaseConnection $connection): array
    {
        try {
            $connector = $this->connectorFactory->create($connection);
            $connector->connect();
            $structures = $connector->listStructures();
            $connector->disconnect();

            // Audit
            $this->auditService->log(
                'database_structures_listed',
                'DatabaseConnection',
                $connection->getId(),
                'Énumération des structures de base de données',
                'success',
                metadata: ['structure_count' => count($structures)],
            );

            return [
                'success' => true,
                'structures' => $structures,
            ];
        } catch (\Exception $e) {
            // Audit de l'erreur
            $this->auditService->log(
                'database_structures_listed',
                'DatabaseConnection',
                $connection->getId(),
                'Erreur lors de l\'énumération des structures',
                'failure',
                errorMessage: $e->getMessage(),
            );

            $this->logger->error('Failed to list database structures', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Liste les données d'une table/collection.
     * 
     * @return array{success: bool, data?: array, metadata?: array, error?: string}
     */
    public function listDatabaseData(
        DatabaseConnection $connection,
        string $structure,
        array $options = [],
    ): array {
        try {
            // Options par défaut
            $limit = (int)($options['limit'] ?? 50);
            $offset = (int)($options['offset'] ?? 0);
            $orderBy = $options['orderBy'] ?? null;

            if ($limit > 1000) {
                $limit = 1000; // Protection contre les requêtes trop gourmandes
            }

            $connector = $this->connectorFactory->create($connection);
            $connector->connect();
            $data = $connector->listData($structure, [
                'limit' => $limit,
                'offset' => $offset,
                'orderBy' => $orderBy,
            ]);
            $connector->disconnect();

            // Audit
            $this->auditService->log(
                'database_data_read',
                'DatabaseConnection',
                $connection->getId(),
                "Lecture des données de $structure",
                'success',
                metadata: [
                    'structure' => $structure,
                    'limit' => $limit,
                    'offset' => $offset,
                    'rows_returned' => count($data),
                ],
            );

            return [
                'success' => true,
                'data' => $data,
                'metadata' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($data),
                ],
            ];
        } catch (\Exception $e) {
            // Audit de l'erreur
            $this->auditService->log(
                'database_data_read',
                'DatabaseConnection',
                $connection->getId(),
                "Erreur lors de la lecture de $structure",
                'failure',
                errorMessage: $e->getMessage(),
            );

            $this->logger->error('Failed to read database data', [
                'connection_id' => $connection->getId(),
                'structure' => $structure,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Exécute une requête arbitraire sur la base de données (ADMIN ONLY).
     * 
     * @return array{success: bool, results?: array, affectedRows?: int, error?: string}
     */
    public function executeQuery(
        DatabaseConnection $connection,
        string $query,
        array $params = [],
    ): array {
        try {
            $connector = $this->connectorFactory->create($connection);
            $connector->connect();
            $result = $connector->executeQuery($query, $params);
            $connector->disconnect();

            // Audit de l'exécution
            $this->auditService->log(
                'database_query_executed',
                'DatabaseConnection',
                $connection->getId(),
                'Exécution de requête personnalisée',
                'success',
                metadata: [
                    'query' => substr($query, 0, 200), // Limiter la taille loggée
                    'param_count' => count($params),
                ],
            );

            // Déterminer si c'est un SELECT ou une opération de modification
            $upper = strtoupper(trim($query));
            if (str_starts_with($upper, 'SELECT')) {
                return [
                    'success' => true,
                    'results' => $result,
                ];
            }

            return [
                'success' => true,
                'affectedRows' => $result,
            ];
        } catch (\Exception $e) {
            // Audit de l'erreur
            $this->auditService->log(
                'database_query_executed',
                'DatabaseConnection',
                $connection->getId(),
                'Erreur lors de l\'exécution de requête',
                'failure',
                errorMessage: $e->getMessage(),
            );

            $this->logger->error('Query execution failed', [
                'connection_id' => $connection->getId(),
                'query' => substr($query, 0, 200),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Récupère toutes les connexions de base de données.
     * 
     * @return DatabaseConnection[]
     */
    public function getAllConnections(): array
    {
        return $this->connectionRepository->findAll();
    }

    /**
     * Récupère une connexion par ID.
     */
    public function getConnection(int $id): ?DatabaseConnection
    {
        return $this->connectionRepository->find($id);
    }

    /**
     * Récupère les connexions par type.
     * 
     * @return DatabaseConnection[]
     */
    public function getConnectionsByType(string $type): array
    {
        return $this->connectionRepository->findByType($type);
    }

    /**
     * Récupère les connexions testées avec succès.
     * 
     * @return DatabaseConnection[]
     */
    public function getTestedConnections(): array
    {
        return $this->connectionRepository->findTested();
    }
}
