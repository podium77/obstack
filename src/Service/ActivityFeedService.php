<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class ActivityFeedService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Log an activity event
     */
    public function logActivity(
        string $eventType,
        ?string $workspaceId,
        int $userId,
        ?int $queryId,
        string $description,
        array $metadata = []
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            
            $this->connection->insert('activity_feed', [
                'id' => $id,
                'event_type' => $eventType,
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'query_id' => $queryId,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'data' => ['activityId' => $id],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to log activity'];
        }
    }

    /**
     * Get activity feed for a workspace
     */
    public function getWorkspaceActivityFeed(string $workspaceId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = <<<SQL
                SELECT af.id, af.event_type, af.user_id, af.query_id,
                       af.description, af.metadata, af.created_at,
                       lu.email, lu.display_name
                FROM activity_feed af
                LEFT JOIN local_user lu ON af.user_id = lu.id
                WHERE af.workspace_id = ?
                ORDER BY af.created_at DESC
                LIMIT ? OFFSET ?
            SQL;

            $activities = $this->connection->fetchAllAssociative(
                $query,
                [$workspaceId, $limit, $offset],
                [1 => \PDO::PARAM_STR, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT]
            );

            // Decode metadata
            foreach ($activities as &$activity) {
                $activity['metadata'] = json_decode($activity['metadata'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $activities,
                'count' => count($activities),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve activity feed'];
        }
    }

    /**
     * Get user's activity feed
     */
    public function getUserActivityFeed(int $userId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = <<<SQL
                SELECT af.id, af.event_type, af.workspace_id, af.query_id,
                       af.description, af.metadata, af.created_at,
                       lu.email, lu.display_name,
                       w.name as workspace_name
                FROM activity_feed af
                LEFT JOIN local_user lu ON af.user_id = lu.id
                LEFT JOIN workspaces w ON af.workspace_id = w.id
                WHERE af.user_id = ?
                ORDER BY af.created_at DESC
                LIMIT ? OFFSET ?
            SQL;

            $activities = $this->connection->fetchAllAssociative(
                $query,
                [$userId, $limit, $offset],
                [1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT, 3 => \PDO::PARAM_INT]
            );

            foreach ($activities as &$activity) {
                $activity['metadata'] = json_decode($activity['metadata'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $activities,
                'count' => count($activities),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve user activity'];
        }
    }

    /**
     * Get query activity events
     */
    public function getQueryActivity(int $queryId, int $limit = 50): array
    {
        try {
            $query = <<<SQL
                SELECT af.id, af.event_type, af.user_id, af.workspace_id,
                       af.description, af.metadata, af.created_at,
                       lu.email, lu.display_name
                FROM activity_feed af
                LEFT JOIN local_user lu ON af.user_id = lu.id
                WHERE af.query_id = ?
                ORDER BY af.created_at DESC
                LIMIT ?
            SQL;

            $activities = $this->connection->fetchAllAssociative(
                $query,
                [$queryId, $limit],
                [1 => \PDO::PARAM_INT, 2 => \PDO::PARAM_INT]
            );

            foreach ($activities as &$activity) {
                $activity['metadata'] = json_decode($activity['metadata'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $activities,
                'count' => count($activities),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve query activity'];
        }
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(string $workspaceId): array
    {
        try {
            // Total events
            $totalQuery = 'SELECT COUNT(*) as count FROM activity_feed WHERE workspace_id = ?';
            $total = $this->connection->fetchOne($totalQuery, [$workspaceId]);

            // Events by type
            $typeQuery = <<<SQL
                SELECT event_type, COUNT(*) as count
                FROM activity_feed
                WHERE workspace_id = ?
                GROUP BY event_type
                ORDER BY count DESC
            SQL;
            $byType = $this->connection->fetchAllAssociative($typeQuery, [$workspaceId]);

            // Top contributors
            $contributorsQuery = <<<SQL
                SELECT user_id, lu.display_name, COUNT(*) as count
                FROM activity_feed af
                LEFT JOIN local_user lu ON af.user_id = lu.id
                WHERE af.workspace_id = ?
                GROUP BY af.user_id, lu.display_name
                ORDER BY count DESC
                LIMIT 10
            SQL;
            $topContributors = $this->connection->fetchAllAssociative($contributorsQuery, [$workspaceId]);

            // Recent events (last 24 hours)
            $recentQuery = <<<SQL
                SELECT COUNT(*) as count
                FROM activity_feed
                WHERE workspace_id = ? AND created_at > NOW() - INTERVAL '1 day'
            SQL;
            $recent = $this->connection->fetchOne($recentQuery, [$workspaceId]);

            return [
                'success' => true,
                'data' => [
                    'totalEvents' => (int)$total,
                    'eventsByType' => $byType,
                    'topContributors' => $topContributors,
                    'recentEvents' => (int)$recent,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve activity stats'];
        }
    }

    /**
     * Filter activity by event type
     */
    public function filterActivityByType(string $workspaceId, string $eventType, int $limit = 50): array
    {
        try {
            $query = <<<SQL
                SELECT af.id, af.event_type, af.user_id, af.query_id,
                       af.description, af.metadata, af.created_at,
                       lu.email, lu.display_name
                FROM activity_feed af
                LEFT JOIN local_user lu ON af.user_id = lu.id
                WHERE af.workspace_id = ? AND af.event_type = ?
                ORDER BY af.created_at DESC
                LIMIT ?
            SQL;

            $activities = $this->connection->fetchAllAssociative(
                $query,
                [$workspaceId, $eventType, $limit]
            );

            foreach ($activities as &$activity) {
                $activity['metadata'] = json_decode($activity['metadata'] ?? '{}', true);
            }

            return [
                'success' => true,
                'data' => $activities,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to filter activities'];
        }
    }

    /**
     * Clear old activity logs
     */
    public function clearOldActivities(int $daysToKeep = 90): array
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));
            
            $result = $this->connection->delete(
                'activity_feed',
                ['created_at < ' => $cutoffDate]
            );

            return [
                'success' => true,
                'message' => "Removed $result old activities",
                'count' => $result,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to clear activities'];
        }
    }
}
