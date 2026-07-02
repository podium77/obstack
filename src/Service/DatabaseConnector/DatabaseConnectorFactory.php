<?php

namespace App\Service\DatabaseConnector;

use App\Entity\DatabaseConnection;
use Psr\Log\LoggerInterface;

/**
 * Fabrique pour créer les connecteurs de bases de données appropriés.
 * 
 * Respecte le principe Open/Closed - l'ajout d'un nouveau type
 * ne nécessite que l'ajout d'une implémentation et son enregistrement ici.
 */
class DatabaseConnectorFactory
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Créer un connecteur pour une connexion donnée.
     * 
     * @param DatabaseConnection $connection Configuration de connexion
     * 
     * @return DatabaseConnectorInterface
     * 
     * @throws \InvalidArgumentException Si le type de base est inconnu
     */
    public function create(DatabaseConnection $connection): DatabaseConnectorInterface
    {
        return match($connection->getType()) {
            DatabaseConnection::TYPE_MYSQL => new MysqlConnector($connection, $this->logger),
            DatabaseConnection::TYPE_POSTGRESQL => new PostgresqlConnector($connection, $this->logger),
            DatabaseConnection::TYPE_NEO4J => new Neo4jConnector($connection, $this->logger),
            DatabaseConnection::TYPE_ARANGODB => new ArangodbConnector($connection, $this->logger),
            default => throw new \InvalidArgumentException(
                sprintf('Type de base de données inconnu: %s', $connection->getType())
            ),
        };
    }

    /**
     * Obtenir la liste des types supportés.
     * 
     * @return array
     */
    public function getSupportedTypes(): array
    {
        return [
            DatabaseConnection::TYPE_MYSQL,
            DatabaseConnection::TYPE_POSTGRESQL,
            DatabaseConnection::TYPE_NEO4J,
            DatabaseConnection::TYPE_ARANGODB,
        ];
    }
}
