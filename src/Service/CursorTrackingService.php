<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Cursor Tracking Service
 * 
 * Manages real-time cursor position tracking for collaborative editing.
 * Tracks cursor position, selection ranges, and broadcasts updates to other users.
 */
class CursorTrackingService
{
    private const CURSOR_TIMEOUT = 60; // seconds

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Update cursor position
     */
    public function updateCursorPosition(
        int $userId,
        string $documentId,
        int $line,
        int $column,
        ?int $selectionStartLine = null,
        ?int $selectionStartColumn = null,
        ?int $selectionEndLine = null,
        ?int $selectionEndColumn = null
    ): array {
        try {
            $now = new \DateTime();
            $id = Uuid::v4()->toRfc4122();

            $this->connection->insert('cursor_positions', [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'line' => $line,
                'column' => $column,
                'selection_start_line' => $selectionStartLine,
                'selection_start_column' => $selectionStartColumn,
                'selection_end_line' => $selectionEndLine,
                'selection_end_column' => $selectionEndColumn,
                'updated_at' => $now,
                'created_at' => $now
            ]);

            $this->logger->debug('Cursor position updated', [
                'user_id' => $userId,
                'document_id' => $documentId,
                'position' => "{$line}:{$column}"
            ]);

            return [
                'user_id' => $userId,
                'document_id' => $documentId,
                'cursor' => [
                    'line' => $line,
                    'column' => $column
                ],
                'selection' => $selectionStartLine !== null ? [
                    'start' => ['line' => $selectionStartLine, 'column' => $selectionStartColumn],
                    'end' => ['line' => $selectionEndLine, 'column' => $selectionEndColumn]
                ] : null,
                'timestamp' => $now->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update cursor position', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get cursor positions for all users in document
     */
    public function getDocumentCursors(string $documentId): array {
        try {
            $sql = 'SELECT cp.*, u.name, u.email 
                    FROM cursor_positions cp
                    INNER JOIN "user" u ON cp.user_id = u.id
                    WHERE cp.document_id = ? 
                    AND cp.created_at > NOW() - INTERVAL \'1 minute\'
                    ORDER BY cp.updated_at DESC';

            $cursors = $this->connection->fetchAllAssociative($sql, [$documentId]);

            return array_map(function($cursor) {
                return [
                    'user_id' => $cursor['user_id'],
                    'user_name' => $cursor['name'],
                    'user_email' => $cursor['email'],
                    'cursor' => [
                        'line' => (int) $cursor['line'],
                        'column' => (int) $cursor['column']
                    ],
                    'selection' => $cursor['selection_start_line'] !== null ? [
                        'start' => [
                            'line' => (int) $cursor['selection_start_line'],
                            'column' => (int) $cursor['selection_start_column']
                        ],
                        'end' => [
                            'line' => (int) $cursor['selection_end_line'],
                            'column' => (int) $cursor['selection_end_column']
                        ]
                    ] : null,
                    'updated_at' => $cursor['updated_at']
                ];
            }, $cursors);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get document cursors', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get user's current cursor position
     */
    public function getUserCursor(int $userId, string $documentId): ?array {
        try {
            $sql = 'SELECT cp.*, u.name, u.email FROM cursor_positions cp
                    INNER JOIN "user" u ON cp.user_id = u.id
                    WHERE cp.user_id = ? AND cp.document_id = ?
                    ORDER BY cp.updated_at DESC LIMIT 1';

            $cursor = $this->connection->fetchAssociative($sql, [$userId, $documentId]);

            if (!$cursor) {
                return null;
            }

            return [
                'user_id' => (int) $cursor['user_id'],
                'user_name' => $cursor['name'],
                'cursor' => [
                    'line' => (int) $cursor['line'],
                    'column' => (int) $cursor['column']
                ],
                'selection' => $cursor['selection_start_line'] !== null ? [
                    'start' => [
                        'line' => (int) $cursor['selection_start_line'],
                        'column' => (int) $cursor['selection_start_column']
                    ],
                    'end' => [
                        'line' => (int) $cursor['selection_end_line'],
                        'column' => (int) $cursor['selection_end_column']
                    ]
                ] : null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user cursor', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all active cursors for a user across documents
     */
    public function getUserActiveCursors(int $userId): array {
        try {
            $sql = 'SELECT cp.*, u.name FROM cursor_positions cp
                    INNER JOIN "user" u ON cp.user_id = u.id
                    WHERE cp.user_id = ? 
                    AND cp.updated_at > NOW() - INTERVAL \'1 minute\'
                    ORDER BY cp.updated_at DESC';

            return $this->connection->fetchAllAssociative($sql, [$userId]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user active cursors', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Clear cursor when user leaves document
     */
    public function clearCursor(int $userId, string $documentId): bool {
        try {
            $this->connection->delete(
                'cursor_positions',
                [
                    'user_id' => $userId,
                    'document_id' => $documentId
                ]
            );

            $this->logger->debug('Cursor cleared', [
                'user_id' => $userId,
                'document_id' => $documentId
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear cursor', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clear all cursors for user
     */
    public function clearUserCursors(int $userId): int {
        try {
            $result = $this->connection->delete(
                'cursor_positions',
                ['user_id' => $userId]
            );

            $this->logger->debug('User cursors cleared', [
                'user_id' => $userId,
                'count' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear user cursors', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Cleanup stale cursors
     */
    public function cleanupStaleCursors(int $timeoutSeconds = self::CURSOR_TIMEOUT): int {
        try {
            $cutoffTime = new \DateTime('-' . $timeoutSeconds . ' seconds');

            $result = $this->connection->executeStatement(
                'DELETE FROM cursor_positions WHERE updated_at < ?',
                [$cutoffTime]
            );

            $this->logger->info('Stale cursors cleaned up', [
                'count' => $result,
                'timeout_seconds' => $timeoutSeconds
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup stale cursors', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get cursor statistics for document
     */
    public function getDocumentCursorStats(string $documentId): array {
        try {
            $activeCursors = count($this->getDocumentCursors($documentId));

            $sql = 'SELECT COUNT(DISTINCT user_id) as unique_users FROM cursor_positions 
                    WHERE document_id = ? AND updated_at > NOW() - INTERVAL \'1 minute\'';

            $result = $this->connection->fetchAssociative($sql, [$documentId]);

            return [
                'document_id' => $documentId,
                'active_cursors' => $activeCursors,
                'unique_users' => (int) ($result['unique_users'] ?? 0)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get document cursor stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Broadcast cursor update
     */
    public function broadcastCursorUpdate(
        int $userId,
        string $documentId,
        int $line,
        int $column,
        ?array $selection = null
    ): array {
        return [
            'user_id' => $userId,
            'document_id' => $documentId,
            'cursor' => [
                'line' => $line,
                'column' => $column
            ],
            'selection' => $selection,
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ATOM)
        ];
    }

    /**
     * Get cursor history for document
     */
    public function getCursorHistory(string $documentId, int $limit = 100): array {
        try {
            $sql = 'SELECT cp.*, u.name, u.email FROM cursor_positions cp
                    INNER JOIN "user" u ON cp.user_id = u.id
                    WHERE cp.document_id = ?
                    ORDER BY cp.updated_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$documentId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get cursor history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Detect cursor collisions (overlapping selections)
     */
    public function detectCursorCollisions(string $documentId): array {
        try {
            $cursors = $this->getDocumentCursors($documentId);

            $collisions = [];
            $count = count($cursors);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $cursor1 = $cursors[$i];
                    $cursor2 = $cursors[$j];

                    if ($this->selectionsOverlap($cursor1['selection'], $cursor2['selection'])) {
                        $collisions[] = [
                            'user_1_id' => $cursor1['user_id'],
                            'user_2_id' => $cursor2['user_id'],
                            'overlap_region' => $this->calculateOverlapRegion(
                                $cursor1['selection'],
                                $cursor2['selection']
                            )
                        ];
                    }
                }
            }

            return [
                'document_id' => $documentId,
                'collision_count' => count($collisions),
                'collisions' => $collisions
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to detect cursor collisions', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Check if selections overlap
     */
    private function selectionsOverlap(?array $selection1, ?array $selection2): bool {
        if (!$selection1 || !$selection2) {
            return false;
        }

        $start1 = $selection1['start'];
        $end1 = $selection1['end'];
        $start2 = $selection2['start'];
        $end2 = $selection2['end'];

        return !($end1['line'] < $start2['line'] || $start1['line'] > $end2['line']);
    }

    /**
     * Calculate overlap region between selections
     */
    private function calculateOverlapRegion(?array $selection1, ?array $selection2): ?array {
        if (!$selection1 || !$selection2) {
            return null;
        }

        $start1 = $selection1['start'];
        $end1 = $selection1['end'];
        $start2 = $selection2['start'];
        $end2 = $selection2['end'];

        $overlapStart = max($start1['line'], $start2['line']);
        $overlapEnd = min($end1['line'], $end2['line']);

        return [
            'start_line' => $overlapStart,
            'end_line' => $overlapEnd
        ];
    }
}
