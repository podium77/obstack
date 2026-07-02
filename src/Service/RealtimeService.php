<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class RealtimeService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Register a user connection
     */
    public function registerConnection(int $userId, string $connectionId, string $clientIp): array
    {
        try {
            $id = Uuid::v4()->toRfc4122();
            
            $this->connection->insert('user_connections', [
                'id' => $id,
                'user_id' => $userId,
                'connection_id' => $connectionId,
                'client_ip' => $clientIp,
                'connected_at' => date('Y-m-d H:i:s'),
                'last_ping' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'data' => [
                    'connectionId' => $connectionId,
                    'userId' => $userId,
                    'connectedAt' => date('Y-m-d H:i:s'),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to register connection: ' . $e->getMessage()];
        }
    }

    /**
     * Unregister a user connection
     */
    public function unregisterConnection(string $connectionId): array
    {
        try {
            $this->connection->delete('user_connections', ['connection_id' => $connectionId]);
            return ['success' => true, 'message' => 'Connection unregistered'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to unregister connection'];
        }
    }

    /**
     * Get active users for a workspace
     */
    public function getActiveUsers(string $workspaceId): array
    {
        try {
            $query = <<<SQL
                SELECT DISTINCT uc.user_id, lu.email, lu.display_name, 
                       COUNT(*) as connection_count, MAX(uc.last_ping) as last_activity
                FROM user_connections uc
                JOIN local_user lu ON uc.user_id = lu.id
                WHERE uc.workspace_id = ?
                  AND uc.last_ping > NOW() - INTERVAL '5 minutes'
                GROUP BY uc.user_id, lu.email, lu.display_name
                ORDER BY MAX(uc.last_ping) DESC
            SQL;

            $result = $this->connection->fetchAllAssociative($query, [$workspaceId]);
            
            return [
                'success' => true,
                'data' => $result,
                'count' => count($result),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve active users'];
        }
    }

    /**
     * Update connection last ping (heartbeat)
     */
    public function updateConnectionHeartbeat(string $connectionId): array
    {
        try {
            $this->connection->update(
                'user_connections',
                ['last_ping' => date('Y-m-d H:i:s')],
                ['connection_id' => $connectionId]
            );

            return ['success' => true, 'message' => 'Heartbeat updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to update heartbeat'];
        }
    }

    /**
     * Subscribe to workspace updates
     */
    public function subscribeToWorkspace(string $connectionId, string $workspaceId): array
    {
        try {
            $this->connection->update(
                'user_connections',
                ['workspace_id' => $workspaceId],
                ['connection_id' => $connectionId]
            );

            return ['success' => true, 'message' => 'Subscribed to workspace'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to subscribe to workspace'];
        }
    }

    /**
     * Unsubscribe from workspace
     */
    public function unsubscribeFromWorkspace(string $connectionId): array
    {
        try {
            $this->connection->update(
                'user_connections',
                ['workspace_id' => null],
                ['connection_id' => $connectionId]
            );

            return ['success' => true, 'message' => 'Unsubscribed from workspace'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to unsubscribe'];
        }
    }

    /**
     * Get connection details
     */
    public function getConnectionDetails(string $connectionId): array
    {
        try {
            $result = $this->connection->fetchAssociative(
                'SELECT * FROM user_connections WHERE connection_id = ?',
                [$connectionId]
            );

            if (!$result) {
                return ['success' => false, 'error' => 'Connection not found'];
            }

            return ['success' => true, 'data' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve connection'];
        }
    }

    /**
     * Cleanup stale connections
     */
    public function cleanupStaleConnections(int $timeoutMinutes = 30): array
    {
        try {
            $cutoffTime = date('Y-m-d H:i:s', strtotime("-$timeoutMinutes minutes"));
            
            $result = $this->connection->delete(
                'user_connections',
                ['last_ping < ' => $cutoffTime]
            );

            return [
                'success' => true,
                'message' => "Removed $result stale connections",
                'count' => $result,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to cleanup connections'];
        }
    }
}
