<?php

namespace App\Service\DatabaseConnector;

use App\Entity\DatabaseConnection;
use Psr\Log\LoggerInterface;

/**
 * Connecteur pour les bases de données MySQL/MariaDB.
 */
class MysqlConnector extends AbstractDatabaseConnector
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
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->connection->getHost(),
                $this->connection->getPort(),
                $this->connection->getDatabase() ?? 'mysql'
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
            $this->logInfo('Connected to MySQL', ['host' => $this->connection->getHost()]);
        } catch (\PDOException $e) {
            $this->logError('Failed to connect to MySQL', ['error' => $e->getMessage()]);
            throw new \RuntimeException(sprintf('Connection failed: %s', $e->getMessage()), 0, $e);
        }
    }

    public function disconnect(): void
    {
        if ($this->connected) {
            $this->pdo = null;
            $this->dbConnection = null;
            $this->connected = false;
            $this->logInfo('Disconnected from MySQL');
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

        $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()";
        $stmt = $this->pdo->query($query);
        $tables = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $tables[] = $row['TABLE_NAME'];
        }

        return ['default' => $tables];
    }

    public function listData(string $structure, array $options = []): array
    {
        if (!$this->connected) {
            $this->connect();
        }

        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $orderBy = $options['orderBy'] ?? null;

        $query = "SELECT * FROM `$structure`";
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

        $columns = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $query = "INSERT INTO `$structure` ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    public function update(string $structure, array $criteria, array $data): int
    {
        if (!$this->connected) {
            $this->connect();
        }

        $setClause = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = ?", array_keys($criteria)));

        $query = "UPDATE `$structure` SET $setClause WHERE $whereClause";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute(array_merge(array_values($data), array_values($criteria)));

        return $stmt->rowCount();
    }

    public function delete(string $structure, array $criteria): int
    {
        if (!$this->connected) {
            $this->connect();
        }

        $whereClause = implode(' AND ', array_map(fn($k) => "`$k` = ?", array_keys($criteria)));
        $query = "DELETE FROM `$structure` WHERE $whereClause";

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

        try {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }
}
