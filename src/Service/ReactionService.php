<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class ReactionService
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * Add reaction to a comment
     */
    public function addReaction(string $commentId, int $userId, string $reactionType): array
    {
        try {
            // Check if reaction already exists
            $existing = $this->connection->fetchOne(
                'SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND reaction_type = ?',
                [$commentId, $userId, $reactionType]
            );

            if ($existing) {
                return ['success' => false, 'error' => 'You already reacted with this emoji'];
            }

            // Add reaction
            $id = Uuid::v4()->toRfc4122();
            $this->connection->insert('comment_reactions', [
                'id' => $id,
                'comment_id' => $commentId,
                'user_id' => $userId,
                'reaction_type' => $reactionType,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'data' => ['reactionId' => $id],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to add reaction'];
        }
    }

    /**
     * Remove reaction from a comment
     */
    public function removeReaction(string $commentId, int $userId, string $reactionType): array
    {
        try {
            $this->connection->delete('comment_reactions', [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'reaction_type' => $reactionType,
            ]);

            return ['success' => true, 'message' => 'Reaction removed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to remove reaction'];
        }
    }

    /**
     * Get reactions for a comment
     */
    public function getCommentReactions(string $commentId): array
    {
        try {
            $query = <<<SQL
                SELECT cr.reaction_type, COUNT(*) as count,
                       JSON_AGG(JSON_BUILD_OBJECT('userId', cr.user_id, 'displayName', lu.display_name))::text as users
                FROM comment_reactions cr
                LEFT JOIN local_user lu ON cr.user_id = lu.id
                WHERE cr.comment_id = ?
                GROUP BY cr.reaction_type
                ORDER BY count DESC
            SQL;

            $reactions = $this->connection->fetchAllAssociative($query, [$commentId]);

            // Decode users JSON
            foreach ($reactions as &$reaction) {
                $reaction['users'] = json_decode($reaction['users'] ?? '[]', true);
            }

            return [
                'success' => true,
                'data' => $reactions,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve reactions'];
        }
    }

    /**
     * Get user's reactions on a comment
     */
    public function getUserReactions(string $commentId, int $userId): array
    {
        try {
            $reactions = $this->connection->fetchAllAssociative(
                'SELECT reaction_type FROM comment_reactions WHERE comment_id = ? AND user_id = ?',
                [$commentId, $userId]
            );

            return [
                'success' => true,
                'data' => array_column($reactions, 'reaction_type'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve user reactions'];
        }
    }

    /**
     * Vote on a comment (thumbs up/down)
     */
    public function voteOnComment(string $commentId, int $userId, string $voteType): array
    {
        try {
            if (!in_array($voteType, ['up', 'down'])) {
                return ['success' => false, 'error' => 'Invalid vote type'];
            }

            // Check existing vote
            $existing = $this->connection->fetchOne(
                'SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?',
                [$commentId, $userId]
            );

            if ($existing) {
                if ($existing === $voteType) {
                    return ['success' => false, 'error' => 'You already voted this way'];
                }

                // Update existing vote
                $this->connection->update(
                    'comment_votes',
                    ['vote_type' => $voteType, 'updated_at' => date('Y-m-d H:i:s')],
                    ['comment_id' => $commentId, 'user_id' => $userId]
                );
            } else {
                // Create new vote
                $id = Uuid::v4()->toRfc4122();
                $this->connection->insert('comment_votes', [
                    'id' => $id,
                    'comment_id' => $commentId,
                    'user_id' => $userId,
                    'vote_type' => $voteType,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return ['success' => true, 'message' => 'Vote recorded'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to vote on comment'];
        }
    }

    /**
     * Get vote stats for a comment
     */
    public function getCommentVotes(string $commentId): array
    {
        try {
            $upvotes = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = ?',
                [$commentId, 'up']
            );

            $downvotes = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = ?',
                [$commentId, 'down']
            );

            $score = (int)$upvotes - (int)$downvotes;

            return [
                'success' => true,
                'data' => [
                    'upvotes' => (int)$upvotes,
                    'downvotes' => (int)$downvotes,
                    'score' => $score,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve vote stats'];
        }
    }

    /**
     * Remove vote from comment
     */
    public function removeVote(string $commentId, int $userId): array
    {
        try {
            $this->connection->delete('comment_votes', [
                'comment_id' => $commentId,
                'user_id' => $userId,
            ]);

            return ['success' => true, 'message' => 'Vote removed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to remove vote'];
        }
    }

    /**
     * Get most reacted comments in a query
     */
    public function getMostReactedComments(int $queryId, int $limit = 10): array
    {
        try {
            $query = <<<SQL
                SELECT qc.id, qc.content, qc.author_id, lu.display_name,
                       COUNT(DISTINCT cr.id) as reaction_count,
                       qc.created_at
                FROM query_comments qc
                LEFT JOIN comment_reactions cr ON qc.id = cr.comment_id
                LEFT JOIN local_user lu ON qc.author_id = lu.id
                WHERE qc.query_id = ?
                GROUP BY qc.id, qc.content, qc.author_id, lu.display_name, qc.created_at
                ORDER BY reaction_count DESC, qc.created_at DESC
                LIMIT ?
            SQL;

            $comments = $this->connection->fetchAllAssociative($query, [$queryId, $limit]);

            return [
                'success' => true,
                'data' => $comments,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve top comments'];
        }
    }

    /**
     * Get most voted comments in a query
     */
    public function getMostVotedComments(int $queryId, int $limit = 10): array
    {
        try {
            $query = <<<SQL
                SELECT qc.id, qc.content, qc.author_id, lu.display_name,
                       SUM(CASE WHEN cv.vote_type = 'up' THEN 1 ELSE 0 END) as upvotes,
                       SUM(CASE WHEN cv.vote_type = 'down' THEN 1 ELSE 0 END) as downvotes,
                       (SUM(CASE WHEN cv.vote_type = 'up' THEN 1 ELSE 0 END) - 
                        SUM(CASE WHEN cv.vote_type = 'down' THEN 1 ELSE 0 END)) as score,
                       qc.created_at
                FROM query_comments qc
                LEFT JOIN comment_votes cv ON qc.id = cv.comment_id
                LEFT JOIN local_user lu ON qc.author_id = lu.id
                WHERE qc.query_id = ?
                GROUP BY qc.id, qc.content, qc.author_id, lu.display_name, qc.created_at
                ORDER BY score DESC, qc.created_at DESC
                LIMIT ?
            SQL;

            $comments = $this->connection->fetchAllAssociative($query, [$queryId, $limit]);

            return [
                'success' => true,
                'data' => $comments,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to retrieve voted comments'];
        }
    }
}
