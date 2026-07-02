<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Conflict Resolution Service
 * 
 * Handles conflict detection and resolution for concurrent document edits
 * using Operational Transformation (OT) principles.
 */
class ConflictResolutionService
{
    public const CONFLICT_TYPE_INSERT_INSERT = 'insert_insert';
    public const CONFLICT_TYPE_INSERT_DELETE = 'insert_delete';
    public const CONFLICT_TYPE_DELETE_DELETE = 'delete_delete';
    public const CONFLICT_TYPE_OVERLAPPING = 'overlapping';

    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger
    ) {}

    /**
     * Record an edit operation
     */
    public function recordOperation(
        int $userId,
        string $documentId,
        string $operationType,
        int $position,
        ?string $content = null,
        int $length = 0
    ): array {
        try {
            $id = Uuid::v4()->toRfc4122();
            $now = new \DateTime();

            $this->connection->insert('edit_operations', [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'operation_type' => $operationType,
                'position' => $position,
                'content' => $content,
                'length' => $length,
                'timestamp' => $now,
                'created_at' => $now
            ]);

            $this->logger->debug('Operation recorded', [
                'user_id' => $userId,
                'document_id' => $documentId,
                'operation_type' => $operationType
            ]);

            return [
                'id' => $id,
                'user_id' => $userId,
                'document_id' => $documentId,
                'operation_type' => $operationType,
                'position' => $position,
                'timestamp' => $now->format(\DateTimeInterface::ATOM)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to record operation', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Detect conflicts between concurrent operations
     */
    public function detectConflicts(string $documentId, array $operations): array {
        try {
            $conflicts = [];
            $count = count($operations);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $op1 = $operations[$i];
                    $op2 = $operations[$j];

                    $conflict = $this->compareOperations($op1, $op2);
                    if ($conflict) {
                        $conflicts[] = $conflict;
                    }
                }
            }

            return [
                'document_id' => $documentId,
                'conflict_count' => count($conflicts),
                'conflicts' => $conflicts,
                'has_conflicts' => count($conflicts) > 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to detect conflicts', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Transform operation against concurrent operation (OT algorithm)
     */
    public function transformOperation(array $op1, array $op2): array {
        try {
            // OT transformation logic
            $op1Type = $op1['operation_type'];
            $op2Type = $op2['operation_type'];
            $op1Pos = $op1['position'];
            $op2Pos = $op2['position'];

            // Insert vs Insert
            if ($op1Type === 'insert' && $op2Type === 'insert') {
                if ($op1Pos < $op2Pos) {
                    return $op1; // op1 stays same
                } elseif ($op1Pos > $op2Pos) {
                    return [
                        ...$op1,
                        'position' => $op1Pos + strlen($op2['content'] ?? '')
                    ];
                } else {
                    // Same position - use user ID as tiebreaker
                    return $op1['user_id'] < $op2['user_id'] ? $op1 : $op2;
                }
            }

            // Insert vs Delete
            if ($op1Type === 'insert' && $op2Type === 'delete') {
                if ($op1Pos <= $op2Pos) {
                    return $op1; // op1 stays same
                } elseif ($op1Pos >= $op2Pos + $op2['length']) {
                    return [
                        ...$op1,
                        'position' => $op1Pos - $op2['length']
                    ];
                } else {
                    // Overlapping
                    return [
                        ...$op1,
                        'position' => $op2Pos
                    ];
                }
            }

            // Delete vs Insert
            if ($op1Type === 'delete' && $op2Type === 'insert') {
                if ($op1Pos + $op1['length'] <= $op2Pos) {
                    return $op1; // op1 stays same
                } elseif ($op1Pos >= $op2Pos) {
                    return [
                        ...$op1,
                        'position' => $op1Pos + strlen($op2['content'] ?? '')
                    ];
                } else {
                    // Overlapping
                    return [
                        ...$op1,
                        'length' => $op1['length'] + strlen($op2['content'] ?? '')
                    ];
                }
            }

            // Delete vs Delete
            if ($op1Type === 'delete' && $op2Type === 'delete') {
                if ($op1Pos + $op1['length'] <= $op2Pos) {
                    return $op1;
                } elseif ($op1Pos >= $op2Pos + $op2['length']) {
                    return [
                        ...$op1,
                        'position' => $op1Pos - $op2['length']
                    ];
                } else {
                    // Overlapping deletes
                    $newPos = min($op1Pos, $op2Pos);
                    $newLength = max($op1Pos + $op1['length'], $op2Pos + $op2['length']) - $newPos;
                    return [
                        ...$op1,
                        'position' => $newPos,
                        'length' => $newLength
                    ];
                }
            }

            return $op1;
        } catch (\Exception $e) {
            $this->logger->error('Failed to transform operation', [
                'error' => $e->getMessage()
            ]);
            return $op1;
        }
    }

    /**
     * Resolve conflicts with chosen strategy
     */
    public function resolveConflict(string $conflictId, string $strategy): bool {
        try {
            $validStrategies = ['first_write_wins', 'last_write_wins', 'user_priority', 'merge'];

            if (!in_array($strategy, $validStrategies)) {
                throw new \InvalidArgumentException("Invalid conflict resolution strategy: $strategy");
            }

            $this->connection->update(
                'conflicts',
                [
                    'resolution_status' => 'resolved',
                    'resolution_strategy' => $strategy,
                    'resolved_at' => new \DateTime()
                ],
                ['id' => $conflictId]
            );

            $this->logger->info('Conflict resolved', [
                'conflict_id' => $conflictId,
                'strategy' => $strategy
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to resolve conflict', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get operation history for document
     */
    public function getOperationHistory(string $documentId, int $limit = 100): array {
        try {
            $sql = 'SELECT eo.*, u.name as user_name FROM edit_operations eo
                    INNER JOIN "user" u ON eo.user_id = u.id
                    WHERE eo.document_id = ?
                    ORDER BY eo.timestamp ASC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$documentId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get operation history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get conflict history
     */
    public function getConflictHistory(string $documentId, int $limit = 50): array {
        try {
            $sql = 'SELECT c.*, u1.name as user1_name, u2.name as user2_name 
                    FROM conflicts c
                    INNER JOIN "user" u1 ON c.user1_id = u1.id
                    INNER JOIN "user" u2 ON c.user2_id = u2.id
                    WHERE c.document_id = ?
                    ORDER BY c.detected_at DESC
                    LIMIT ?';

            return $this->connection->fetchAllAssociative($sql, [$documentId, $limit]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get conflict history', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Compare two operations for conflicts
     */
    private function compareOperations(array $op1, array $op2): ?array {
        $op1Pos = $op1['position'];
        $op2Pos = $op2['position'];
        $op1Type = $op1['operation_type'];
        $op2Type = $op2['operation_type'];
        $op1Length = $op1['length'] ?? strlen($op1['content'] ?? '');
        $op2Length = $op2['length'] ?? strlen($op2['content'] ?? '');

        // Check if operations overlap
        $op1End = $op1Pos + $op1Length;
        $op2End = $op2Pos + $op2Length;

        if ($op1Pos < $op2End && $op2Pos < $op1End) {
            $conflictType = $this->getConflictType($op1Type, $op2Type);

            return [
                'operation1_id' => $op1['id'] ?? null,
                'operation2_id' => $op2['id'] ?? null,
                'user1_id' => $op1['user_id'],
                'user2_id' => $op2['user_id'],
                'conflict_type' => $conflictType,
                'position_overlap' => [$op1Pos, $op2Pos, $op1End, $op2End],
                'severity' => $conflictType === self::CONFLICT_TYPE_OVERLAPPING ? 'high' : 'medium'
            ];
        }

        return null;
    }

    /**
     * Determine conflict type
     */
    private function getConflictType(string $type1, string $type2): string {
        if ($type1 === 'insert' && $type2 === 'insert') {
            return self::CONFLICT_TYPE_INSERT_INSERT;
        } elseif (($type1 === 'insert' && $type2 === 'delete') || 
                  ($type1 === 'delete' && $type2 === 'insert')) {
            return self::CONFLICT_TYPE_INSERT_DELETE;
        } elseif ($type1 === 'delete' && $type2 === 'delete') {
            return self::CONFLICT_TYPE_DELETE_DELETE;
        }
        return self::CONFLICT_TYPE_OVERLAPPING;
    }

    /**
     * Get conflict statistics
     */
    public function getConflictStats(string $documentId): array {
        try {
            $totalConflicts = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM conflicts WHERE document_id = ?',
                [$documentId]
            );

            $resolvedConflicts = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM conflicts WHERE document_id = ? AND resolution_status = ?',
                [$documentId, 'resolved']
            );

            $unresolvedConflicts = $this->connection->fetchOne(
                'SELECT COUNT(*) FROM conflicts WHERE document_id = ? AND resolution_status = ?',
                [$documentId, 'unresolved']
            );

            $byType = $this->connection->fetchAllAssociative(
                'SELECT conflict_type, COUNT(*) as count FROM conflicts 
                 WHERE document_id = ? GROUP BY conflict_type',
                [$documentId]
            );

            return [
                'document_id' => $documentId,
                'total_conflicts' => (int) $totalConflicts,
                'resolved' => (int) $resolvedConflicts,
                'unresolved' => (int) $unresolvedConflicts,
                'by_type' => $byType
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get conflict stats', [
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Apply operations in correct order
     */
    public function applyOperations(string $documentId): bool {
        try {
            // Get all operations in order
            $operations = $this->getOperationHistory($documentId);

            // Apply each operation
            foreach ($operations as $operation) {
                $this->connection->update(
                    'edit_operations',
                    ['applied' => true, 'applied_at' => new \DateTime()],
                    ['id' => $operation['id']]
                );
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to apply operations', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
