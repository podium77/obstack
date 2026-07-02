<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DatabaseConnection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for importing data from various formats (CSV, JSON) into databases
 */
class DataImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Import CSV data into a table
     *
     * @param DatabaseConnection $connection Database connection to import into
     * @param string $csvContent CSV file content
     * @param string $tableName Target table name
     * @param array<string, mixed> $options Import options
     * @return array<string, mixed> Import result with row count and details
     */
    public function importCsv(
        DatabaseConnection $connection,
        string $csvContent,
        string $tableName,
        array $options = []
    ): array {
        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = $options['hasHeader'] ?? true;
        $skipEmptyRows = $options['skipEmptyRows'] ?? true;
        $onDuplicate = $options['onDuplicate'] ?? 'skip'; // skip, update, error

        $lines = array_filter(explode("\n", trim($csvContent)));
        if (empty($lines)) {
            return [
                'success' => false,
                'error' => 'CSV file is empty',
                'rowsImported' => 0,
                'rowsSkipped' => 0,
            ];
        }

        $headers = null;
        $rowsImported = 0;
        $rowsSkipped = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($skipEmptyRows && empty($line)) {
                $rowsSkipped++;
                continue;
            }

            $values = str_getcsv($line, $delimiter);

            // First line is header
            if ($hasHeader && $headers === null) {
                $headers = array_map('trim', $values);
                continue;
            }

            if ($headers === null) {
                $errors[] = "No headers found in CSV (line {$lineNum})";
                continue;
            }

            try {
                $data = array_combine($headers, $values);
                if ($data === false) {
                    $rowsSkipped++;
                    continue;
                }

                // Insert or update based on strategy
                if ($this->insertOrUpdateRow($connection, $tableName, $data, $onDuplicate)) {
                    $rowsImported++;
                } else {
                    $rowsSkipped++;
                }
            } catch (\Exception $e) {
                $rowsSkipped++;
                $errors[] = "Line {$lineNum}: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'rowsImported' => $rowsImported,
            'rowsSkipped' => $rowsSkipped,
            'errors' => $errors,
            'totalRows' => $rowsImported + $rowsSkipped,
        ];
    }

    /**
     * Import JSON data into a table
     *
     * @param DatabaseConnection $connection Database connection to import into
     * @param string $jsonContent JSON content
     * @param string $tableName Target table name
     * @param array<string, mixed> $options Import options
     * @return array<string, mixed> Import result with row count and details
     */
    public function importJson(
        DatabaseConnection $connection,
        string $jsonContent,
        string $tableName,
        array $options = []
    ): array {
        try {
            $data = json_decode($jsonContent, true);
            if (!is_array($data)) {
                return [
                    'success' => false,
                    'error' => 'JSON must contain an array of objects',
                    'rowsImported' => 0,
                    'rowsSkipped' => 0,
                ];
            }

            $onDuplicate = $options['onDuplicate'] ?? 'skip';
            $rowsImported = 0;
            $rowsSkipped = 0;
            $errors = [];

            foreach ($data as $index => $row) {
                if (!is_array($row)) {
                    $rowsSkipped++;
                    $errors[] = "Row {$index} is not an object";
                    continue;
                }

                try {
                    if ($this->insertOrUpdateRow($connection, $tableName, $row, $onDuplicate)) {
                        $rowsImported++;
                    } else {
                        $rowsSkipped++;
                    }
                } catch (\Exception $e) {
                    $rowsSkipped++;
                    $errors[] = "Row {$index}: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'rowsImported' => $rowsImported,
                'rowsSkipped' => $rowsSkipped,
                'errors' => $errors,
                'totalRows' => $rowsImported + $rowsSkipped,
            ];
        } catch (\JsonException $e) {
            return [
                'success' => false,
                'error' => 'Invalid JSON: ' . $e->getMessage(),
                'rowsImported' => 0,
                'rowsSkipped' => 0,
            ];
        }
    }

    /**
     * Insert or update a single row in the database
     *
     * @param DatabaseConnection $connection Database connection
     * @param string $tableName Target table name
     * @param array<string, mixed> $data Row data
     * @param string $onDuplicate Strategy for duplicates: skip, update, error
     * @return bool True if row was inserted/updated, false if skipped
     */
    private function insertOrUpdateRow(
        DatabaseConnection $connection,
        string $tableName,
        array $data,
        string $onDuplicate = 'skip'
    ): bool {
        // Validate table name to prevent injection
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            throw new \InvalidArgumentException("Invalid table name: $tableName");
        }

        // Get connection from service
        $dbConnection = $this->getDbConnection($connection);

        try {
            // Try insert
            $dbConnection->insert($tableName, $data);
            return true;
        } catch (\Exception $e) {
            // Handle duplicate key error
            if (strpos($e->getMessage(), 'duplicate') !== false || 
                strpos($e->getMessage(), 'UNIQUE') !== false) {
                if ($onDuplicate === 'skip') {
                    return false;
                } elseif ($onDuplicate === 'update') {
                    try {
                        // Find primary key for update
                        $result = $dbConnection->executeQuery(
                            "SELECT column_name FROM information_schema.table_constraints 
                             WHERE table_name = ? AND constraint_type = 'PRIMARY KEY'",
                            [$tableName]
                        );
                        $pkColumn = $result->fetchOne();

                        if ($pkColumn && isset($data[$pkColumn])) {
                            $updateData = $data;
                            $pkValue = $updateData[$pkColumn];
                            unset($updateData[$pkColumn]);

                            $dbConnection->update(
                                $tableName,
                                $updateData,
                                [$pkColumn => $pkValue]
                            );
                            return true;
                        }
                        return false;
                    } catch (\Exception) {
                        return false;
                    }
                } else {
                    throw $e;
                }
            }
            throw $e;
        }
    }

    /**
     * Validate data before import
     *
     * @param array<string, mixed> $row Row data
     * @param array<string, mixed> $schema Table schema
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function validateRow(array $row, array $schema): array
    {
        $errors = [];

        foreach ($schema as $column => $columnDef) {
            $value = $row[$column] ?? null;

            // Check required fields
            if ($columnDef['nullable'] === false && ($value === null || $value === '')) {
                $errors[$column] = "Required field cannot be empty";
                continue;
            }

            // Type validation
            if ($value !== null && $value !== '') {
                if (!$this->validateType($value, $columnDef['type'])) {
                    $errors[$column] = "Invalid type for {$columnDef['type']}";
                }
            }

            // Length validation
            if (isset($columnDef['length']) && strlen((string)$value) > $columnDef['length']) {
                $errors[$column] = "Value exceeds maximum length of {$columnDef['length']}";
            }
        }

        return $errors;
    }

    /**
     * Validate value type
     */
    private function validateType(mixed $value, string $type): bool
    {
        return match ($type) {
            'integer', 'bigint', 'smallint' => is_numeric($value) && intval($value) == $value,
            'decimal', 'float', 'double' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0]),
            'date' => $this->isValidDate($value, 'Y-m-d'),
            'datetime', 'timestamp' => $this->isValidDate($value, 'Y-m-d H:i:s'),
            'time' => $this->isValidDate($value, 'H:i:s'),
            'string', 'text', 'varchar' => is_string($value),
            default => true,
        };
    }

    /**
     * Check if value is valid date format
     */
    private function isValidDate(string $date, string $format): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Get database connection for executing queries
     */
    private function getDbConnection(DatabaseConnection $connection): Connection
    {
        // For now, return current Doctrine connection
        // In production, would establish connection based on DatabaseConnection entity
        return $this->entityManager->getConnection();
    }
}
