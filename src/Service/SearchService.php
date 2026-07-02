<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

class SearchService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Advanced search for queries
     */
    public function searchQueries(
        string $term,
        ?string $workspaceId = null,
        ?int $userId = null,
        string $sortBy = 'relevance',
        int $limit = 50,
        int $offset = 0
    ): array {
        try {
            // Build search query with relevance scoring
            $query = <<<SQL
                SELECT q.id, q.name, q.description, q.query_text,
                       q.connection_id, q.owner_id, q.created_at, q.updated_at,
                       c.name as connection_name,
                       lu.display_name as owner_name,
                       CASE
                           WHEN q.name ILIKE ? THEN 3
                           WHEN q.description ILIKE ? THEN 2
                           WHEN q.query_text ILIKE ? THEN 1
                           ELSE 0
                       END as relevance_score
                FROM queries q
                LEFT JOIN connections c ON q.connection_id = c.id
                LEFT JOIN local_user lu ON q.owner_id = lu.id
                WHERE (q.name ILIKE ? OR q.description ILIKE ? OR q.query_text ILIKE ?)
            SQL;

            $params = [
                "%$term%",  // name ILIKE
                "%$term%",  // description ILIKE
                "%$term%",  // query_text ILIKE
                "%$term%",  // name ILIKE (WHERE clause)
                "%$term%",  // description ILIKE (WHERE clause)
                "%$term%",  // query_text ILIKE (WHERE clause)
            ];

            // Add workspace filter if specified
            if ($workspaceId) {
                $query .= <<<SQL
                     AND q.id IN (
                        SELECT query_id FROM workspace_queries 
                        WHERE workspace_id = ?
                     )
                SQL;
                $params[] = $workspaceId;
            }

            // Add user filter if specified
            if ($userId) {
                $query .= ' AND (q.owner_id = ? OR q.id IN (SELECT query_id FROM query_shares WHERE shared_with_user_id = ? OR shared_with_group_id IN (SELECT group_id FROM group_members WHERE user_id = ?)))';
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            }

            // Sort
            if ($sortBy === 'relevance') {
                $query .= ' ORDER BY relevance_score DESC, q.updated_at DESC';
            } elseif ($sortBy === 'recent') {
                $query .= ' ORDER BY q.updated_at DESC';
            } elseif ($sortBy === 'oldest') {
                $query .= ' ORDER BY q.created_at ASC';
            } elseif ($sortBy === 'name') {
                $query .= ' ORDER BY q.name ASC';
            }

            $query .= ' LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;

            $results = $this->connection->fetchAllAssociative($query, $params);

            // Count total matches
            $countQuery = <<<SQL
                SELECT COUNT(*) as count FROM queries q
                WHERE (q.name ILIKE ? OR q.description ILIKE ? OR q.query_text ILIKE ?)
            SQL;
            
            $countParams = ["%$term%", "%$term%", "%$term%"];
            
            if ($workspaceId) {
                $countQuery .= ' AND q.id IN (SELECT query_id FROM workspace_queries WHERE workspace_id = ?)';
                $countParams[] = $workspaceId;
            }

            $total = $this->connection->fetchOne($countQuery, $countParams);

            return [
                'success' => true,
                'data' => $results,
                'total' => (int)$total,
                'count' => count($results),
                'query' => $term,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Search failed: ' . $e->getMessage()];
        }
    }

    /**
     * Filter queries with multiple criteria
     */
    public function filterQueries(array $filters): array
    {
        try {
            $query = 'SELECT q.id, q.name, q.description, q.created_at, q.updated_at, q.owner_id, c.name as connection_name FROM queries q LEFT JOIN connections c ON q.connection_id = c.id WHERE 1=1';
            $params = [];

            // Connection filter
            if (!empty($filters['connectionId'])) {
                $query .= ' AND q.connection_id = ?';
                $params[] = $filters['connectionId'];
            }

            // Owner filter
            if (!empty($filters['ownerId'])) {
                $query .= ' AND q.owner_id = ?';
                $params[] = $filters['ownerId'];
            }

            // Workspace filter
            if (!empty($filters['workspaceId'])) {
                $query .= ' AND q.id IN (SELECT query_id FROM workspace_queries WHERE workspace_id = ?)';
                $params[] = $filters['workspaceId'];
            }

            // Date range filter
            if (!empty($filters['createdAfter'])) {
                $query .= ' AND q.created_at >= ?';
                $params[] = $filters['createdAfter'];
            }

            if (!empty($filters['createdBefore'])) {
                $query .= ' AND q.created_at <= ?';
                $params[] = $filters['createdBefore'];
            }

            // Sort
            $sortBy = $filters['sortBy'] ?? 'updated_at';
            $sortOrder = $filters['sortOrder'] ?? 'DESC';
            $query .= " ORDER BY q.$sortBy $sortOrder LIMIT ? OFFSET ?";
            $params[] = $filters['limit'] ?? 50;
            $params[] = $filters['offset'] ?? 0;

            $results = $this->connection->fetchAllAssociative($query, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Filter failed: ' . $e->getMessage()];
        }
    }

    /**
     * Search for comments
     */
    public function searchComments(string $term, int $limit = 50): array
    {
        try {
            $query = <<<SQL
                SELECT qc.id, qc.query_id, qc.content, qc.created_at, qc.author_id,
                       lu.display_name as author_name,
                       q.name as query_name
                FROM query_comments qc
                LEFT JOIN local_user lu ON qc.author_id = lu.id
                LEFT JOIN queries q ON qc.query_id = q.id
                WHERE qc.content ILIKE ?
                ORDER BY qc.created_at DESC
                LIMIT ?
            SQL;

            $results = $this->connection->fetchAllAssociative(
                $query,
                ["%$term%", $limit]
            );

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Comment search failed'];
        }
    }

    /**
     * Search for users in workspaces
     */
    public function searchUsers(string $term, ?string $workspaceId = null, int $limit = 20): array
    {
        try {
            $query = <<<SQL
                SELECT lu.id, lu.email, lu.display_name, lu.created_at
                FROM local_user lu
                WHERE (lu.email ILIKE ? OR lu.display_name ILIKE ?)
            SQL;

            $params = ["%$term%", "%$term%"];

            if ($workspaceId) {
                $query .= <<<SQL
                     AND lu.id IN (
                        SELECT user_id FROM workspace_members
                        WHERE workspace_id = ?
                     )
                SQL;
                $params[] = $workspaceId;
            }

            $query .= ' ORDER BY lu.display_name ASC LIMIT ?';
            $params[] = $limit;

            $results = $this->connection->fetchAllAssociative($query, $params);

            return [
                'success' => true,
                'data' => $results,
                'count' => count($results),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'User search failed'];
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(string $term, int $limit = 10): array
    {
        try {
            // Get popular query names
            $queries = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT name FROM queries WHERE name ILIKE ? ORDER BY name ASC LIMIT ?',
                ["%$term%", $limit]
            );

            // Get popular tags/workspaces
            $workspaces = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT name FROM workspaces WHERE name ILIKE ? ORDER BY name ASC LIMIT ?',
                ["%$term%", $limit]
            );

            $suggestions = [
                'queries' => array_column($queries, 'name'),
                'workspaces' => array_column($workspaces, 'name'),
            ];

            return [
                'success' => true,
                'data' => $suggestions,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to get suggestions'];
        }
    }
}
