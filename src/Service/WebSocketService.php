<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * WebSocket Service - Handle real-time message broadcasting
 */
class WebSocketService
{
    private const REDIS_CHANNEL_PREFIX = 'ws:channel:';
    private const REDIS_MESSAGE_PREFIX = 'ws:message:';
    private const MESSAGE_RETENTION_HOURS = 24;

    public function __construct(
        private Connection $connection,
        private \Redis $redis,
    ) {}

    /**
     * Broadcast message to workspace channel
     */
    public function broadcastToWorkspace(string $workspaceId, string $eventType, array $data): array
    {
        try {
            $channel = self::REDIS_CHANNEL_PREFIX . $workspaceId;
            $message = [
                'id' => uniqid('msg_', true),
                'event_type' => $eventType,
                'workspace_id' => $workspaceId,
                'data' => $data,
                'timestamp' => (new \DateTime())->format('c'),
                'broadcast' => true,
            ];

            // Store message
            $messageId = self::REDIS_MESSAGE_PREFIX . $message['id'];
            $this->redis->setex($messageId, 3600 * self::MESSAGE_RETENTION_HOURS, json_encode($message));

            // Publish to channel
            $this->redis->publish($channel, json_encode($message));

            return ['success' => true, 'data' => $message];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Broadcast message to specific user
     */
    public function broadcastToUser(int $userId, string $eventType, array $data): array
    {
        try {
            $channel = self::REDIS_CHANNEL_PREFIX . 'user:' . $userId;
            $message = [
                'id' => uniqid('msg_', true),
                'event_type' => $eventType,
                'user_id' => $userId,
                'data' => $data,
                'timestamp' => (new \DateTime())->format('c'),
                'broadcast' => false,
            ];

            $messageId = self::REDIS_MESSAGE_PREFIX . $message['id'];
            $this->redis->setex($messageId, 3600 * self::MESSAGE_RETENTION_HOURS, json_encode($message));
            $this->redis->publish($channel, json_encode($message));

            return ['success' => true, 'data' => $message];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get workspace channel subscribers count
     */
    public function getChannelSubscriberCount(string $workspaceId): array
    {
        try {
            $channel = self::REDIS_CHANNEL_PREFIX . $workspaceId;
            $count = $this->redis->pubsub('CHANNELS', $channel);
            return ['success' => true, 'data' => ['channel' => $channel, 'subscribers' => count($count) ?? 0]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store message for offline users
     */
    public function storeMessage(int $userId, string $eventType, array $data): array
    {
        try {
            $message = [
                'user_id' => $userId,
                'event_type' => $eventType,
                'data' => json_encode($data),
                'read' => false,
                'created_at' => (new \DateTime())->format('c'),
            ];

            $this->connection->insert('user_messages', $message);

            return ['success' => true, 'data' => $message];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get pending messages for user
     */
    public function getPendingMessages(int $userId, int $limit = 50): array
    {
        try {
            $messages = $this->connection->fetchAllAssociative(
                'SELECT * FROM user_messages WHERE user_id = ? AND read = false ORDER BY created_at DESC LIMIT ?',
                [$userId, $limit]
            );

            return ['success' => true, 'data' => $messages];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mark message as read
     */
    public function markMessageAsRead(string $messageId): array
    {
        try {
            $this->connection->update('user_messages', ['read' => true], ['id' => $messageId]);
            return ['success' => true, 'message' => 'Message marked as read'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Clear old messages for user
     */
    public function clearOldMessages(int $daysToKeep = 30): array
    {
        try {
            $date = (new \DateTime())
                ->sub(new \DateInterval('P' . $daysToKeep . 'D'))
                ->format('Y-m-d H:i:s');

            $result = $this->connection->executeStatement(
                'DELETE FROM user_messages WHERE created_at < ?',
                [$date]
            );

            return ['success' => true, 'data' => ['deleted' => $result]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get connection stats
     */
    public function getConnectionStats(): array
    {
        try {
            $stats = [
                'total_active_connections' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM user_connections WHERE last_ping > NOW() - INTERVAL 5 MINUTE'
                ),
                'total_messages_pending' => $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM user_messages WHERE read = false'
                ),
                'workspaces_active' => $this->connection->fetchOne(
                    'SELECT COUNT(DISTINCT workspace_id) FROM user_connections WHERE last_ping > NOW() - INTERVAL 5 MINUTE'
                ),
            ];

            return ['success' => true, 'data' => $stats];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
