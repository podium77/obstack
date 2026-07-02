<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class CollaborationAuditService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Log a collaboration event
     */
    public function logCollaborationEvent(
        int $userId,
        string $action,
        string $entityType,
        ?string $entityId,
        array $changes = [],
        ?string $workspaceId = null
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();

            $this->connection->insert('collaboration_audit_log', [
                'id' => $id,
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'workspace_id' => $workspaceId,
                'changes' => json_encode($changes),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'data' => ['auditId' => $id],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to log audit event'];
        }
    }

    /**
     * Get audit logs for a workspace
     */
    public function getWorkspaceAuditLogs(string $workspaceId, int $limit = 100, int $offset = 0): array
    {
        try {
            $query = <<<SQL
                SELECT cal.id, cal.user_id, cal.action, cal.entity_type, cal.entity_id,
                       cal.changes, cal.ip_address, cal.created_at,
                       lu.email, lu.display_name
                FROM collaboration_audit_log cal
                LEFT JOIN local_user lu ON cal.user_id = lu.id
                WHERE cal.workspace_id = ?
                ORDER BY cal.created_at DESC
                LIMIT ? OFFSET ?
            SQL;

            $logs = $this->connection->fetchAllAssociative(
                $query,
                [$workspaceId, $limit, $offset]
            );

            // Decode changes
            foreach ($logs as &$log) {
                $log['changes'] = json_decode($log['changes'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $logs,
                'count' => count($logs),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve audit logs'];
        }
    }

    /**
     * Get user's collaboration audit trail
     */
    public function getUserAuditTrail(int $userId, int $limit = 100): array
    {
        try {
            $query = <<<SQL
                SELECT cal.id, cal.action, cal.entity_type, cal.entity_id,
                       cal.workspace_id, cal.changes, cal.created_at,
                       w.name as workspace_name
                FROM collaboration_audit_log cal
                LEFT JOIN workspaces w ON cal.workspace_id = w.id
                WHERE cal.user_id = ?
                ORDER BY cal.created_at DESC
                LIMIT ?
            SQL;

            $logs = $this->connection->fetchAllAssociative($query, [$userId, $limit]);

            foreach ($logs as &$log) {
                $log['changes'] = json_decode($log['changes'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $logs,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve audit trail'];
        }
    }

    /**
     * Get audit logs by action type
     */
    public function getAuditLogsByAction(string $workspaceId, string $action, int $limit = 50): array
    {
        try {
            $query = <<<SQL
                SELECT cal.id, cal.user_id, cal.action, cal.entity_type, cal.entity_id,
                       cal.changes, cal.created_at,
                       lu.email, lu.display_name
                FROM collaboration_audit_log cal
                LEFT JOIN local_user lu ON cal.user_id = lu.id
                WHERE cal.workspace_id = ? AND cal.action = ?
                ORDER BY cal.created_at DESC
                LIMIT ?
            SQL;

            $logs = $this->connection->fetchAllAssociative(
                $query,
                [$workspaceId, $action, $limit]
            );

            foreach ($logs as &$log) {
                $log['changes'] = json_decode($log['changes'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $logs,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve audit logs'];
        }
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats(string $workspaceId): array
    {
        try {
            // Total events
            $total = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM collaboration_audit_log WHERE workspace_id = ?',
                [$workspaceId]
            );

            // Events by action type
            $byAction = $this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT action, COUNT(*) as count
                    FROM collaboration_audit_log
                    WHERE workspace_id = ?
                    GROUP BY action
                    ORDER BY count DESC
                SQL,
                [$workspaceId]
            );

            // Events by entity type
            $byEntity = $this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT entity_type, COUNT(*) as count
                    FROM collaboration_audit_log
                    WHERE workspace_id = ?
                    GROUP BY entity_type
                    ORDER BY count DESC
                SQL,
                [$workspaceId]
            );

            // Most active users
            $topUsers = $this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT cal.user_id, lu.display_name, COUNT(*) as count
                    FROM collaboration_audit_log cal
                    LEFT JOIN local_user lu ON cal.user_id = lu.id
                    WHERE cal.workspace_id = ?
                    GROUP BY cal.user_id, lu.display_name
                    ORDER BY count DESC
                    LIMIT 10
                SQL,
                [$workspaceId]
            );

            // Recent events (24 hours)
            $recent24h = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM collaboration_audit_log WHERE workspace_id = ? AND created_at > NOW() - INTERVAL \'1 day\'',
                [$workspaceId]
            );

            return [
                'success' => true,
                'data' => [
                    'totalEvents' => (int)$total,
                    'eventsByAction' => $byAction,
                    'eventsByEntity' => $byEntity,
                    'topUsers' => $topUsers,
                    'recent24h' => (int)$recent24h,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve audit stats'];
        }
    }

    /**
     * Export audit logs
     */
    public function exportAuditLogs(string $workspaceId, string $format = 'csv'): array
    {
        try {
            $logs = $this->connection->fetchAllAssociative(
                <<<SQL
                    SELECT cal.created_at, lu.display_name, cal.action, cal.entity_type,
                           cal.entity_id, cal.ip_address, cal.changes
                    FROM collaboration_audit_log cal
                    LEFT JOIN local_user lu ON cal.user_id = lu.id
                    WHERE cal.workspace_id = ?
                    ORDER BY cal.created_at DESC
                SQL,
                [$workspaceId]
            );

            if ($format === 'csv') {
                $csv = "Timestamp,User,Action,Entity Type,Entity ID,IP Address,Changes\n";
                foreach ($logs as $log) {
                    $changes = str_replace('"', '""', json_encode($log['changes'] ?? []));
                    $csv .= sprintf(
                        '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                        $log['created_at'],
                        $log['display_name'] ?? 'Unknown',
                        $log['action'],
                        $log['entity_type'],
                        $log['entity_id'] ?? '',
                        $log['ip_address'],
                        $changes
                    );
                }
                return [
                    'success' => true,
                    'data' => $csv,
                    'format' => 'csv',
                ];
            } elseif ($format === 'json') {
                return [
                    'success' => true,
                    'data' => $logs,
                    'format' => 'json',
                ];
            }

            return ['success' => false, 'error' => 'Invalid export format'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Export failed'];
        }
    }

    /**
     * Purge old audit logs
     */
    public function purgeOldLogs(int $daysToKeep = 180): array
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));

            $result = $this->connection->delete(
                'collaboration_audit_log',
                ['created_at < ' => $cutoffDate]
            );

            return [
                'success' => true,
                'message' => "Purged $result old audit logs",
                'count' => $result,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to purge logs'];
        }
    }

    /**
     * Generate audit report for a period
     */
    public function generateAuditReport(string $workspaceId, string $startDate, string $endDate): array
    {
        try {
            $query = <<<SQL
                SELECT 
                    DATE(cal.created_at) as date,
                    cal.action,
                    COUNT(*) as count,
                    COUNT(DISTINCT cal.user_id) as unique_users
                FROM collaboration_audit_log cal
                WHERE cal.workspace_id = ? AND cal.created_at BETWEEN ? AND ?
                GROUP BY DATE(cal.created_at), cal.action
                ORDER BY DATE(cal.created_at) DESC
            SQL;

            $report = $this->connection->fetchAllAssociative(
                $query,
                [$workspaceId, $startDate, $endDate]
            );

            return [
                'success' => true,
                'data' => $report,
                'period' => ['start' => $startDate, 'end' => $endDate],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Report generation failed'];
        }
    }
}
