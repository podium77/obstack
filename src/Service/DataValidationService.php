<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for data validation and conflict detection
 */
class DataValidationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Validate CSV data against table schema
     *
     * @param string $csvContent CSV content
     * @param Connection $connection Database connection
     * @param string $tableName Table name
     * @param array<string, mixed> $options Validation options
     * @return array<string, mixed> Validation result
     */
    public function validateCsvData(
        string $csvContent,
        Connection $connection,
        string $tableName,
        array $options = []
    ): array {
        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = $options['hasHeader'] ?? true;

        $lines = array_filter(explode("\n", trim($csvContent)));
        if (empty($lines)) {
            return [
                'valid' => false,
                'errors' => ['CSV file is empty'],
                'warnings' => [],
            ];
        }

        // Get table schema
        $schema = $this->getTableSchema($connection, $tableName);
        if (empty($schema)) {
            return [
                'valid' => false,
                'errors' => ["Table '{$tableName}' not found"],
                'warnings' => [],
            ];
        }

        $errors = [];
        $warnings = [];
        $headers = null;
        $lineNum = 0;

        foreach ($lines as $line) {
            $lineNum++;
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $values = str_getcsv($line, $delimiter);

            // First line is header
            if ($hasHeader && $headers === null) {
                $headers = array_map('trim', $values);

                // Validate headers against schema
                foreach ($headers as $header) {
                    if (!isset($schema[$header])) {
                        $warnings[] = "Line 1: Column '{$header}' not found in table schema";
                    }
                }
                continue;
            }

            if ($headers === null) {
                $errors[] = "No headers found in CSV";
                break;
            }

            // Validate row data
            $data = array_combine($headers, $values);
            if ($data === false) {
                $errors[] = "Line {$lineNum}: Column count mismatch";
                continue;
            }

            $rowErrors = $this->validateRow($data, $schema);
            if (!empty($rowErrors)) {
                foreach ($rowErrors as $column => $error) {
                    $errors[] = "Line {$lineNum}, Column '{$column}': {$error}";
                }
            }

            // Stop after checking first 100 rows for performance
            if ($lineNum > 100) {
                $warnings[] = "Validation limited to first 100 rows (found " . count($lines) . " total)";
                break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'linesChecked' => $lineNum,
        ];
    }

    /**
     * Detect duplicate records
     *
     * @param Connection $connection Database connection
     * @param string $tableName Table name
     * @param array<string, mixed> $row Row data
     * @param array<string> $matchColumns Columns to match
     * @return array<string, mixed> Duplicate detection result
     */
    public function detectDuplicates(
        Connection $connection,
        string $tableName,
        array $row,
        array $matchColumns
    ): array {
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            return ['found' => false, 'count' => 0];
        }

        try {
            $whereClause = [];
            foreach ($matchColumns as $column) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                    continue;
                }

                $value = $row[$column] ?? null;
                if ($value === null) {
                    $whereClause[] = "{$column} IS NULL";
                } else {
                    $whereClause[] = "{$column} = '" . str_replace("'", "''", $value) . "'";
                }
            }

            if (empty($whereClause)) {
                return ['found' => false, 'count' => 0];
            }

            $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE " . implode(' AND ', $whereClause);
            $result = $connection->executeQuery($sql);
            $count = (int)($result->fetchOne() ?? 0);

            return [
                'found' => $count > 0,
                'count' => $count,
                'matchColumns' => $matchColumns,
            ];
        } catch (\Exception $e) {
            return [
                'found' => false,
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate a single row against schema
     *
     * @param array<string, mixed> $row Row data
     * @param array<string, array<string, mixed>> $schema Table schema
     * @return array<string, string> Validation errors
     */
    private function validateRow(array $row, array $schema): array
    {
        $errors = [];

        foreach ($schema as $column => $columnDef) {
            $value = $row[$column] ?? null;

            // Check required fields
            if (!$columnDef['nullable'] && ($value === null || $value === '')) {
                $errors[$column] = "Required field is empty";
                continue;
            }

            // Type validation
            if ($value !== null && $value !== '') {
                if (!$this->isValidType($value, $columnDef['type'])) {
                    $errors[$column] = "Invalid type for {$columnDef['type']}";
                }
            }

            // Length validation
            if (isset($columnDef['length']) && $columnDef['length'] > 0) {
                if (strlen((string)$value) > $columnDef['length']) {
                    $errors[$column] = "Exceeds max length of {$columnDef['length']}";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if value matches type
     */
    private function isValidType(mixed $value, string $type): bool
    {
        return match ($type) {
            'integer', 'bigint', 'smallint' => is_numeric($value) && filter_var($value, FILTER_VALIDATE_INT) !== false,
            'decimal', 'float', 'double' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0]),
            'date' => $this->isValidDate((string)$value, 'Y-m-d'),
            'datetime', 'timestamp' => $this->isValidDate((string)$value, 'Y-m-d H:i:s'),
            'time' => $this->isValidDate((string)$value, 'H:i:s'),
            'string', 'text', 'varchar' => is_string($value),
            default => true,
        };
    }

    /**
     * Check if value is valid date
     */
    private function isValidDate(string $date, string $format): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Get table schema
     *
     * @param Connection $connection Database connection
     * @param string $tableName Table name
     * @return array<string, array<string, mixed>> Schema
     */
    private function getTableSchema(Connection $connection, string $tableName): array
    {
        try {
            $schemaManager = $connection->getSchemaManager();
            $table = $schemaManager->introspectTable($tableName);

            $schema = [];
            foreach ($table->getColumns() as $column) {
                $schema[$column->getName()] = [
                    'type' => $column->getType()->getName(),
                    'length' => $column->getLength(),
                    'nullable' => !$column->getNotnull(),
                    'default' => $column->getDefault(),
                ];
            }

            return $schema;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Analyze data quality
     *
     * @param Connection $connection Database connection
     * @param string $tableName Table name
     * @return array<string, mixed> Quality metrics
     */
    public function analyzeDataQuality(
        Connection $connection,
        string $tableName
    ): array {
        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $tableName)) {
            return ['error' => 'Invalid table name'];
        }

        try {
            $schema = $this->getTableSchema($connection, $tableName);
            $result = $connection->executeQuery("SELECT COUNT(*) as count FROM {$tableName}");
            $totalRows = (int)($result->fetchOne() ?? 0);

            $quality = [
                'totalRows' => $totalRows,
                'columns' => [
                    'total' => count($schema),
                    'nullable' => 0,
                    'withDefault' => 0,
                ],
                'nullability' => [],
            ];

            foreach ($schema as $column => $def) {
                if ($def['nullable']) {
                    $quality['columns']['nullable']++;
                }
                if ($def['default'] !== null) {
                    $quality['columns']['withDefault']++;
                }

                // Check NULL percentage
                try {
                    $nullResult = $connection->executeQuery(
                        "SELECT COUNT(*) as count FROM {$tableName} WHERE {$column} IS NULL"
                    );
                    $nullCount = (int)($nullResult->fetchOne() ?? 0);
                    $nullPercentage = $totalRows > 0 ? round(($nullCount / $totalRows) * 100, 2) : 0;

                    $quality['nullability'][$column] = [
                        'nullCount' => $nullCount,
                        'nullPercentage' => $nullPercentage,
                    ];
                } catch (\Exception) {
                    // Skip column if query fails
                }
            }

            return $quality;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
