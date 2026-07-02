<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;

/**
 * Service for query comments and annotations
 */
class CommentService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Add comment to query
     */
    public function addComment(int $queryId, LocalUser $author, string $content, ?int $parentCommentId = null): array
    {
        try {
            if (empty(trim($content))) {
                return [
                    'success' => false,
                    'error' => 'Comment content cannot be empty',
                ];
            }

            $commentId = uniqid();

            $this->connection->insert('query_comments', [
                'id' => $commentId,
                'query_id' => $queryId,
                'author_id' => $author->getId(),
                'content' => $content,
                'parent_comment_id' => $parentCommentId,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Comment added',
                'data' => [
                    'id' => $commentId,
                    'queryId' => $queryId,
                    'authorId' => $author->getId(),
                    'authorName' => $author->getDisplayName(),
                    'content' => $content,
                    'parentCommentId' => $parentCommentId,
                    'createdAt' => (new \DateTime())->format('c'),
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
     * Get query comments with replies
     */
    public function getComments(int $queryId): array
    {
        try {
            // Get root comments
            $stmt = $this->connection->executeQuery(
                'SELECT qc.id, qc.query_id, qc.author_id, qc.content, 
                        qc.created_at, qc.updated_at, u.display_name, u.email,
                        COUNT(DISTINCT qc2.id) as reply_count
                 FROM query_comments qc
                 JOIN local_user u ON qc.author_id = u.id
                 LEFT JOIN query_comments qc2 ON qc2.parent_comment_id = qc.id
                 WHERE qc.query_id = ? AND qc.parent_comment_id IS NULL
                 GROUP BY qc.id
                 ORDER BY qc.created_at DESC',
                [$queryId]
            );

            $comments = $stmt->fetchAllAssociative();

            // Get replies for each comment
            foreach ($comments as &$comment) {
                $repliesStmt = $this->connection->executeQuery(
                    'SELECT qc.id, qc.author_id, qc.content, qc.created_at, 
                            qc.updated_at, u.display_name, u.email
                     FROM query_comments qc
                     JOIN local_user u ON qc.author_id = u.id
                     WHERE qc.parent_comment_id = ?
                     ORDER BY qc.created_at ASC',
                    [$comment['id']]
                );
                $comment['replies'] = $repliesStmt->fetchAllAssociative();
            }

            return [
                'success' => true,
                'data' => $comments,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update comment
     */
    public function updateComment(string $commentId, string $content, LocalUser $user): array
    {
        try {
            // Verify user is comment author
            $stmt = $this->connection->executeQuery(
                'SELECT author_id FROM query_comments WHERE id = ?',
                [$commentId]
            );
            $comment = $stmt->fetchAssociative();

            if (!$comment || (int)$comment['author_id'] !== $user->getId()) {
                return [
                    'success' => false,
                    'error' => 'Not authorized to edit this comment',
                ];
            }

            $this->connection->update('query_comments', 
                [
                    'content' => $content,
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ],
                ['id' => $commentId]
            );

            return [
                'success' => true,
                'message' => 'Comment updated',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete comment
     */
    public function deleteComment(string $commentId, LocalUser $user): array
    {
        try {
            // Verify user is comment author
            $stmt = $this->connection->executeQuery(
                'SELECT author_id FROM query_comments WHERE id = ?',
                [$commentId]
            );
            $comment = $stmt->fetchAssociative();

            if (!$comment || (int)$comment['author_id'] !== $user->getId()) {
                return [
                    'success' => false,
                    'error' => 'Not authorized to delete this comment',
                ];
            }

            // Delete replies first
            $this->connection->delete('query_comments', ['parent_comment_id' => $commentId]);
            
            // Delete comment
            $this->connection->delete('query_comments', ['id' => $commentId]);

            return [
                'success' => true,
                'message' => 'Comment deleted',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get comment statistics
     */
    public function getCommentStats(int $queryId): array
    {
        try {
            // Total comments
            $totalStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM query_comments WHERE query_id = ?',
                [$queryId]
            );
            $total = $totalStmt->fetchAssociative();

            // Root comments
            $rootStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM query_comments 
                 WHERE query_id = ? AND parent_comment_id IS NULL',
                [$queryId]
            );
            $root = $rootStmt->fetchAssociative();

            // Recent comments
            $recentStmt = $this->connection->executeQuery(
                'SELECT COUNT(*) as count FROM query_comments 
                 WHERE query_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                [$queryId]
            );
            $recent = $recentStmt->fetchAssociative();

            // Top contributors
            $topStmt = $this->connection->executeQuery(
                'SELECT u.id, u.display_name, COUNT(*) as count
                 FROM query_comments qc
                 JOIN local_user u ON qc.author_id = u.id
                 WHERE qc.query_id = ?
                 GROUP BY qc.author_id
                 ORDER BY count DESC
                 LIMIT 5',
                [$queryId]
            );
            $top = $topStmt->fetchAllAssociative();

            return [
                'success' => true,
                'data' => [
                    'totalComments' => (int)($total['count'] ?? 0),
                    'rootComments' => (int)($root['count'] ?? 0),
                    'recentComments' => (int)($recent['count'] ?? 0),
                    'topContributors' => $top,
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
     * Add annotation to query
     */
    public function addAnnotation(int $queryId, LocalUser $author, array $annotation): array
    {
        try {
            if (!isset($annotation['type'], $annotation['position'])) {
                return [
                    'success' => false,
                    'error' => 'Type and position are required',
                ];
            }

            $annotationId = uniqid();

            $this->connection->insert('query_annotations', [
                'id' => $annotationId,
                'query_id' => $queryId,
                'author_id' => $author->getId(),
                'type' => $annotation['type'],
                'position' => (int)$annotation['position'],
                'content' => $annotation['content'] ?? '',
                'color' => $annotation['color'] ?? '#FFFF00',
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => 'Annotation added',
                'data' => [
                    'id' => $annotationId,
                    'queryId' => $queryId,
                    'authorId' => $author->getId(),
                    'type' => $annotation['type'],
                    'position' => $annotation['position'],
                    'content' => $annotation['content'] ?? '',
                    'color' => $annotation['color'] ?? '#FFFF00',
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
     * Get query annotations
     */
    public function getAnnotations(int $queryId): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT qa.id, qa.type, qa.position, qa.content, qa.color, 
                        qa.author_id, u.display_name, qa.created_at
                 FROM query_annotations qa
                 JOIN local_user u ON qa.author_id = u.id
                 WHERE qa.query_id = ?
                 ORDER BY qa.position ASC',
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
}
