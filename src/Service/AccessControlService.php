<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Service for access control groups and permissions
 */
class AccessControlService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Create access control group
     */
    public function createGroup(array $group): array
    {
        try {
            if (!isset($group['name']) || empty($group['name'])) {
                return [
                    'success' => false,
                    'error' => 'Group name is required',
                ];
            }

            $groupId = uniqid();

            $this->connection->insert('access_control_groups', [
                'id' => $groupId,
                'name' => $group['name'],
                'description' => $group['description'] ?? '',
                'permissions' => json_encode($group['permissions'] ?? []),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Group created',
                'data' => [
                    'id' => $groupId,
                    'name' => $group['name'],
                    'description' => $group['description'] ?? '',
                    'permissions' => $group['permissions'] ?? [],
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
     * List all groups
     */
    public function listGroups(): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT acg.id, acg.name, acg.description, acg.permissions, 
                        COUNT(gm.user_id) as member_count, acg.created_at
                 FROM access_control_groups acg
                 LEFT JOIN group_members gm ON acg.id = gm.group_id
                 GROUP BY acg.id
                 ORDER BY acg.created_at DESC'
            );

            $groups = $stmt->fetchAllAssociative();
            
            // Parse JSON permissions
            foreach ($groups as &$group) {
                $group['permissions'] = json_decode((string)$group['permissions'], true) ?? [];
            }

            return [
                'success' => true,
                'data' => $groups,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get group details
     */
    public function getGroupDetails(string $groupId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM access_control_groups WHERE id = ?',
                [$groupId]
            );
            $group = $stmt->fetchAssociative();

            if (!$group) {
                return [
                    'success' => false,
                    'error' => 'Group not found',
                ];
            }

            $group['permissions'] = json_decode((string)$group['permissions'], true) ?? [];

            // Get members
            $membersStmt = $this->connection->executeQuery(
                'SELECT u.id, u.display_name, u.email, gm.joined_at
                 FROM group_members gm
                 JOIN local_user u ON gm.user_id = u.id
                 WHERE gm.group_id = ?
                 ORDER BY gm.joined_at ASC',
                [$groupId]
            );
            $group['members'] = $membersStmt->fetchAllAssociative();

            return [
                'success' => true,
                'data' => $group,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add member to group
     */
    public function addGroupMember(string $groupId, int $userId): array
    {
        try {
            $this->connection->insert('group_members', [
                'group_id' => $groupId,
                'user_id' => $userId,
                'joined_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Member added to group',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove member from group
     */
    public function removeGroupMember(string $groupId, int $userId): array
    {
        try {
            $this->connection->delete('group_members', [
                'group_id' => $groupId,
                'user_id' => $userId,
            ]);

            return [
                'success' => true,
                'message' => 'Member removed from group',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update group permissions
     */
    public function updateGroupPermissions(string $groupId, array $permissions): array
    {
        try {
            $this->connection->update('access_control_groups', 
                ['permissions' => json_encode($permissions)],
                ['id' => $groupId]
            );

            return [
                'success' => true,
                'message' => 'Permissions updated',
                'data' => [
                    'groupId' => $groupId,
                    'permissions' => $permissions,
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
     * Get user's groups
     */
    public function getUserGroups(int $userId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT acg.id, acg.name, acg.description, acg.permissions, gm.joined_at
                 FROM access_control_groups acg
                 JOIN group_members gm ON acg.id = gm.group_id
                 WHERE gm.user_id = ?
                 ORDER BY gm.joined_at DESC',
                [$userId]
            );

            $groups = $stmt->fetchAllAssociative();
            
            foreach ($groups as &$group) {
                $group['permissions'] = json_decode((string)$group['permissions'], true) ?? [];
            }

            return [
                'success' => true,
                'data' => $groups,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(int $userId, string $permission): bool
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT acg.permissions FROM access_control_groups acg
                 JOIN group_members gm ON acg.id = gm.group_id
                 WHERE gm.user_id = ?',
                [$userId]
            );

            $groups = $stmt->fetchAllAssociative();
            
            foreach ($groups as $group) {
                $permissions = json_decode((string)$group['permissions'], true) ?? [];
                if (in_array($permission, $permissions)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Delete group
     */
    public function deleteGroup(string $groupId): array
    {
        try {
            // Remove all members
            $this->connection->delete('group_members', ['group_id' => $groupId]);
            
            // Delete group
            $this->connection->delete('access_control_groups', ['id' => $groupId]);

            return [
                'success' => true,
                'message' => 'Group deleted',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
