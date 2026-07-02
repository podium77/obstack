<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;

/**
 * Service for collaborative query sharing and management
 */
class CollaborationService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Share a query with users or groups
     */
    public function shareQuery(int $queryId, array $shareWith, string $accessLevel = 'view'): array
    {
        try {
            if (!in_array($accessLevel, ['view', 'edit', 'delete'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid access level',
                ];
            }

            // Share with users
            if (isset($shareWith['users'])) {
                foreach ($shareWith['users'] as $userId) {
                    $this->connection->insert('query_shares', [
                        'query_id' => $queryId,
                        'shared_with_user_id' => $userId,
                        'shared_with_group_id' => null,
                        'access_level' => $accessLevel,
                        'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Share with groups
            if (isset($shareWith['groups'])) {
                foreach ($shareWith['groups'] as $groupId) {
                    $this->connection->insert('query_shares', [
                        'query_id' => $queryId,
                        'shared_with_user_id' => null,
                        'shared_with_group_id' => $groupId,
                        'access_level' => $accessLevel,
                        'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Query shared successfully',
                'data' => [
                    'queryId' => $queryId,
                    'sharedWith' => $shareWith,
                    'accessLevel' => $accessLevel,
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
     * Get shared queries for a user
     */
    public function getSharedQueries(LocalUser $user): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT DISTINCT q.id, q.name, q.connection_id, q.query_text, 
                        u.display_name as owner, qs.access_level
                 FROM queries q
                 JOIN local_user u ON q.user_id = u.id
                 JOIN query_shares qs ON q.id = qs.query_id
                 LEFT JOIN access_control_groups acg ON qs.shared_with_group_id = acg.id
                 LEFT JOIN group_members gm ON acg.id = gm.group_id
                 WHERE (qs.shared_with_user_id = ? OR gm.user_id = ?)
                 ORDER BY q.created_at DESC',
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
     * Update share permissions
     */
    public function updateSharePermission(int $queryId, int $userId, string $accessLevel): array
    {
        try {
            $this->connection->update('query_shares', 
                ['access_level' => $accessLevel],
                ['query_id' => $queryId, 'shared_with_user_id' => $userId]
            );

            return [
                'success' => true,
                'message' => 'Permission updated',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Revoke share access
     */
    public function revokeShare(int $queryId, int $userId = null, int $groupId = null): array
    {
        try {
            $conditions = ['query_id' => $queryId];
            if ($userId) {
                $conditions['shared_with_user_id'] = $userId;
            }
            if ($groupId) {
                $conditions['shared_with_group_id'] = $groupId;
            }

            $this->connection->delete('query_shares', $conditions);

            return [
                'success' => true,
                'message' => 'Share revoked',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get query sharing details
     */
    public function getQueryShares(int $queryId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT qs.id, qs.shared_with_user_id, qs.shared_with_group_id, 
                        qs.access_level, u.display_name as user_name, 
                        acg.name as group_name, qs.created_at
                 FROM query_shares qs
                 LEFT JOIN local_user u ON qs.shared_with_user_id = u.id
                 LEFT JOIN access_control_groups acg ON qs.shared_with_group_id = acg.id
                 WHERE qs.query_id = ?',
                [$queryId]
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
     * Check if user can access query
     */
    public function canAccessQuery(LocalUser $user, int $queryId, string $requiredLevel = 'view'): bool
    {
        try {
            // Owner can always access
            $stmt = $this->connection->executeQuery(
                'SELECT user_id FROM queries WHERE id = ?',
                [$queryId]
            );
            $result = $stmt->fetchAssociative();
            if ($result && (int)$result['user_id'] === $user->getId()) {
                return true;
            }

            // Check if shared with user
            $stmt = $this->connection->executeQuery(
                'SELECT access_level FROM query_shares 
                 WHERE query_id = ? AND shared_with_user_id = ?',
                [$queryId, $user->getId()]
            );
            $share = $stmt->fetchAssociative();
            if ($share) {
                return $this->hasAccessLevel((string)$share['access_level'], $requiredLevel);
            }

            // Check if shared with any of user's groups
            $stmt = $this->connection->executeQuery(
                'SELECT qs.access_level FROM query_shares qs
                 JOIN group_members gm ON qs.shared_with_group_id = gm.group_id
                 WHERE qs.query_id = ? AND gm.user_id = ?',
                [$queryId, $user->getId()]
            );
            $groupShare = $stmt->fetchAssociative();
            if ($groupShare) {
                return $this->hasAccessLevel((string)$groupShare['access_level'], $requiredLevel);
            }

            return false;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Check if user has required access level
     */
    private function hasAccessLevel(string $userLevel, string $requiredLevel): bool
    {
        $levels = ['view' => 1, 'edit' => 2, 'delete' => 3];
        return ($levels[$userLevel] ?? 0) >= ($levels[$requiredLevel] ?? 0);
    }
}
