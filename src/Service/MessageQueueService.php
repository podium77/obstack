<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Message Queue Service
 * 
 * Manages message queue, delivery retries, and persistence for
 * reliable message delivery in real-time messaging system.
 */
class MessageQueueService
{
    private const MAX_QUEUE_SIZE = 10000;
    private const RETRY_DELAYS = [5, 15, 60]; // seconds

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Enqueue message
     */
    public function enqueue(
        string $messageId,
        string $eventType,
        array $payload,
        ?int $priority = null
    ): array {
        try {
            $queueId = Uuid::v4()->toRfc4122();
            $now = new \DateTime();
            $priority = $priority ?? 5; // Default priority

            // Check queue size
            $queueSize = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                ['pending']
            );

            if ($queueSize >= self::MAX_QUEUE_SIZE) {
                throw new \RuntimeException('Message queue is full');
            }

            $this->connection->insert('message_queue', [
                'id' => $queueId,
                'message_id' => $messageId,
                'event_type' => $eventType,
                'payload' => json_encode($payload),
                'priority' => $priority,
                'status' => 'pending',
                'retry_count' => 0,
                'created_at' => $now,
                'scheduled_at' => $now,
                'updated_at' => $now
            ]);

            $this->logger->debug('Message enqueued', [
                'message_id' => $messageId,
                'event_type' => $eventType,
                'priority' => $priority
            ]);

            return [
                'queue_id' => $queueId,
                'message_id' => $messageId,
                'status' => 'pending',
                'priority' => $priority
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to enqueue message', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Dequeue message (get next to process)
     */
    public function dequeue(): ?array {
        try {
            $message = $this->connection->fetchAssociative(
                'SELECT * FROM message_queue 
                 WHERE status = ? AND scheduled_at <= NOW()
                 ORDER BY priority DESC, created_at ASC
                 LIMIT 1
                 FOR UPDATE',
                ['pending']
            );

            if ($message) {
                $this->connection->update(
                    'message_queue',
                    ['status' => 'processing', 'updated_at' => new \DateTime()],
                    ['id' => $message['id']]
                );
            }

            return $message;
        } catch (\Exception $e) {
            $this->logger->error('Failed to dequeue message', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Mark message as processed successfully
     */
    public function markProcessed(string $queueId): bool {
        try {
            $this->connection->update(
                'message_queue',
                [
                    'status' => 'processed',
                    'processed_at' => new \DateTime(),
                    'updated_at' => new \DateTime()
                ],
                ['id' => $queueId]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark message processed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Mark message as failed and schedule retry
     */
    public function markFailed(string $queueId): bool {
        try {
            $message = $this->connection->fetchAssociative(
                'SELECT retry_count FROM message_queue WHERE id = ?',
                [$queueId]
            );

            $retryCount = (int) ($message['retry_count'] ?? 0);
            $maxRetries = count(self::RETRY_DELAYS);

            if ($retryCount < $maxRetries) {
                $delaySeconds = self::RETRY_DELAYS[$retryCount];
                $scheduledAt = (new \DateTime())->modify("+{$delaySeconds} seconds");

                $this->connection->update(
                    'message_queue',
                    [
                        'status' => 'pending',
                        'retry_count' => $retryCount + 1,
                        'scheduled_at' => $scheduledAt,
                        'updated_at' => new \DateTime()
                    ],
                    ['id' => $queueId]
                );

                $this->logger->info('Message scheduled for retry', [
                    'queue_id' => $queueId,
                    'retry_count' => $retryCount + 1,
                    'delay_seconds' => $delaySeconds
                ]);
            } else {
                $this->connection->update(
                    'message_queue',
                    [
                        'status' => 'failed',
                        'failed_at' => new \DateTime(),
                        'updated_at' => new \DateTime()
                    ],
                    ['id' => $queueId]
                );

                $this->logger->warning('Message failed after max retries', [
                    'queue_id' => $queueId,
                    'retry_count' => $retryCount
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to mark message as failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array {
        try {
            $pending = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                ['pending']
            );

            $processing = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                ['processing']
            );

            $processed = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                ['processed']
            );

            $failed = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                ['failed']
            );

            $avgProcessingTime = $this->connection->fetchOne(
                'SELECT AVG(EXTRACT(EPOCH FROM (processed_at - created_at))) 
                 FROM message_queue WHERE processed_at IS NOT NULL'
            );

            return [
                'pending' => (int) $pending,
                'processing' => (int) $processing,
                'processed' => (int) $processed,
                'failed' => (int) $failed,
                'total' => (int) ($pending + $processing + $processed + $failed),
                'avg_processing_time_seconds' => (int) ($avgProcessingTime ?? 0),
                'queue_utilization' => round((($pending + $processing) / self::MAX_QUEUE_SIZE) * 100, 2) . '%'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Process queue (batch processing)
     */
    public function processQueue(int $batchSize = 50): array {
        try {
            $processed = 0;
            $failed = 0;

            for ($i = 0; $i < $batchSize; $i++) {
                $message = $this->dequeue();
                
                if (!$message) {
                    break;
                }

                // Simulate processing
                // In real implementation, this would call WebSocket broadcast
                try {
                    $this->markProcessed($message['id']);
                    $processed++;
                } catch (\Exception $e) {
                    $this->markFailed($message['id']);
                    $failed++;
                }
            }

            return [
                'batch_size' => $batchSize,
                'processed' => $processed,
                'failed' => $failed,
                'remaining' => (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM message_queue WHERE status = ?',
                    ['pending']
                )
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to process queue', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup old processed messages
     */
    public function cleanup(int $daysOld = 7): int {
        try {
            $cutoffDate = new \DateTime("-{$daysOld} days");

            $result = $this->connection->executeStatement(
                'DELETE FROM message_queue WHERE status = ? AND processed_at < ?',
                ['processed', $cutoffDate]
            );

            $this->logger->info('Old messages cleaned up', [
                'count' => $result,
                'days_old' => $daysOld
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup old messages', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get queue by status
     */
    public function getQueueByStatus(string $status, int $limit = 100): array {
        try {
            $sql = 'SELECT * FROM message_queue WHERE status = ?
                    ORDER BY priority DESC, created_at ASC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$status, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get queue by status', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get failed messages for retry analysis
     */
    public function getFailedMessages(int $limit = 100): array {
        try {
            $sql = 'SELECT * FROM message_queue WHERE status = ?
                    ORDER BY retry_count DESC, failed_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, ['failed', $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get failed messages', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Requeue failed message
     */
    public function requeue(string $queueId): bool {
        try {
            $this->connection->update(
                'message_queue',
                [
                    'status' => 'pending',
                    'retry_count' => 0,
                    'scheduled_at' => new \DateTime(),
                    'updated_at' => new \DateTime()
                ],
                ['id' => $queueId]
            );

            $this->logger->info('Message requeued', [
                'queue_id' => $queueId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to requeue message', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get queue event types
     */
    public function getEventTypeStats(): array {
        try {
            $sql = 'SELECT event_type, COUNT(*) as count, status
                    FROM message_queue 
                    GROUP BY event_type, status
                    ORDER BY count DESC';

            return $this->connection->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get event type stats', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
