<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * WebSocket Server Management Service
 * 
 * Manages WebSocket server lifecycle, connections, rooms, and message routing.
 * Tracks active connections, manages room subscriptions, and handles disconnections.
 */
class WebSocketServerService
{
    private const HEARTBEAT_INTERVAL = 30; // seconds
    private const CONNECTION_TIMEOUT = 300; // 5 minutes
    private const ROOM_PREFIX = 'ws_room_';
    private const USER_CONNECTION_PREFIX = 'ws_user_';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Register a new WebSocket connection
     */
    public function registerConnection(
        int $userId,
        string $connectionId,
        ?string $workspaceId = null,
        ?string $documentId = null,
        ?string $clientIp = null
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_connections', [
                'id' => $id,
                'user_id' => $userId,
                'connection_id' => $connectionId,
                'workspace_id' => $workspaceId,
                'document_id' => $documentId,
                'client_ip' => $clientIp,
                'connected_at' => $now,
                'last_heartbeat' => $now,
                'is_active' => true
            ]);

            $this->logger->info('WebSocket connection registered', [
                'connection_id' => $connectionId,
                'user_id' => $userId,
                'workspace_id' => $workspaceId
            ]);

            return [
                'id' => $id,
                'connection_id' => $connectionId,
                'user_id' => $userId,
                'status' => 'connected'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to register connection', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Unregister a WebSocket connection
     */
    public function unregisterConnection(string $connectionId): bool {
        try {
            $this->connection->update(
                'websocket_connections',
                ['is_active' => false],
                ['connection_id' => $connectionId]
            );

            $this->logger->info('WebSocket connection unregistered', [
                'connection_id' => $connectionId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to unregister connection', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send heartbeat to keep connection alive
     */
    public function sendHeartbeat(string $connectionId): bool {
        try {
            $now = new \DateTime();
            $this->connection->update(
                'websocket_connections',
                ['last_heartbeat' => $now],
                ['connection_id' => $connectionId]
            );
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send heartbeat', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Subscribe connection to a room
     */
    public function subscribeToRoom(string $connectionId, string $roomId): bool {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('websocket_room_subscriptions', [
                'id' => $id,
                'connection_id' => $connectionId,
                'room_id' => $roomId,
                'subscribed_at' => $now
            ]);

            $this->logger->info('Connection subscribed to room', [
                'connection_id' => $connectionId,
                'room_id' => $roomId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to subscribe to room', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Unsubscribe connection from a room
     */
    public function unsubscribeFromRoom(string $connectionId, string $roomId): bool {
        try {
            $this->connection->delete(
                'websocket_room_subscriptions',
                [
                    'connection_id' => $connectionId,
                    'room_id' => $roomId
                ]
            );

            $this->logger->info('Connection unsubscribed from room', [
                'connection_id' => $connectionId,
                'room_id' => $roomId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to unsubscribe from room', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all active connections in a room
     */
    public function getRoomConnections(string $roomId): array {
        try {
            $sql = 'SELECT wc.* FROM websocket_connections wc 
                    INNER JOIN websocket_room_subscriptions wrs ON wc.connection_id = wrs.connection_id
                    WHERE wrs.room_id = ? AND wc.is_active = true';

            return $this->connection->fetchAllAssociative($sql, [$roomId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room connections', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all connections for a specific user
     */
    public function getUserConnections(int $userId): array {
        try {
            $sql = 'SELECT * FROM websocket_connections WHERE user_id = ? AND is_active = true';
            return $this->connection->fetchAllAssociative($sql, [$userId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user connections', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all connections in a workspace
     */
    public function getWorkspaceConnections(string $workspaceId): array {
        try {
            $sql = 'SELECT * FROM websocket_connections WHERE workspace_id = ? AND is_active = true';
            return $this->connection->fetchAllAssociative($sql, [$workspaceId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get workspace connections', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all connections in a document
     */
    public function getDocumentConnections(string $documentId): array {
        try {
            $sql = 'SELECT * FROM websocket_connections WHERE document_id = ? AND is_active = true';
            return $this->connection->fetchAllAssociative($sql, [$documentId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get document connections', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get server statistics
     */
    public function getServerStats(): array {
        try {
            $totalConnections = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM websocket_connections WHERE is_active = true'
            );

            $totalRooms = $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT room_id) FROM websocket_room_subscriptions'
            );

            $activeWorkspaces = $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT workspace_id) FROM websocket_connections WHERE workspace_id IS NOT NULL AND is_active = true'
            );

            $activeDocuments = $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT document_id) FROM websocket_connections WHERE document_id IS NOT NULL AND is_active = true'
            );

            $avgConnectionTime = $this->connection->fetchOne(
                'SELECT AVG(EXTRACT(EPOCH FROM (NOW() - connected_at))) FROM websocket_connections WHERE is_active = true'
            );

            return [
                'total_active_connections' => (int) $totalConnections,
                'total_rooms' => (int) $totalRooms,
                'active_workspaces' => (int) $activeWorkspaces,
                'active_documents' => (int) $activeDocuments,
                'avg_connection_time_seconds' => (int) ($avgConnectionTime ?? 0),
                'server_status' => 'running'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get server stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get room statistics
     */
    public function getRoomStats(string $roomId): array {
        try {
            $subscribers = count($this->getRoomConnections($roomId));

            $sql = 'SELECT COUNT(DISTINCT user_id) as unique_users FROM websocket_connections 
                    WHERE room_id = ? OR (workspace_id IN (SELECT workspace_id FROM websocket_connections WHERE room_id = ?))
                    AND is_active = true';

            $result = $this->connection->fetchAssociative($sql, [$roomId, $roomId]);

            return [
                'room_id' => $roomId,
                'subscribers' => $subscribers,
                'unique_users' => (int) ($result['unique_users'] ?? 0)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Cleanup stale connections
     */
    public function cleanupStaleConnections(int $timeoutSeconds = self::CONNECTION_TIMEOUT): int {
        try {
            $cutoffTime = new \DateTime('-' . $timeoutSeconds . ' seconds');

            $result = $this->connection->executeStatement(
                'UPDATE websocket_connections SET is_active = false 
                 WHERE last_heartbeat < ? AND is_active = true',
                [$cutoffTime]
            );

            $this->logger->info('Stale connections cleaned up', [
                'count' => $result,
                'timeout_seconds' => $timeoutSeconds
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup stale connections', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Broadcast message to all connections in a room
     */
    public function broadcastToRoom(string $roomId, string $eventType, array $data): array {
        $connections = $this->getRoomConnections($roomId);
        return [
            'room_id' => $roomId,
            'event_type' => $eventType,
            'target_connections' => count($connections),
            'connections' => $connections,
            'data' => $data
        ];
    }

    /**
     * Broadcast message to all connections in a workspace
     */
    public function broadcastToWorkspace(string $workspaceId, string $eventType, array $data): array {
        $connections = $this->getWorkspaceConnections($workspaceId);
        return [
            'workspace_id' => $workspaceId,
            'event_type' => $eventType,
            'target_connections' => count($connections),
            'connections' => $connections,
            'data' => $data
        ];
    }

    /**
     * Broadcast message to all connections on a document
     */
    public function broadcastToDocument(string $documentId, string $eventType, array $data): array {
        $connections = $this->getDocumentConnections($documentId);
        return [
            'document_id' => $documentId,
            'event_type' => $eventType,
            'target_connections' => count($connections),
            'connections' => $connections,
            'data' => $data
        ];
    }

    /**
     * Send message to specific user's connections
     */
    public function sendToUser(int $userId, string $eventType, array $data): array {
        $connections = $this->getUserConnections($userId);
        return [
            'user_id' => $userId,
            'event_type' => $eventType,
            'target_connections' => count($connections),
            'connections' => $connections,
            'data' => $data
        ];
    }

    /**
     * Cleanup room subscriptions
     */
    public function cleanupRoomSubscriptions(): int {
        try {
            $result = $this->connection->executeStatement(
                'DELETE FROM websocket_room_subscriptions 
                 WHERE connection_id NOT IN (SELECT connection_id FROM websocket_connections WHERE is_active = true)'
            );

            $this->logger->info('Room subscriptions cleaned up', [
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup room subscriptions', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get connection info
     */
    public function getConnectionInfo(string $connectionId): ?array {
        try {
            return $this->connection->fetchAssociative(
                'SELECT * FROM websocket_connections WHERE connection_id = ?',
                [$connectionId]
            ) ?: null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get connection info', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
