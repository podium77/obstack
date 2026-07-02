<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Comment Notification Service - Handle comment-related notifications
 */
class CommentNotificationService
{
    private const NOTIFICATION_TYPES = ['mentioned', 'replied', 'reacted', 'liked'];

    public function __construct(
        private Connection $connection,
        private PushNotificationService $pushNotificationService,
    ) {}

    /**
     * Notify users when mentioned in comment
     */
    public function notifyMentions(string $commentId, int $authorId, array $mentionedUserIds): array
    {
        try {
            $comment = $this->connection->fetchAssociative(
                'SELECT c.content, q.name FROM comments c JOIN queries q ON c.query_id = q.id WHERE c.id = ?',
                [$commentId]
            );

            if (!$comment) {
                return ['success' => false, 'error' => 'Comment not found'];
            }

            $author = $this->connection->fetchAssociative(
                'SELECT display_name FROM local_user WHERE id = ?',
                [$authorId]
            );

            $notified = 0;
            foreach ($mentionedUserIds as $userId) {
                if ($userId !== $authorId) {
                    $result = $this->pushNotificationService->sendNotification(
                        $userId,
                        '💬 You were mentioned',
                        $author['display_name'] . ' mentioned you in query: ' . $comment['name'],
                        '/queries/' . $comment['name'],
                        ['comment_id' => $commentId, 'type' => 'mentioned']
                    );
                    if ($result['success']) {
                        $notified++;
                    }
                }
            }

            return ['success' => true, 'data' => ['notified' => $notified]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notify comment author on reply
     */
    public function notifyReply(string $parentCommentId, int $replyerId, string $replyContent): array
    {
        try {
            $parentComment = $this->connection->fetchAssociative(
                'SELECT user_id, content FROM comments WHERE id = ?',
                [$parentCommentId]
            );

            if (!$parentComment || !$parentComment['user_id']) {
                return ['success' => true, 'message' => 'No author to notify'];
            }

            $replier = $this->connection->fetchAssociative(
                'SELECT display_name FROM local_user WHERE id = ?',
                [$replyerId]
            );

            if ($parentComment['user_id'] !== $replyerId) {
                $this->pushNotificationService->sendNotification(
                    $parentComment['user_id'],
                    '💬 New reply to your comment',
                    $replier['display_name'] . ' replied: ' . substr($replyContent, 0, 50),
                    null,
                    ['comment_id' => $parentCommentId, 'type' => 'replied']
                );
            }

            return ['success' => true, 'data' => ['notified' => 1]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notify comment author on reaction
     */
    public function notifyReaction(string $commentId, int $reactorId, string $reactionType): array
    {
        try {
            $comment = $this->connection->fetchAssociative(
                'SELECT user_id FROM comments WHERE id = ?',
                [$commentId]
            );

            if (!$comment || !$comment['user_id'] || $comment['user_id'] === $reactorId) {
                return ['success' => true, 'message' => 'No author to notify'];
            }

            $reactor = $this->connection->fetchAssociative(
                'SELECT display_name FROM local_user WHERE id = ?',
                [$reactorId]
            );

            $this->pushNotificationService->sendNotification(
                $comment['user_id'],
                '👍 Reaction to your comment',
                $reactor['display_name'] . ' reacted with ' . $reactionType,
                null,
                ['comment_id' => $commentId, 'type' => 'reacted']
            );

            return ['success' => true, 'data' => ['notified' => 1]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notify comment author on vote
     */
    public function notifyVote(string $commentId, int $voterId, string $voteType): array
    {
        try {
            $comment = $this->connection->fetchAssociative(
                'SELECT user_id FROM comments WHERE id = ?',
                [$commentId]
            );

            if (!$comment || !$comment['user_id'] || $comment['user_id'] === $voterId) {
                return ['success' => true, 'message' => 'No author to notify'];
            }

            $voter = $this->connection->fetchAssociative(
                'SELECT display_name FROM local_user WHERE id = ?',
                [$voterId]
            );

            $emoji = $voteType === 'up' ? '👍' : '👎';
            $this->pushNotificationService->sendNotification(
                $comment['user_id'],
                $emoji . ' Vote on your comment',
                $voter['display_name'] . ' ' . ($voteType === 'up' ? 'upvoted' : 'downvoted') . ' your comment',
                null,
                ['comment_id' => $commentId, 'type' => 'voted', 'vote_type' => $voteType]
            );

            return ['success' => true, 'data' => ['notified' => 1]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get notification preferences for user
     */
    public function getPreferences(int $userId): array
    {
        try {
            $prefs = $this->connection->fetchAssociative(
                'SELECT * FROM notification_preferences WHERE user_id = ?',
                [$userId]
            );

            if (!$prefs) {
                // Return defaults
                return ['success' => true, 'data' => [
                    'notify_mentions' => true,
                    'notify_replies' => true,
                    'notify_reactions' => true,
                    'notify_votes' => true,
                    'digest_frequency' => 'daily'
                ]];
            }

            return ['success' => true, 'data' => $prefs];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(int $userId, array $preferences): array
    {
        try {
            $exists = $this->connection->fetchOne(
                'SELECT id FROM notification_preferences WHERE user_id = ?',
                [$userId]
            );

            if ($exists) {
                $this->connection->update('notification_preferences', $preferences, ['user_id' => $userId]);
            } else {
                $preferences['user_id'] = $userId;
                $this->connection->insert('notification_preferences', $preferences);
            }

            return ['success' => true, 'message' => 'Preferences updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Batch notify watchers of comment activity
     */
    public function notifyCommentWatchers(string $commentId, string $eventType, int $initiatorId): array
    {
        try {
            // Get all users who participated in this comment thread
            $watchers = $this->connection->fetchAllAssociative(
                'SELECT DISTINCT user_id FROM comments WHERE parent_id = ? OR id = ?',
                [$commentId, $commentId]
            );

            $userIds = array_column($watchers, 'user_id');
            $userIds = array_filter($userIds, fn($id) => $id !== $initiatorId);

            return ['success' => true, 'data' => ['watchers' => count($userIds)]];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
