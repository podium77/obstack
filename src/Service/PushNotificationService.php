<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Push Notification Service - Send notifications to users
 */
class PushNotificationService
{
    private const NOTIFICATION_TABLE = 'push_notifications';
    private const RETENTION_DAYS = 90;

    public function __construct(
        private Connection $connection,
        private WebSocketService $webSocketService,
    ) {}

    /**
     * Send notification to user
     */
    public function sendNotification(
        int $userId,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?array $metadata = null
    ): array {
        try {
            $notificationData = [
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'metadata' => json_encode($metadata ?? []),
                'read' => false,
                'created_at' => (new \DateTime())->format('c'),
            ];

            $this->connection->insert(self::NOTIFICATION_TABLE, $notificationData);

            // Broadcast via WebSocket
            $this->webSocketService->broadcastToUser($userId, 'notification', $notificationData);

            return ['success' => true, 'data' => $notificationData];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendBulkNotification(
        array $userIds,
        string $title,
        string $message,
        ?string $actionUrl = null
    ): array {
        try {
            $sent = 0;
            foreach ($userIds as $userId) {
                $result = $this->sendNotification($userId, $title, $message, $actionUrl);
                if ($result['success']) {
                    $sent++;
                }
            }

            return ['success' => true, 'data' => ['total' => count($userIds), 'sent' => $sent]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to workspace
     */
    public function sendWorkspaceNotification(
        string $workspaceId,
        string $title,
        string $message,
        ?int $excludeUserId = null
    ): array {
        try {
            $sql = 'SELECT DISTINCT user_id FROM workspace_members WHERE workspace_id = ?';
            $params = [$workspaceId];

            if ($excludeUserId) {
                $sql .= ' AND user_id != ?';
                $params[] = $excludeUserId;
            }

            $userIds = array_column($this->connection->fetchAllAssociative($sql, $params), 'user_id');

            return $this->sendBulkNotification($userIds, $title, $message);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            $notifications = $this->connection->fetchAllAssociative(
                'SELECT * FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [$userId, $limit, $offset]
            );

            $total = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ?',
                [$userId]
            );

            return ['success' => true, 'data' => $notifications, 'total' => $total];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get unread notifications count
     */
    public function getUnreadCount(int $userId): array
    {
        try {
            $count = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ? AND read = false',
                [$userId]
            );

            return ['success' => true, 'data' => ['unread_count' => $count]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $notificationId): array
    {
        try {
            $this->connection->update(
                self::NOTIFICATION_TABLE,
                ['read' => true],
                ['id' => $notificationId]
            );

            return ['success' => true, 'message' => 'Notification marked as read'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(int $userId): array
    {
        try {
            $this->connection->update(
                self::NOTIFICATION_TABLE,
                ['read' => true],
                ['user_id' => $userId, 'read' => false]
            );

            return ['success' => true, 'message' => 'All notifications marked as read'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete notification
     */
    public function deleteNotification(string $notificationId): array
    {
        try {
            $this->connection->delete(self::NOTIFICATION_TABLE, ['id' => $notificationId]);
            return ['success' => true, 'message' => 'Notification deleted'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Purge old notifications
     */
    public function purgeOldNotifications(int $daysToKeep = self::RETENTION_DAYS): array
    {
        try {
            $date = (new \DateTime())
                ->sub(new \DateInterval('P' . $daysToKeep . 'D'))
                ->format('Y-m-d H:i:s');

            $result = $this->connection->executeStatement(
                'DELETE FROM ' . self::NOTIFICATION_TABLE . ' WHERE created_at < ?',
                [$date]
            );

            return ['success' => true, 'data' => ['deleted' => $result]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(int $userId): array
    {
        try {
            $stats = [
                'total_notifications' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ?',
                    [$userId]
                ),
                'unread_notifications' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ? AND read = false',
                    [$userId]
                ),
                'read_notifications' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM ' . self::NOTIFICATION_TABLE . ' WHERE user_id = ? AND read = true',
                    [$userId]
                ),
            ];

            return ['success' => true, 'data' => $stats];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
