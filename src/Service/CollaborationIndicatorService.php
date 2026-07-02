<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Collaboration Indicator Service
 * 
 * Manages live collaboration indicators including active editors,
 * active viewers, document state tracking, and conflict detection.
 */
class CollaborationIndicatorService
{
    private const ACTIVITY_TIMEOUT = 60; // seconds

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Register editor on document
     */
    public function registerEditor(int $userId, string $documentId): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('document_editors', [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'edit_count' => 0,
                'last_edit' => $now,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $this->logger->info('Editor registered', [
                'user_id' => $userId,
                'document_id' => $documentId
            ]);

            return [
                'user_id' => $userId,
                'document_id' => $documentId,
                'role' => 'editor',
                'status' => 'active'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register editor', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Register viewer on document
     */
    public function registerViewer(int $userId, string $documentId): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('document_viewers', [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'view_count' => 0,
                'last_view' => $now,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now
            ]);

            $this->logger->info('Viewer registered', [
                'user_id' => $userId,
                'document_id' => $documentId
            ]);

            return [
                'user_id' => $userId,
                'document_id' => $documentId,
                'role' => 'viewer',
                'status' => 'active'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register viewer', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Record edit activity
     */
    public function recordEdit(int $userId, string $documentId, string $changeType = 'modify'): bool {
        try {
            $now = new \DateTime();

            $this->connection->update(
                'document_editors',
                [
                    'edit_count' => $this->connection->quote('edit_count') . ' + 1',
                    'last_edit' => $now,
                    'updated_at' => $now
                ],
                ['user_id' => $userId, 'document_id' => $documentId]
            );

            // Record edit in history
            $id = Uuid::v4()->toRfc4122();
            $this->connection->insert('edit_history', [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'change_type' => $changeType,
                'created_at' => $now
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to record edit', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Record view activity
     */
    public function recordView(int $userId, string $documentId): bool {
        try {
            $now = new \DateTime();

            $this->connection->update(
                'document_viewers',
                [
                    'view_count' => $this->connection->quote('view_count') . ' + 1',
                    'last_view' => $now,
                    'updated_at' => $now
                ],
                ['user_id' => $userId, 'document_id' => $documentId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to record view', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unregister editor
     */
    public function unregisterEditor(int $userId, string $documentId): bool {
        try {
            $this->connection->update(
                'document_editors',
                ['is_active' => false],
                ['user_id' => $userId, 'document_id' => $documentId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to unregister editor', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unregister viewer
     */
    public function unregisterViewer(int $userId, string $documentId): bool {
        try {
            $this->connection->update(
                'document_viewers',
                ['is_active' => false],
                ['user_id' => $userId, 'document_id' => $documentId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to unregister viewer', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get active editors for document
     */
    public function getActiveEditors(string $documentId): array {
        try {
            $sql = 'SELECT de.*, u.name, u.email FROM document_editors de
                    INNER JOIN "user" u ON de.user_id = u.id
                    WHERE de.document_id = ? AND de.is_active = true
                    ORDER BY de.last_edit DESC';

            return $this->connection->fetchAllAssociative($sql, [$documentId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get active editors', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get active viewers for document
     */
    public function getActiveViewers(string $documentId): array {
        try {
            $sql = 'SELECT dv.*, u.name, u.email FROM document_viewers dv
                    INNER JOIN "user" u ON dv.user_id = u.id
                    WHERE dv.document_id = ? AND dv.is_active = true
                    ORDER BY dv.last_view DESC';

            return $this->connection->fetchAllAssociative($sql, [$documentId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get active viewers', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get collaboration stats for document
     */
    public function getDocumentCollaborationStats(string $documentId): array {
        try {
            $editors = $this->getActiveEditors($documentId);
            $viewers = $this->getActiveViewers($documentId);

            $sql = 'SELECT COUNT(*) as total_edits FROM edit_history 
                    WHERE document_id = ? AND created_at > NOW() - INTERVAL \'1 hour\'';
            $editsResult = $this->connection->fetchAssociative($sql, [$documentId]);

            return [
                'document_id' => $documentId,
                'active_editors' => count($editors),
                'active_viewers' => count($viewers),
                'total_participants' => count($editors) + count($viewers),
                'recent_edits' => (int) ($editsResult['total_edits'] ?? 0),
                'editors' => $editors,
                'viewers' => $viewers
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get collaboration stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Track document state version
     */
    public function trackStateVersion(string $documentId, int $version, array $changeData): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('document_state_versions', [
                'id' => $id,
                'document_id' => $documentId,
                'version' => $version,
                'change_data' => json_encode($changeData),
                'created_at' => $now
            ]);

            return [
                'document_id' => $documentId,
                'version' => $version,
                'tracked' => true
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to track state version', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Detect edit conflicts
     */
    public function detectConflicts(
        string $documentId,
        int $userId,
        int $startLine,
        int $endLine
    ): array {
        try {
            $sql = 'SELECT de.*, u.name FROM document_editors de
                    INNER JOIN "user" u ON de.user_id = u.id
                    WHERE de.document_id = ? AND de.user_id != ?
                    AND de.is_active = true
                    AND de.last_edit > NOW() - INTERVAL \'5 seconds\'';

            $otherEditors = $this->connection->fetchAllAssociative($sql, [$documentId, $userId]);

            $conflicts = [];
            foreach ($otherEditors as $editor) {
                $conflicts[] = [
                    'conflict_user_id' => $editor['user_id'],
                    'conflict_user_name' => $editor['name'],
                    'conflict_type' => 'concurrent_edit',
                    'severity' => 'warning'
                ];
            }

            return [
                'document_id' => $documentId,
                'conflict_count' => count($conflicts),
                'conflicts' => $conflicts,
                'has_conflicts' => count($conflicts) > 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to detect conflicts', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get edit history
     */
    public function getEditHistory(string $documentId, int $limit = 100): array {
        try {
            $sql = 'SELECT eh.*, u.name, u.email FROM edit_history eh
                    INNER JOIN "user" u ON eh.user_id = u.id
                    WHERE eh.document_id = ?
                    ORDER BY eh.created_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$documentId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get edit history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get collaboration summary
     */
    public function getCollaborationSummary(string $documentId): array {
        try {
            $stats = $this->getDocumentCollaborationStats($documentId);

            $sql = 'SELECT COUNT(DISTINCT user_id) as unique_editors FROM edit_history 
                    WHERE document_id = ? AND created_at > NOW() - INTERVAL \'24 hours\'';
            $uniqueResult = $this->connection->fetchAssociative($sql, [$documentId]);

            $sql2 = 'SELECT COUNT(*) as total_views FROM document_viewers 
                    WHERE document_id = ?';
            $viewsResult = $this->connection->fetchAssociative($sql2, [$documentId]);

            return [
                'document_id' => $documentId,
                'active_editors' => $stats['active_editors'] ?? 0,
                'active_viewers' => $stats['active_viewers'] ?? 0,
                'unique_editors_24h' => (int) ($uniqueResult['unique_editors'] ?? 0),
                'total_views' => (int) ($viewsResult['total_views'] ?? 0),
                'recent_activity' => true
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get collaboration summary', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup inactive editors
     */
    public function cleanupInactiveEditors(int $timeoutSeconds = self::ACTIVITY_TIMEOUT): int {
        try {
            $cutoffTime = new \DateTime('-' . $timeoutSeconds . ' seconds');

            $result = $this->connection->executeStatement(
                'UPDATE document_editors SET is_active = false 
                 WHERE last_edit < ? AND is_active = true',
                [$cutoffTime]
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup inactive editors', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Cleanup inactive viewers
     */
    public function cleanupInactiveViewers(int $timeoutSeconds = self::ACTIVITY_TIMEOUT): int {
        try {
            $cutoffTime = new \DateTime('-' . $timeoutSeconds . ' seconds');

            $result = $this->connection->executeStatement(
                'UPDATE document_viewers SET is_active = false 
                 WHERE last_view < ? AND is_active = true',
                [$cutoffTime]
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup inactive viewers', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
