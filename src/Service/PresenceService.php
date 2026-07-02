<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * User Presence Tracking Service
 * 
 * Manages user online/offline status, activity status, location tracking,
 * and presence awareness for real-time collaboration features.
 */
class PresenceService
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_IDLE = 'idle';
    public const STATUS_AWAY = 'away';
    public const STATUS_OFFLINE = 'offline';

    private const IDLE_TIMEOUT = 300; // 5 minutes
    private const AWAY_TIMEOUT = 900; // 15 minutes

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Update user presence status
     */
    public function updatePresence(
        int $userId,
        string $status,
        ?string $workspaceId = null,
        ?string $documentId = null
    ): array {
        try {
            $now = new \DateTime();

            // Check if presence record exists
            $existing = $this->connection->fetchOne(
                'SELECT id FROM user_presence WHERE user_id = ?',
                [$userId]
            );

            if ($existing) {
                $this->connection->update(
                    'user_presence',
                    [
                        'status' => $status,
                        'workspace_id' => $workspaceId,
                        'document_id' => $documentId,
                        'last_seen' => $now,
                        'updated_at' => $now
                    ],
                    ['user_id' => $userId]
                );
            } else {
                $id = Uuid::v4()->toRfc4122();
                $this->connection->insert('user_presence', [
                    'id' => $id,
                    'user_id' => $userId,
                    'status' => $status,
                    'workspace_id' => $workspaceId,
                    'document_id' => $documentId,
                    'last_seen' => $now,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }

            $this->logger->info('User presence updated', [
                'user_id' => $userId,
                'status' => $status,
                'workspace_id' => $workspaceId
            ]);

            return [
                'user_id' => $userId,
                'status' => $status,
                'workspace_id' => $workspaceId,
                'document_id' => $documentId,
                'last_seen' => $now->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update presence', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Set user as offline
     */
    public function setOffline(int $userId): bool {
        try {
            return (bool) $this->connection->update(
                'user_presence',
                ['status' => self::STATUS_OFFLINE],
                ['user_id' => $userId]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to set user offline', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user presence info
     */
    public function getUserPresence(int $userId): ?array {
        try {
            $presence = $this->connection->fetchAssociative(
                'SELECT * FROM user_presence WHERE user_id = ?',
                [$userId]
            );

            if (!$presence) {
                return null;
            }

            // Determine status based on activity
            $status = $this->calculateStatus($presence);

            return array_merge($presence, ['calculated_status' => $status]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user presence', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all online users in a workspace
     */
    public function getWorkspaceOnlineUsers(string $workspaceId): array {
        try {
            $sql = 'SELECT up.*, u.name, u.email FROM user_presence up
                    INNER JOIN "user" u ON up.user_id = u.id
                    WHERE up.workspace_id = ? AND up.status != ?
                    ORDER BY up.last_seen DESC';

            return $this->connection->fetchAllAssociative($sql, [$workspaceId, self::STATUS_OFFLINE]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get workspace online users', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all users on a document
     */
    public function getDocumentUsers(string $documentId): array {
        try {
            $sql = 'SELECT up.*, u.name, u.email FROM user_presence up
                    INNER JOIN "user" u ON up.user_id = u.id
                    WHERE up.document_id = ? AND up.status != ?
                    ORDER BY up.last_seen DESC';

            return $this->connection->fetchAllAssociative($sql, [$documentId, self::STATUS_OFFLINE]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get document users', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get global online users count
     */
    public function getGlobalOnlineCount(): int {
        try {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_presence WHERE status != ?',
                [self::STATUS_OFFLINE]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to get global online count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Check if user is online
     */
    public function isOnline(int $userId): bool {
        try {
            $presence = $this->getUserPresence($userId);
            return $presence && $presence['status'] !== self::STATUS_OFFLINE;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check if user is online', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Record activity (updates last_seen)
     */
    public function recordActivity(int $userId): bool {
        try {
            $now = new \DateTime();
            return (bool) $this->connection->update(
                'user_presence',
                [
                    'last_seen' => $now,
                    'status' => self::STATUS_ONLINE
                ],
                ['user_id' => $userId]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to record activity', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get presence statistics
     */
    public function getPresenceStats(): array {
        try {
            $onlineCount = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_presence WHERE status = ?',
                [self::STATUS_ONLINE]
            );

            $idleCount = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_presence WHERE status = ?',
                [self::STATUS_IDLE]
            );

            $awayCount = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_presence WHERE status = ?',
                [self::STATUS_AWAY]
            );

            $offlineCount = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_presence WHERE status = ?',
                [self::STATUS_OFFLINE]
            );

            $activeWorkspaces = $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT workspace_id) FROM user_presence WHERE workspace_id IS NOT NULL AND status != ?',
                [self::STATUS_OFFLINE]
            );

            return [
                'online' => (int) $onlineCount,
                'idle' => (int) $idleCount,
                'away' => (int) $awayCount,
                'offline' => (int) $offlineCount,
                'total' => (int) ($onlineCount + $idleCount + $awayCount + $offlineCount),
                'active_workspaces' => (int) $activeWorkspaces
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get presence stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup stale presence records
     */
    public function cleanupStalePresence(int $maxDaysOff = 30): int {
        try {
            $cutoffDate = new \DateTime('-' . $maxDaysOff . ' days');
            $result = $this->connection->executeStatement(
                'DELETE FROM user_presence WHERE status = ? AND last_seen < ?',
                [self::STATUS_OFFLINE, $cutoffDate]
            );

            $this->logger->info('Stale presence records cleaned up', [
                'count' => $result,
                'days' => $maxDaysOff
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup stale presence', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Broadcast presence change
     */
    public function broadcastPresenceChange(int $userId, string $status): array {
        $presence = $this->getUserPresence($userId);
        return [
            'user_id' => $userId,
            'status' => $status,
            'presence' => $presence,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Calculate status based on last activity
     */
    private function calculateStatus(array $presence): string {
        if ($presence['status'] === self::STATUS_OFFLINE) {
            return self::STATUS_OFFLINE;
        }

        if (!$presence['last_seen']) {
            return self::STATUS_ONLINE;
        }

        $lastSeen = new \DateTime($presence['last_seen']);
        $now = new \DateTime();
        $diffSeconds = $now->getTimestamp() - $lastSeen->getTimestamp();

        if ($diffSeconds > self::AWAY_TIMEOUT) {
            return self::STATUS_AWAY;
        } elseif ($diffSeconds > self::IDLE_TIMEOUT) {
            return self::STATUS_IDLE;
        }

        return self::STATUS_ONLINE;
    }

    /**
     * Get users in same workspace
     */
    public function getWorkspaceUsers(string $workspaceId): array {
        try {
            $sql = 'SELECT up.*, u.name, u.email FROM user_presence up
                    INNER JOIN "user" u ON up.user_id = u.id
                    WHERE up.workspace_id = ?
                    ORDER BY up.status DESC, up.last_seen DESC';

            return $this->connection->fetchAllAssociative($sql, [$workspaceId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get workspace users', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get active editors on document
     */
    public function getDocumentEditors(string $documentId): array {
        try {
            $sql = 'SELECT up.*, u.name, u.email FROM user_presence up
                    INNER JOIN "user" u ON up.user_id = u.id
                    WHERE up.document_id = ? AND up.status != ?
                    ORDER BY up.updated_at DESC';

            return $this->connection->fetchAllAssociative($sql, [$documentId, self::STATUS_OFFLINE]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get document editors', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
