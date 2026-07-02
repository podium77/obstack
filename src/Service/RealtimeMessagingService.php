<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Real-time Messaging Service
 * 
 * Handles real-time message delivery through WebSocket connections,
 * message routing, acknowledgment tracking, and delivery retries.
 */
class RealtimeMessagingService
{
    private const MESSAGE_EXPIRY = 86400; // 24 hours
    private const RETRY_DELAY = 5; // seconds
    private const MAX_RETRIES = 3;

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Send real-time message
     */
    public function sendMessage(
        int $fromUserId,
        int $toUserId,
        string $messageType,
        array $payload,
        ?string $workspaceId = null,
        ?string $documentId = null
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();
            $expiresAt = (new \DateTime())->modify('+' . (self::MESSAGE_EXPIRY / 3600) . ' hours');

            $this->connection->insert('realtime_messages', [
                'id' => $id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'message_type' => $messageType,
                'payload' => json_encode($payload),
                'workspace_id' => $workspaceId,
                'document_id' => $documentId,
                'delivery_status' => 'pending',
                'retry_count' => 0,
                'created_at' => $now,
                'expires_at' => $expiresAt,
                'last_retry_at' => $now
            ]);

            $this->logger->info('Real-time message sent', [
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'message_type' => $messageType
            ]);

            return [
                'id' => $id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'message_type' => $messageType,
                'status' => 'pending',
                'created_at' => $now->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to send message', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Broadcast message to multiple users
     */
    public function broadcastMessage(
        int $fromUserId,
        array $toUserIds,
        string $messageType,
        array $payload,
        ?string $workspaceId = null
    ): array {
        try {
            $messages = [];
            $now = new \DateTime();
            $expiresAt = (new \DateTime())->modify('+' . (self::MESSAGE_EXPIRY / 3600) . ' hours');

            foreach ($toUserIds as $toUserId) {
                $id = Uuid::v4()->toRfc4122();
                
                $this->connection->insert('realtime_messages', [
                    'id' => $id,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'message_type' => $messageType,
                    'payload' => json_encode($payload),
                    'workspace_id' => $workspaceId,
                    'delivery_status' => 'pending',
                    'retry_count' => 0,
                    'created_at' => $now,
                    'expires_at' => $expiresAt,
                    'last_retry_at' => $now
                ]);

                $messages[] = $id;
            }

            $this->logger->info('Broadcast message sent', [
                'from_user_id' => $fromUserId,
                'recipients' => count($toUserIds),
                'message_type' => $messageType
            ]);

            return [
                'message_ids' => $messages,
                'recipient_count' => count($toUserIds),
                'status' => 'pending'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to broadcast message', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Mark message as delivered
     */
    public function markDelivered(string $messageId): bool {
        try {
            $this->connection->update(
                'realtime_messages',
                [
                    'delivery_status' => 'delivered',
                    'delivered_at' => new \DateTime()
                ],
                ['id' => $messageId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark message delivered', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Mark message as acknowledged
     */
    public function markAcknowledged(string $messageId): bool {
        try {
            $this->connection->update(
                'realtime_messages',
                [
                    'delivery_status' => 'acknowledged',
                    'acknowledged_at' => new \DateTime()
                ],
                ['id' => $messageId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark message acknowledged', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get pending messages for user
     */
    public function getPendingMessages(int $userId, int $limit = 100): array {
        try {
            $sql = 'SELECT rm.*, u.name as from_user_name FROM realtime_messages rm
                    INNER JOIN "user" u ON rm.from_user_id = u.id
                    WHERE rm.to_user_id = ? AND rm.delivery_status = ?
                    ORDER BY rm.created_at DESC
                    LIMIT ?';

            $messages = $this->connection->fetchAllAssociative($sql, [$userId, 'pending', $limit]);

            return array_map(function($msg) {
                return [
                    'id' => $msg['id'],
                    'from_user_id' => $msg['from_user_id'],
                    'from_user_name' => $msg['from_user_name'],
                    'message_type' => $msg['message_type'],
                    'payload' => json_decode($msg['payload'], true),
                    'created_at' => $msg['created_at']
                ];
            }, $messages);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get pending messages', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get message history between users
     */
    public function getMessageHistory(int $userId1, int $userId2, int $limit = 100): array {
        try {
            $sql = 'SELECT rm.*, u.name as from_user_name FROM realtime_messages rm
                    INNER JOIN "user" u ON rm.from_user_id = u.id
                    WHERE ((rm.from_user_id = ? AND rm.to_user_id = ?) 
                           OR (rm.from_user_id = ? AND rm.to_user_id = ?))
                    ORDER BY rm.created_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative(
                $sql, 
                [$userId1, $userId2, $userId2, $userId1, $limit]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to get message history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Retry failed messages
     */
    public function retryFailedMessages(int $maxRetries = self::MAX_RETRIES): int {
        try {
            $retryTime = new \DateTime('-' . self::RETRY_DELAY . ' seconds');

            $result = $this->connection->executeStatement(
                'UPDATE realtime_messages 
                 SET retry_count = retry_count + 1,
                     delivery_status = CASE 
                        WHEN retry_count < ? THEN \'pending\'
                        ELSE \'failed\'
                     END,
                     last_retry_at = ?
                 WHERE delivery_status = ? AND last_retry_at < ?',
                [$maxRetries, new \DateTime(), 'failed', $retryTime]
            );

            $this->logger->info('Failed messages retry', [
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retry messages', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get delivery statistics
     */
    public function getDeliveryStats(): array {
        try {
            $pending = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM realtime_messages WHERE delivery_status = ?',
                ['pending']
            );

            $delivered = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM realtime_messages WHERE delivery_status = ?',
                ['delivered']
            );

            $acknowledged = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM realtime_messages WHERE delivery_status = ?',
                ['acknowledged']
            );

            $failed = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM realtime_messages WHERE delivery_status = ?',
                ['failed']
            );

            $avgDeliveryTime = $this->connection->fetchOne(
                'SELECT AVG(EXTRACT(EPOCH FROM (delivered_at - created_at))) 
                 FROM realtime_messages WHERE delivered_at IS NOT NULL'
            );

            return [
                'pending' => (int) $pending,
                'delivered' => (int) $delivered,
                'acknowledged' => (int) $acknowledged,
                'failed' => (int) $failed,
                'total' => (int) ($pending + $delivered + $acknowledged + $failed),
                'avg_delivery_time_seconds' => (int) ($avgDeliveryTime ?? 0)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get delivery stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup expired messages
     */
    public function cleanupExpiredMessages(): int {
        try {
            $result = $this->connection->executeStatement(
                'DELETE FROM realtime_messages WHERE expires_at < NOW()'
            );

            $this->logger->info('Expired messages cleaned up', [
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup expired messages', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get message by ID
     */
    public function getMessage(string $messageId): ?array {
        try {
            return $this->connection->fetchAssociative(
                'SELECT * FROM realtime_messages WHERE id = ?',
                [$messageId]
            ) ?: null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get message', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get user's conversation partners
     */
    public function getConversationPartners(int $userId): array {
        try {
            $sql = 'SELECT DISTINCT 
                    CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END as partner_id,
                    u.name, u.email
                    FROM realtime_messages rm
                    INNER JOIN "user" u ON (CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END) = u.id
                    WHERE from_user_id = ? OR to_user_id = ?
                    ORDER BY rm.created_at DESC';

            return $this->connection->fetchAllAssociative($sql, [$userId, $userId, $userId, $userId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get conversation partners', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get workspace messages
     */
    public function getWorkspaceMessages(string $workspaceId, int $limit = 100): array {
        try {
            $sql = 'SELECT rm.*, u.name as from_user_name FROM realtime_messages rm
                    INNER JOIN "user" u ON rm.from_user_id = u.id
                    WHERE rm.workspace_id = ?
                    ORDER BY rm.created_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$workspaceId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get workspace messages', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
