<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * WebSocket Gateway Service
 * 
 * Core service for managing WebSocket connections, event handling,
 * and real-time messaging through Socket.io infrastructure.
 */
class WebSocketGatewayService
{
    public const EVENT_USER_JOINED = 'user:joined';
    public const EVENT_USER_LEFT = 'user:left';
    public const EVENT_MESSAGE_SENT = 'message:sent';
    public const EVENT_PRESENCE_UPDATED = 'presence:updated';
    public const EVENT_CURSOR_MOVED = 'cursor:moved';
    public const EVENT_TYPING_STARTED = 'typing:started';
    public const EVENT_TYPING_STOPPED = 'typing:stopped';
    public const EVENT_EDIT_APPLIED = 'edit:applied';
    public const EVENT_CONFLICT_DETECTED = 'conflict:detected';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Register WebSocket event listener
     */
    public function registerEventListener(
        string $eventType,
        string $handlerClass,
        string $handlerMethod
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_event_listeners', [
                'id' => $id,
                'event_type' => $eventType,
                'handler_class' => $handlerClass,
                'handler_method' => $handlerMethod,
                'is_active' => true,
                'created_at' => $now
            ]);

            $this->logger->info('Event listener registered', [
                'event_type' => $eventType,
                'handler' => "{$handlerClass}::{$handlerMethod}"
            ]);

            return [
                'id' => $id,
                'event_type' => $eventType,
                'handler' => "{$handlerClass}::{$handlerMethod}",
                'status' => 'registered'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register event listener', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Emit WebSocket event
     */
    public function emitEvent(
        string $eventType,
        array $data,
        ?int $userId = null,
        ?string $roomId = null,
        string $broadcastScope = 'room' // 'room', 'user', 'broadcast'
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_events', [
                'id' => $id,
                'event_type' => $eventType,
                'data' => json_encode($data),
                'user_id' => $userId,
                'room_id' => $roomId,
                'broadcast_scope' => $broadcastScope,
                'emitted_at' => $now,
                'created_at' => $now
            ]);

            $this->logger->debug('WebSocket event emitted', [
                'event_type' => $eventType,
                'scope' => $broadcastScope,
                'user_id' => $userId
            ]);

            return [
                'event_id' => $id,
                'event_type' => $eventType,
                'scope' => $broadcastScope,
                'emitted_at' => $now->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to emit event', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get event listeners for event type
     */
    public function getEventListeners(string $eventType): array {
        try {
            $sql = 'SELECT * FROM websocket_event_listeners 
                    WHERE event_type = ? AND is_active = true';

            return $this->connection->fetchAllAssociative($sql, [$eventType]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get event listeners', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get recent events
     */
    public function getRecentEvents(int $limit = 100, ?string $eventType = null): array {
        try {
            if ($eventType) {
                $sql = 'SELECT * FROM websocket_events 
                        WHERE event_type = ?
                        ORDER BY emitted_at DESC
                        LIMIT ?';
                return $this->connection->fetchAllAssociative($sql, [$eventType, $limit]);
            }

            $sql = 'SELECT * FROM websocket_events 
                    ORDER BY emitted_at DESC
                    LIMIT ?';
            return $this->connection->fetchAllAssociative($sql, [$limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get recent events', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get room events
     */
    public function getRoomEvents(string $roomId, int $limit = 100): array {
        try {
            $sql = 'SELECT * FROM websocket_events 
                    WHERE room_id = ?
                    ORDER BY emitted_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$roomId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room events', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Register namespace
     */
    public function registerNamespace(
        string $namespaceName,
        ?string $pattern = null
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_namespaces', [
                'id' => $id,
                'name' => $namespaceName,
                'pattern' => $pattern,
                'is_active' => true,
                'created_at' => $now
            ]);

            $this->logger->info('WebSocket namespace registered', [
                'namespace' => $namespaceName
            ]);

            return [
                'id' => $id,
                'namespace' => $namespaceName,
                'pattern' => $pattern,
                'status' => 'registered'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register namespace', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create broadcast room
     */
    public function createRoom(
        string $roomId,
        string $roomType,
        ?int $maxCapacity = null,
        array $metadata = []
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_rooms', [
                'id' => $id,
                'room_id' => $roomId,
                'room_type' => $roomType,
                'max_capacity' => $maxCapacity,
                'metadata' => json_encode($metadata),
                'is_active' => true,
                'created_at' => $now
            ]);

            $this->logger->info('WebSocket room created', [
                'room_id' => $roomId,
                'room_type' => $roomType
            ]);

            return [
                'id' => $id,
                'room_id' => $roomId,
                'room_type' => $roomType,
                'status' => 'created'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create room', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get gateway statistics
     */
    public function getGatewayStats(): array {
        try {
            $activeConnections = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_connections WHERE is_active = true'
            );

            $activeRooms = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_rooms WHERE is_active = true'
            );

            $registeredListeners = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_event_listeners WHERE is_active = true'
            );

            $recentEvents = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_events WHERE emitted_at > NOW() - INTERVAL \'1 hour\''
            );

            $eventTypes = $this->connection->fetchAllAssociative(
                'SELECT event_type, COUNT(*) as count FROM websocket_events 
                 WHERE emitted_at > NOW() - INTERVAL \'1 hour\'
                 GROUP BY event_type
                 ORDER BY count DESC'
            );

            return [
                'active_connections' => (int) $activeConnections,
                'active_rooms' => (int) $activeRooms,
                'registered_listeners' => (int) $registeredListeners,
                'recent_events_1h' => (int) $recentEvents,
                'event_types' => $eventTypes,
                'gateway_status' => 'operational'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get gateway stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Log event metrics
     */
    public function logEventMetric(
        string $eventType,
        int $latencyMs,
        bool $success = true,
        ?string $errorMessage = null
    ): bool {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_event_metrics', [
                'id' => $id,
                'event_type' => $eventType,
                'latency_ms' => $latencyMs,
                'success' => $success,
                'error_message' => $errorMessage,
                'created_at' => $now
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to log event metric', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get event metrics
     */
    public function getEventMetrics(string $eventType): array {
        try {
            $sql = 'SELECT 
                    event_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN success THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN success THEN 0 ELSE 1 END) as failed,
                    AVG(latency_ms) as avg_latency_ms,
                    MIN(latency_ms) as min_latency_ms,
                    MAX(latency_ms) as max_latency_ms
                    FROM websocket_event_metrics 
                    WHERE event_type = ? AND created_at > NOW() - INTERVAL \'1 hour\'
                    GROUP BY event_type';

            $result = $this->connection->fetchAssociative($sql, [$eventType]);

            return $result ?: [
                'event_type' => $eventType,
                'total' => 0,
                'successful' => 0,
                'failed' => 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get event metrics', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get all registered namespaces
     */
    public function getRegisteredNamespaces(): array {
        try {
            $sql = 'SELECT * FROM websocket_namespaces WHERE is_active = true';
            return $this->connection->fetchAllAssociative($sql);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get namespaces', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Cleanup old events
     */
    public function cleanupOldEvents(int $daysOld = 7): int {
        try {
            $cutoffDate = new \DateTime("-{$daysOld} days");

            $result = $this->connection->executeStatement(
                'DELETE FROM websocket_events WHERE emitted_at < ?',
                [$cutoffDate]
            );

            $this->logger->info('Old events cleaned up', [
                'count' => $result,
                'days_old' => $daysOld
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup old events', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get connection throughput
     */
    public function getConnectionThroughput(int $intervalMinutes = 5): array {
        try {
            $since = new \DateTime("-{$intervalMinutes} minutes");

            $connections = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_connections WHERE last_heartbeat > ?',
                [$since]
            );

            $events = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_events WHERE emitted_at > ?',
                [$since]
            );

            $messages = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM realtime_messages WHERE created_at > ?',
                [$since]
            );

            return [
                'interval_minutes' => $intervalMinutes,
                'active_connections' => (int) $connections,
                'events_emitted' => (int) $events,
                'messages_sent' => (int) $messages,
                'events_per_second' => round((int) $events / ($intervalMinutes * 60), 2),
                'messages_per_second' => round((int) $messages / ($intervalMinutes * 60), 2)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get connection throughput', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }
}
