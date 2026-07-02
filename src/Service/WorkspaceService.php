<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;

/**
 * Service for workspace management
 */
class WorkspaceService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Create a new workspace
     */
    public function createWorkspace(array $workspace, LocalUser $owner): array
    {
        try {
            if (!isset($workspace['name']) || empty($workspace['name'])) {
                return [
                    'success' => false,
                    'error' => 'Workspace name is required',
                ];
            }

            $workspaceId = uniqid();
            
            $this->connection->insert('workspaces', [
                'id' => $workspaceId,
                'name' => $workspace['name'],
                'description' => $workspace['description'] ?? '',
                'owner_id' => $owner->getId(),
                'is_public' => $workspace['isPublic'] ?? false,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            // Add owner as member with admin role
            $this->connection->insert('workspace_members', [
                'workspace_id' => $workspaceId,
                'user_id' => $owner->getId(),
                'role' => 'admin',
                'joined_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Workspace created',
                'data' => [
                    'id' => $workspaceId,
                    'name' => $workspace['name'],
                    'description' => $workspace['description'] ?? '',
                    'ownerId' => $owner->getId(),
                    'isPublic' => $workspace['isPublic'] ?? false,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user's workspaces
     */
    public function getUserWorkspaces(LocalUser $user): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT w.id, w.name, w.description, w.owner_id, w.is_public, 
                        COUNT(wm.user_id) as member_count, w.created_at
                 FROM workspaces w
                 LEFT JOIN workspace_members wm ON w.id = wm.workspace_id
                 WHERE w.owner_id = ? OR w.id IN (
                    SELECT workspace_id FROM workspace_members WHERE user_id = ?
                 )
                 GROUP BY w.id
                 ORDER BY w.created_at DESC',
                [$user->getId(), $user->getId()]
            );

            return [
                'success' => true,
                'data' => $stmt->fetchAllAssociative(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get workspace members
     */
    public function getWorkspaceMembers(string $workspaceId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT wm.user_id, wm.role, wm.joined_at, u.display_name, u.email
                 FROM workspace_members wm
                 JOIN local_user u ON wm.user_id = u.id
                 WHERE wm.workspace_id = ?
                 ORDER BY wm.joined_at ASC',
                [$workspaceId]
            );

            return [
                'success' => true,
                'data' => $stmt->fetchAllAssociative(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add member to workspace
     */
    public function addWorkspaceMember(string $workspaceId, int $userId, string $role = 'member'): array
    {
        try {
            if (!in_array($role, ['admin', 'member', 'viewer'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid role',
                ];
            }

            $this->connection->insert('workspace_members', [
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'role' => $role,
                'joined_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Member added to workspace',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove member from workspace
     */
    public function removeWorkspaceMember(string $workspaceId, int $userId): array
    {
        try {
            $this->connection->delete('workspace_members', [
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'message' => 'Member removed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update member role
     */
    public function updateMemberRole(string $workspaceId, int $userId, string $role): array
    {
        try {
            $this->connection->update('workspace_members', 
                ['role' => $role],
                ['workspace_id' => $workspaceId, 'user_id' => $userId]
            );

            return [
                'success' => true,
                'message' => 'Member role updated',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get workspace queries
     */
    public function getWorkspaceQueries(string $workspaceId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT q.id, q.name, q.connection_id, q.user_id, u.display_name, q.created_at
                 FROM workspace_queries wq
                 JOIN queries q ON wq.query_id = q.id
                 JOIN local_user u ON q.user_id = u.id
                 WHERE wq.workspace_id = ?
                 ORDER BY q.created_at DESC',
                [$workspaceId]
            );

            return [
                'success' => true,
                'data' => $stmt->fetchAllAssociative(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add query to workspace
     */
    public function addQueryToWorkspace(string $workspaceId, int $queryId): array
    {
        try {
            $this->connection->insert('workspace_queries', [
                'workspace_id' => $workspaceId,
                'query_id' => $queryId,
                'added_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Query added to workspace',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get workspace statistics
     */
    public function getWorkspaceStats(string $workspaceId): array
    {
        try {
            // Member count
            $membersStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM workspace_members WHERE workspace_id = ?',
                [$workspaceId]
            );
            $members = $membersStmt->fetchAssociative();

            // Query count
            $queriesStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM workspace_queries WHERE workspace_id = ?',
                [$workspaceId]
            );
            $queries = $queriesStmt->fetchAssociative();

            // Last activity
            $activityStmt = $this->connection->executeQuery(
                'SELECT MAX(created_at) as last_activity FROM workspace_queries WHERE workspace_id = ?',
                [$workspaceId]
            );
            $activity = $activityStmt->fetchAssociative();

            return [
                'success' => true,
                'data' => [
                    'memberCount' => (int)($members['count'] ?? 0),
                    'queryCount' => (int)($queries['count'] ?? 0),
                    'lastActivity' => $activity['last_activity'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
