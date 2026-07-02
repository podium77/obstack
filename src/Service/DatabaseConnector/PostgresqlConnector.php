<?php

namespace App\Service\DatabaseConnector;

use App\Entity\DatabaseConnection;
use Psr\Log\LoggerInterface;

/**
 * Connecteur pour les bases de données PostgreSQL.
 */
class PostgresqlConnector extends AbstractDatabaseConnector
{
    private \PDO $pdo;

    public function __construct(DatabaseConnection $connection, LoggerInterface $logger)
    {
        parent::__construct($connection, $logger);
    }

    public function connect(): void
    {
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->connection->getHost(),
                $this->connection->getPort(),
                $this->connection->getDatabase() ?? 'postgres'
            );

            $this->pdo = new \PDO(
                $dsn,
                $this->connection->getUsername(),
                $this->connection->getEncryptedPassword(),
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => $this->getConnectionTimeout(),
                ]
            );

            $this->dbConnection = $this->pdo;
            $this->connected = true;
            $this->logInfo('Connected to PostgreSQL', ['host' => $this->connection->getHost()]);
        } catch (\PDOException $e) {
            $this->logError('Failed to connect to PostgreSQL', ['error' => $e->getMessage()]);
            throw new \RuntimeException(sprintf('Connection failed: %s', $e->getMessage()), 0, $e);
        }
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            $this->pdo = null;
            $this->dbConnection = null;
            $this->connected = false;
            $this->logInfo('Disconnected from PostgreSQL');
        }
    }

    public function testConnection(): bool
    {
        try {
            if (!$this->connected) {
                $this->connect();
            }
            $result = $this->pdo->query('SELECT 1');
            return $result !== false;
        } catch (\Exception $e) {
            $this->logWarning('Connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function listStructures(): array
    {
        if (!$this->connected) {
            $this->connect();
        }

        $query = "
            SELECT schemaname as schema, tablename as table
            FROM pg_tables
            WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
            ORDER BY schemaname, tablename
        ";

        $stmt = $this->pdo->query($query);
        $structures = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $schema = $row['schema'];
            if (!isset($structures[$schema])) {
                $structures[$schema] = [];
            }
            $structures[$schema][] = $row['table'];
        }

        return $structures;
    }

    public function listData(string $structure, array $options = []): array
    {
        if (!$this->connected) {
            $this->connect();
        }

        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $orderBy = $options['orderBy'] ?? null;

        $query = "SELECT * FROM $structure";
        if ($orderBy) {
            $query .= " ORDER BY $orderBy";
        }
        $query .= " LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert(string $structure, array $data): mixed
    {
        if (!$this->connected) {
            $this->connect();
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $structure ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_values($data));

        // Retourner l'ID de la dernière ligne insérée
        return $this->pdo->lastInsertId();
    }

    public function update(string $structure, array $criteria, array $data): int
    {
        if (!$this->connected) {
            $this->connect();
        }

        $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($criteria)));

        $query = "UPDATE $structure SET $setClause WHERE $whereClause";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_merge(array_values($data), array_values($criteria)));

        return $stmt->rowCount();
    }

    public function delete(string $structure, array $criteria): int
    {
        if (!$this->connected) {
            $this->connect();
        }

        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($criteria)));
        $query = "DELETE FROM $structure WHERE $whereClause";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_values($criteria));

        return $stmt->rowCount();
    }

    public function executeQuery(string $query, array $params = []): mixed
    {
        if (!$this->connected) {
            $this->connect();
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        // Essayer d'obtenir les résultats
        try {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Peut retourner null pour les requêtes non-SELECT
            return null;
        }
    }
}
