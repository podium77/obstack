<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DatabaseConnection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for exporting data from databases in various formats (CSV, JSON, Excel)
 */
class DataExportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Export data to CSV format
     *
     * @param DatabaseConnection $connection Database connection to export from
     * @param string $tableName Source table name
     * @param array<string, mixed> $options Export options
     * @return string CSV content
     */
    public function exportToCsv(
        DatabaseConnection $connection,
        string $tableName,
        array $options = []
    ): string {
        $limit = $options['limit'] ?? 10000;
        $offset = $options['offset'] ?? 0;
        $delimiter = $options['delimiter'] ?? ',';
        $includeHeader = $options['includeHeader'] ?? true;
        $columns = $options['columns'] ?? null;
        $whereClause = $options['where'] ?? null;

        $dbConnection = $this->getDbConnection($connection);
        $data = $this->fetchData($dbConnection, $tableName, $limit, $offset, $columns, $whereClause);

        if (empty($data)) {
            return '';
        }

        $csv = '';

        // Add header
        if ($includeHeader) {
            $headers = array_keys($data[0]);
            $csv .= $this->escapeCsvLine($headers, $delimiter) . "\n";
        }

        // Add rows
        foreach ($data as $row) {
            $csv .= $this->escapeCsvLine($row, $delimiter) . "\n";
        }

        return $csv;
    }

    /**
     * Export data to JSON format
     *
     * @param DatabaseConnection $connection Database connection to export from
     * @param string $tableName Source table name
     * @param array<string, mixed> $options Export options
     * @return string JSON content
     */
    public function exportToJson(
        DatabaseConnection $connection,
        string $tableName,
        array $options = []
    ): string {
        $limit = $options['limit'] ?? 10000;
        $offset = $options['offset'] ?? 0;
        $pretty = $options['pretty'] ?? true;
        $columns = $options['columns'] ?? null;
        $whereClause = $options['where'] ?? null;

        $dbConnection = $this->getDbConnection($connection);
        $data = $this->fetchData($dbConnection, $tableName, $limit, $offset, $columns, $whereClause);

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    /**
     * Export data to JSONL format (JSON Lines - one object per line)
     *
     * @param DatabaseConnection $connection Database connection to export from
     * @param string $tableName Source table name
     * @param array<string, mixed> $options Export options
     * @return string JSONL content
     */
    public function exportToJsonL(
        DatabaseConnection $connection,
        string $tableName,
        array $options = []
    ): string {
        $limit = $options['limit'] ?? 10000;
        $offset = $options['offset'] ?? 0;
        $columns = $options['columns'] ?? null;
        $whereClause = $options['where'] ?? null;

        $dbConnection = $this->getDbConnection($connection);
        $data = $this->fetchData($dbConnection, $tableName, $limit, $offset, $columns, $whereClause);

        $jsonl = '';
        foreach ($data as $row) {
            $jsonl .= json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        }

        return $jsonl;
    }

    /**
     * Export data to Excel format (simplified - as CSV)
     *
     * @param DatabaseConnection $connection Database connection to export from
     * @param string $tableName Source table name
     * @param array<string, mixed> $options Export options
     * @return string CSV content (Excel-compatible)
     */
    public function exportToExcel(
        DatabaseConnection $connection,
        string $tableName,
        array $options = []
    ): string {
        // Add UTF-8 BOM for proper Excel encoding
        $csv = "\xEF\xBB\xBF";

        $options['delimiter'] = $options['delimiter'] ?? ';';
        $csv .= $this->exportToCsv($connection, $tableName, $options);

        return $csv;
    }

    /**
     * Export table structure/schema
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @return array<string, mixed> Table structure
     */
    public function exportTableStructure(
        DatabaseConnection $connection,
        string $tableName
    ): array {
        $dbConnection = $this->getDbConnection($connection);

        try {
            $schemaManager = $dbConnection->getSchemaManager();
            $table = $schemaManager->introspectTable($tableName);

            $columns = [];
            foreach ($table->getColumns() as $column) {
                $columns[] = [
                    'name' => $column->getName(),
                    'type' => $column->getType()->getName(),
                    'length' => $column->getLength(),
                    'precision' => $column->getPrecision(),
                    'scale' => $column->getScale(),
                    'nullable' => $column->getNotnull() === false,
                    'default' => $column->getDefault(),
                    'autoincrement' => $column->getAutoincrement(),
                ];
            }

            $indexes = [];
            foreach ($table->getIndexes() as $index) {
                $indexes[] = [
                    'name' => $index->getName(),
                    'columns' => $index->getColumns(),
                    'unique' => $index->isUnique(),
                    'primary' => $index->isPrimary(),
                ];
            }

            return [
                'name' => $tableName,
                'columns' => $columns,
                'indexes' => $indexes,
                'primaryKey' => $table->getPrimaryKey()?->getColumns(),
            ];
        } catch (\Exception $e) {
            return [
                'name' => $tableName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch data from database
     *
     * @param Connection $connection Database connection
     * @param string $tableName Table name
     * @param int $limit Row limit
     * @param int $offset Row offset
     * @param array<string>|null $columns Specific columns to fetch
     * @param string|null $whereClause WHERE clause
     * @return array<array<string, mixed>> Result rows
     */
    private function fetchData(
        Connection $connection,
        string $tableName,
        int $limit,
        int $offset,
        ?array $columns = null,
        ?string $whereClause = null
    ): array {
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            throw new \InvalidArgumentException("Invalid table name: $tableName");
        }

        $columnList = $columns ? implode(', ', array_map(
            fn($col) => preg_match('/^[a-zA-Z0-9_]+$/', $col) ? $col : throw new \InvalidArgumentException("Invalid column name"),
            $columns
        )) : '*';

        $sql = "SELECT {$columnList} FROM {$tableName}";

        if ($whereClause) {
            $sql .= " WHERE {$whereClause}";
        }

        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $result = $connection->executeQuery($sql);
        return $result->fetchAllAssociative();
    }

    /**
     * Escape CSV line
     *
     * @param array<mixed> $fields Field values
     * @param string $delimiter CSV delimiter
     * @return string Escaped CSV line
     */
    private function escapeCsvLine(array $fields, string $delimiter = ','): string
    {
        $escaped = [];
        foreach ($fields as $field) {
            $value = (string)($field ?? '');
            // Quote if contains delimiter, newline, or quote
            if (strpos($value, $delimiter) !== false || 
                strpos($value, "\n") !== false || 
                strpos($value, '"') !== false) {
                $value = '"' . str_replace('"', '""', $value) . '"';
            }
            $escaped[] = $value;
        }
        return implode($delimiter, $escaped);
    }

    /**
     * Get export statistics
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Table name
     * @return array<string, mixed> Statistics
     */
    public function getTableStats(
        DatabaseConnection $connection,
        string $tableName
    ): array {
        $dbConnection = $this->getDbConnection($connection);

        try {
            $result = $dbConnection->executeQuery("SELECT COUNT(*) as count FROM {$tableName}");
            $rowCount = $result->fetchOne() ?? 0;

            return [
                'tableName' => $tableName,
                'rowCount' => (int)$rowCount,
                'estimatedSize' => $this->estimateTableSize($dbConnection, $tableName),
            ];
        } catch (\Exception $e) {
            return [
                'tableName' => $tableName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Estimate table size in bytes
     */
    private function estimateTableSize(Connection $connection, string $tableName): int
    {
        try {
            $result = $connection->executeQuery(
                "SELECT pg_total_relation_size(?) as size",
                [$tableName],
                [\Doctrine\DBAL\Types\Types::STRING]
            );
            $size = $result->fetchOne();
            return (int)($size ?? 0);
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Get database connection for executing queries
     */
    private function getDbConnection(DatabaseConnection $connection): Connection
    {
        return $this->entityManager->getConnection();
    }
}
