<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DatabaseConnection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for bulk operations (insert, update, delete)
 */
class BulkOperationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Bulk insert multiple rows
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @param array<array<string, mixed>> $rows Rows to insert
     * @param int $batchSize Batch size for chunking
     * @return array<string, mixed> Operation result
     */
    public function bulkInsert(
        DatabaseConnection $connection,
        string $tableName,
        array $rows,
        int $batchSize = 1000
    ): array {
        if (empty($rows)) {
            return [
                'success' => true,
                'inserted' => 0,
                'failed' => 0,
                'total' => 0,
                'errors' => [],
            ];
        }

        $dbConnection = $this->getDbConnection($connection);
        $inserted = 0;
        $failed = 0;
        $errors = [];

        // Process in batches
        $chunks = array_chunk($rows, $batchSize);

        foreach ($chunks as $chunk) {
            try {
                $dbConnection->beginTransaction();

                foreach ($chunk as $idx => $row) {
                    try {
                        $dbConnection->insert($tableName, $row);
                        $inserted++;
                    } catch (\Exception $e) {
                        $failed++;
                        $errors[] = "Row {$idx}: " . $e->getMessage();
                    }
                }

                $dbConnection->commit();
            } catch (\Exception $e) {
                $dbConnection->rollBack();
                $failed += count($chunk);
                $errors[] = "Batch error: " . $e->getMessage();
            }
        }

        return [
            'success' => $failed === 0,
            'inserted' => $inserted,
            'failed' => $failed,
            'total' => $inserted + $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Bulk update rows by condition
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @param array<string, mixed> $updateData Data to update
     * @param array<string, mixed> $conditions WHERE conditions
     * @return array<string, mixed> Operation result
     */
    public function bulkUpdate(
        DatabaseConnection $connection,
        string $tableName,
        array $updateData,
        array $conditions
    ): array {
        $dbConnection = $this->getDbConnection($connection);

        try {
            $affected = $dbConnection->update($tableName, $updateData, $conditions);

            return [
                'success' => true,
                'affected' => $affected,
                'message' => "{$affected} rows updated",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'affected' => 0,
            ];
        }
    }

    /**
     * Bulk delete rows by condition
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @param array<string, mixed> $conditions WHERE conditions
     * @param bool $confirm Safety confirmation
     * @return array<string, mixed> Operation result
     */
    public function bulkDelete(
        DatabaseConnection $connection,
        string $tableName,
        array $conditions,
        bool $confirm = false
    ): array {
        if (!$confirm) {
            return [
                'success' => false,
                'error' => 'Deletion requires explicit confirmation',
                'affectedEstimate' => 0,
            ];
        }

        $dbConnection = $this->getDbConnection($connection);

        try {
            $affected = $dbConnection->delete($tableName, $conditions);

            return [
                'success' => true,
                'affected' => $affected,
                'message' => "{$affected} rows deleted",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'affected' => 0,
            ];
        }
    }

    /**
     * Estimate rows affected by conditions
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @param array<string, mixed> $conditions WHERE conditions
     * @return int Estimated row count
     */
    public function estimateAffected(
        DatabaseConnection $connection,
        string $tableName,
        array $conditions
    ): int {
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            throw new \InvalidArgumentException("Invalid table name: $tableName");
        }

        $dbConnection = $this->getDbConnection($connection);

        try {
            $whereClause = $this->buildWhereClause($conditions);
            $sql = "SELECT COUNT(*) as count FROM {$tableName}";

            if ($whereClause) {
                $sql .= " WHERE {$whereClause}";
            }

            $result = $dbConnection->executeQuery($sql);
            return (int)($result->fetchOne() ?? 0);
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Truncate table (delete all rows)
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @param bool $confirm Safety confirmation
     * @return array<string, mixed> Operation result
     */
    public function truncateTable(
        DatabaseConnection $connection,
        string $tableName,
        bool $confirm = false
    ): array {
        if (!$confirm) {
            return [
                'success' => false,
                'error' => 'Truncate requires explicit confirmation',
            ];
        }

        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            return [
                'success' => false,
                'error' => 'Invalid table name',
            ];
        }

        $dbConnection = $this->getDbConnection($connection);

        try {
            $dbConnection->executeStatement("TRUNCATE TABLE {$tableName}");

            return [
                'success' => true,
                'message' => "Table {$tableName} truncated",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build WHERE clause from conditions
     */
    private function buildWhereClause(array $conditions): string
    {
        $parts = [];
        foreach ($conditions as $column => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name: $column");
            }

            if ($value === null) {
                $parts[] = "{$column} IS NULL";
            } else {
                $parts[] = "{$column} = " . (is_numeric($value) ? $value : "'{$value}'");
            }
        }

        return implode(' AND ', $parts);
    }

    /**
     * Get database connection for executing queries
     */
    private function getDbConnection(DatabaseConnection $connection): Connection
    {
        return $this->entityManager->getConnection();
    }
}
