<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for archiving and managing audit logs
 */
class AuditArchiveService
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Archive old audit logs
     *
     * @param int $retentionDays Number of days to retain
     * @return array<string, mixed> Archive result
     */
    public function archiveOldLogs(int $retentionDays = 90): array
    {
        try {
            $archiveDate = (new \DateTime())->modify("-{$retentionDays} days");
            
            // Get logs to archive
            $stmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM audit_logs WHERE created_at < ?',
                [$archiveDate]
            );
            $result = $stmt->fetchAssociative();
            $logsToArchive = $result['count'] ?? 0;

            if ($logsToArchive === 0) {
                return [
                    'success' => true,
                    'message' => 'No logs to archive',
                    'archived' => 0,
                ];
            }

            // Copy to archive table
            $this->connection->executeStatement(
                'INSERT INTO audit_logs_archive (user_id, action, entity, entity_id, old_data, new_data, created_at)
                 SELECT user_id, action, entity, entity_id, old_data, new_data, created_at 
                 FROM audit_logs WHERE created_at < ?',
                [$archiveDate]
            );

            // Delete from main table
            $this->connection->executeStatement(
                'DELETE FROM audit_logs WHERE created_at < ?',
                [$archiveDate]
            );

            return [
                'success' => true,
                'message' => "Archived {$logsToArchive} logs",
                'archived' => $logsToArchive,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get archive statistics
     *
     * @return array<string, mixed> Statistics
     */
    public function getArchiveStats(): array
    {
        try {
            $mainStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM audit_logs'
            );
            $mainResult = $mainStmt->fetchAssociative();
            
            $archiveStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM audit_logs_archive'
            );
            $archiveResult = $archiveStmt->fetchAssociative();

            // Get size estimates
            $mainSize = $this->estimateTableSize('audit_logs');
            $archiveSize = $this->estimateTableSize('audit_logs_archive');

            return [
                'success' => true,
                'data' => [
                    'mainLogs' => $mainResult['count'] ?? 0,
                    'archivedLogs' => $archiveResult['count'] ?? 0,
                    'mainSize' => $mainSize,
                    'archiveSize' => $archiveSize,
                    'totalLogs' => ($mainResult['count'] ?? 0) + ($archiveResult['count'] ?? 0),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set retention policy
     *
     * @param array<string, mixed> $policy Policy configuration
     * @return array<string, mixed> Result
     */
    public function setRetentionPolicy(array $policy): array
    {
        try {
            if (!isset($policy['retentionDays'], $policy['archiveEnabled'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required fields',
                ];
            }

            if ($policy['retentionDays'] < 1 || $policy['retentionDays'] > 3650) {
                return [
                    'success' => false,
                    'error' => 'Retention days must be between 1 and 3650',
                ];
            }

            // Store policy
            $this->connection->delete('audit_retention_policy', ['id' => 1]);
            $this->connection->insert('audit_retention_policy', [
                'id' => 1,
                'retention_days' => $policy['retentionDays'],
                'archive_enabled' => (int)$policy['archiveEnabled'],
                'archive_compression' => $policy['archiveCompression'] ?? false,
                'notify_before_delete' => $policy['notifyBeforeDelete'] ?? true,
                'notify_days' => $policy['notifyDays'] ?? 7,
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Retention policy updated',
                'data' => $policy,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get retention policy
     *
     * @return array<string, mixed> Policy
     */
    public function getRetentionPolicy(): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM audit_retention_policy WHERE id = 1'
            );
            $policy = $stmt->fetchAssociative();

            if (!$policy) {
                // Return default policy
                return [
                    'success' => true,
                    'data' => [
                        'retentionDays' => 90,
                        'archiveEnabled' => true,
                        'archiveCompression' => false,
                        'notifyBeforeDelete' => true,
                        'notifyDays' => 7,
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'retentionDays' => $policy['retention_days'],
                    'archiveEnabled' => (bool)$policy['archive_enabled'],
                    'archiveCompression' => (bool)$policy['archive_compression'],
                    'notifyBeforeDelete' => (bool)$policy['notify_before_delete'],
                    'notifyDays' => $policy['notify_days'],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export audit logs
     *
     * @param string $format 'csv' or 'json'
     * @param \DateTime|null $fromDate Start date
     * @param \DateTime|null $toDate End date
     * @return array<string, mixed> Export result
     */
    public function exportLogs(string $format = 'csv', ?\DateTime $fromDate = null, ?\DateTime $toDate = null): array
    {
        try {
            $query = 'SELECT * FROM audit_logs WHERE 1=1';
            $params = [];

            if ($fromDate) {
                $query .= ' AND created_at >= ?';
                $params[] = $fromDate->format('Y-m-d H:i:s');
            }

            if ($toDate) {
                $query .= ' AND created_at <= ?';
                $params[] = $toDate->format('Y-m-d H:i:s');
            }

            $query .= ' ORDER BY created_at DESC';

            $stmt = $this->connection->executeQuery($query, $params);
            $logs = $stmt->fetchAllAssociative();

            if ($format === 'json') {
                return [
                    'success' => true,
                    'format' => 'json',
                    'data' => $logs,
                ];
            }

            // CSV format
            $csv = "ID,User ID,Action,Entity,Entity ID,Old Data,New Data,Created At\n";
            foreach ($logs as $log) {
                $csv .= sprintf(
                    "%d,%d,%s,%s,%s,%s,%s,%s\n",
                    $log['id'],
                    $log['user_id'],
                    $log['action'],
                    $log['entity'],
                    $log['entity_id'],
                    $log['old_data'] ? substr($log['old_data'], 0, 50) : '',
                    $log['new_data'] ? substr($log['new_data'], 0, 50) : '',
                    $log['created_at']
                );
            }

            return [
                'success' => true,
                'format' => 'csv',
                'data' => $csv,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get audit log statistics
     *
     * @return array<string, mixed> Statistics
     */
    public function getAuditStats(): array
    {
        try {
            // Actions breakdown
            $actionsStmt = $this->connection->executeQuery(
                'SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action'
            );
            $actions = $actionsStmt->fetchAllAssociative();

            // Top users
            $usersStmt = $this->connection->executeQuery(
                'SELECT user_id, COUNT(*) as count FROM audit_logs GROUP BY user_id ORDER BY count DESC LIMIT 5'
            );
            $topUsers = $usersStmt->fetchAllAssociative();

            // Entities breakdown
            $entitiesStmt = $this->connection->executeQuery(
                'SELECT entity, COUNT(*) as count FROM audit_logs GROUP BY entity'
            );
            $entities = $entitiesStmt->fetchAllAssociative();

            return [
                'success' => true,
                'data' => [
                    'actionBreakdown' => $actions,
                    'topUsers' => $topUsers,
                    'entityBreakdown' => $entities,
                    'logsInPastDay' => $this->countLogsSince('1 day'),
                    'logsInPastWeek' => $this->countLogsSince('7 days'),
                    'logsInPastMonth' => $this->countLogsSince('30 days'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Count logs since a time period
     */
    private function countLogsSince(string $period): int
    {
        $date = (new \DateTime())->modify("-{$period}");
        $stmt = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM audit_logs WHERE created_at >= ?',
            [$date->format('Y-m-d H:i:s')]
        );
        $result = $stmt->fetchAssociative();
        return $result['count'] ?? 0;
    }

    /**
     * Estimate table size in bytes (PostgreSQL)
     */
    private function estimateTableSize(string $tableName): int
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT pg_total_relation_size(?) as size',
                [$tableName]
            );
            $result = $stmt->fetchAssociative();
            return (int)($result['size'] ?? 0);
        } catch (\Exception) {
            return 0;
        }
    }
}
