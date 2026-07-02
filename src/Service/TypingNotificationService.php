<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Typing Notification Service
 * 
 * Manages typing indicators showing who is currently typing in a document.
 * Handles typing start/stop events and broadcast of typing status.
 */
class TypingNotificationService
{
    private const TYPING_TIMEOUT = 10; // seconds

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Record user is typing
     */
    public function recordTyping(
        int $userId,
        string $documentId,
        ?int $line = null,
        ?int $column = null,
        int $charactersAdded = 1
    ): array {
        try {
            $now = new \DateTime();
            $expiresAt = (new \DateTime())->modify('+' . self::TYPING_TIMEOUT . ' seconds');

            // Check if record exists
            $existing = $this->connection->fetchOne(
                'SELECT id FROM user_typing WHERE user_id = ? AND document_id = ?',
                [$userId, $documentId]
            );

            if ($existing) {
                $this->connection->update(
                    'user_typing',
                    [
                        'last_typed' => $now,
                        'expires_at' => $expiresAt,
                        'characters_typed' => $this->connection->quote('characters_typed') . ' + ' . $charactersAdded,
                        'updated_at' => $now
                    ],
                    ['user_id' => $userId, 'document_id' => $documentId]
                );
            } else {
                $id = Uuid::v4()->toRfc4122();
                $this->connection->insert('user_typing', [
                    'id' => $id,
                    'user_id' => $userId,
                    'document_id' => $documentId,
                    'line' => $line,
                    'column' => $column,
                    'characters_typed' => $charactersAdded,
                    'last_typed' => $now,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            }

            $this->logger->debug('Typing recorded', [
                'user_id' => $userId,
                'document_id' => $documentId
            ]);

            return [
                'user_id' => $userId,
                'document_id' => $documentId,
                'typing' => true,
                'position' => $line !== null ? ['line' => $line, 'column' => $column] : null,
                'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to record typing', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Record user stopped typing
     */
    public function recordStoppedTyping(int $userId, string $documentId): bool {
        try {
            $this->connection->delete(
                'user_typing',
                ['user_id' => $userId, 'document_id' => $documentId]
            );

            $this->logger->debug('Typing stopped', [
                'user_id' => $userId,
                'document_id' => $documentId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to record stopped typing', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all users currently typing in document
     */
    public function getTypingUsers(string $documentId): array {
        try {
            $sql = 'SELECT ut.*, u.name, u.email FROM user_typing ut
                    INNER JOIN "user" u ON ut.user_id = u.id
                    WHERE ut.document_id = ? AND ut.expires_at > NOW()
                    ORDER BY ut.last_typed DESC';

            $typingUsers = $this->connection->fetchAllAssociative($sql, [$documentId]);

            return array_map(function($typing) {
                return [
                    'user_id' => (int) $typing['user_id'],
                    'user_name' => $typing['name'],
                    'user_email' => $typing['email'],
                    'position' => $typing['line'] !== null ? [
                        'line' => (int) $typing['line'],
                        'column' => (int) $typing['column']
                    ] : null,
                    'characters_typed' => (int) $typing['characters_typed'],
                    'last_typed' => $typing['last_typed'],
                    'expires_at' => $typing['expires_at']
                ];
            }, $typingUsers);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get typing users', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get count of users typing
     */
    public function getTypingCount(string $documentId): int {
        try {
            return (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM user_typing WHERE document_id = ? AND expires_at > NOW()',
                [$documentId]
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to get typing count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Check if user is typing
     */
    public function isUserTyping(int $userId, string $documentId): bool {
        try {
            $result = $this->connection->fetchOne(
                'SELECT expires_at FROM user_typing WHERE user_id = ? AND document_id = ?',
                [$userId, $documentId]
            );

            if (!$result) {
                return false;
            }

            $expiresAt = new \DateTime($result);
            return $expiresAt > new \DateTime();
        } catch (\Exception $e) {
            $this->logger->error('Failed to check if user is typing', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get typing stats for document
     */
    public function getTypingStats(string $documentId): array {
        try {
            $typingUsers = $this->getTypingUsers($documentId);

            $totalCharacters = 0;
            foreach ($typingUsers as $user) {
                $totalCharacters += $user['characters_typed'];
            }

            return [
                'document_id' => $documentId,
                'users_typing' => count($typingUsers),
                'total_characters_typed' => $totalCharacters,
                'typing_users' => $typingUsers
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get typing stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get typing activity for user
     */
    public function getUserTypingActivity(int $userId, string $documentId): ?array {
        try {
            $sql = 'SELECT ut.*, u.name FROM user_typing ut
                    INNER JOIN "user" u ON ut.user_id = u.id
                    WHERE ut.user_id = ? AND ut.document_id = ?
                    AND ut.expires_at > NOW()';

            return $this->connection->fetchAssociative($sql, [$userId, $documentId]) ?: null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user typing activity', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Cleanup expired typing records
     */
    public function cleanupExpiredTyping(): int {
        try {
            $result = $this->connection->executeStatement(
                'DELETE FROM user_typing WHERE expires_at < NOW()'
            );

            $this->logger->debug('Expired typing records cleaned up', [
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup expired typing', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Clear user typing status
     */
    public function clearUserTyping(int $userId): int {
        try {
            $result = $this->connection->delete(
                'user_typing',
                ['user_id' => $userId]
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear user typing', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Broadcast typing status
     */
    public function broadcastTypingStatus(int $userId, string $documentId): array {
        $typingUsers = $this->getTypingUsers($documentId);
        $userTyping = array_filter($typingUsers, fn($u) => $u['user_id'] === $userId);

        return [
            'document_id' => $documentId,
            'user_id' => $userId,
            'is_typing' => !empty($userTyping),
            'typing_users_count' => count($typingUsers),
            'all_typing_users' => $typingUsers,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Get typing burst (rapid typing activity)
     */
    public function detectTypingBurst(string $documentId, int $characterThreshold = 50): array {
        try {
            $typingUsers = $this->getTypingUsers($documentId);

            $bursts = array_filter($typingUsers, function($user) use ($characterThreshold) {
                return $user['characters_typed'] >= $characterThreshold;
            });

            return [
                'document_id' => $documentId,
                'burst_count' => count($bursts),
                'burst_users' => $bursts
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to detect typing burst', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get typing timeline for document
     */
    public function getTypingTimeline(string $documentId, int $limit = 100): array {
        try {
            $sql = 'SELECT ut.*, u.name FROM user_typing ut
                    INNER JOIN "user" u ON ut.user_id = u.id
                    WHERE ut.document_id = ?
                    ORDER BY ut.created_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$documentId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get typing timeline', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
