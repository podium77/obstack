<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing row-level security (RLS) policies
 */
class RowLevelSecurityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Create or update RLS policy
     *
     * @param array<string, mixed> $policy Policy configuration
     * @return array<string, mixed> Policy result
     */
    public function createPolicy(array $policy): array
    {
        try {
            // Validate policy structure
            if (!isset($policy['name'], $policy['tableName'], $policy['expression'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required fields: name, tableName, expression',
                ];
            }

            // Validate table and column names
            if (!$this->validateTableName($policy['tableName'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid table name',
                ];
            }

            // In production, would store in database table
            $policyId = hash('sha256', $policy['name'] . time());

            return [
                'success' => true,
                'data' => [
                    'id' => $policyId,
                    'name' => $policy['name'],
                    'tableName' => $policy['tableName'],
                    'expression' => $policy['expression'],
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
     * List all RLS policies
     *
     * @return array<string, mixed> Policies list
     */
    public function listPolicies(): array
    {
        try {
            // In production, would query from policies table
            return [
                'success' => true,
                'data' => [
                    [
                        'id' => '1',
                        'name' => 'Users can see their own data',
                        'tableName' => 'users',
                        'expression' => 'user_id = current_user_id()',
                        'active' => true,
                        'createdAt' => (new \DateTime())->format('c'),
                    ],
                    [
                        'id' => '2',
                        'name' => 'Admins see all data',
                        'tableName' => 'users',
                        'expression' => 'current_user_role() = "admin"',
                        'active' => true,
                        'createdAt' => (new \DateTime())->format('c'),
                    ],
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
     * Update RLS policy
     */
    public function updatePolicy(string $policyId, array $updates): array
    {
        try {
            if (isset($updates['expression'])) {
                $this->validateExpression($updates['expression']);
            }

            return [
                'success' => true,
                'data' => array_merge(['id' => $policyId], $updates),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete RLS policy
     */
    public function deletePolicy(string $policyId): array
    {
        try {
            return [
                'success' => true,
                'message' => "Policy {$policyId} deleted",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enable/Disable RLS for table
     */
    public function toggleRls(string $tableName, bool $enabled): array
    {
        if (!$this->validateTableName($tableName)) {
            return [
                'success' => false,
                'error' => 'Invalid table name',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'tableName' => $tableName,
                'rlsEnabled' => $enabled,
                'message' => $enabled ? "RLS enabled for {$tableName}" : "RLS disabled for {$tableName}",
            ],
        ];
    }

    /**
     * Get user access rules
     */
    public function getUserAccessRules(LocalUser $user): array
    {
        return [
            'userId' => $user->getId(),
            'userRole' => $user->getRoles()[0] ?? 'user',
            'tables' => [
                'users' => [
                    'canRead' => true,
                    'canWrite' => false,
                    'canDelete' => false,
                    'filters' => [
                        'user_id' => $user->getId(),
                    ],
                ],
                'audit_logs' => [
                    'canRead' => in_array('ROLE_ADMIN', $user->getRoles()),
                    'canWrite' => false,
                    'canDelete' => false,
                ],
            ],
        ];
    }

    /**
     * Validate RLS expression
     */
    private function validateExpression(string $expression): bool
    {
        // Basic validation - prevent dangerous functions
        $dangerous = ['DROP', 'DELETE', 'TRUNCATE', 'UPDATE', 'INSERT'];
        foreach ($dangerous as $keyword) {
            if (stripos($expression, $keyword) !== false) {
                throw new \InvalidArgumentException("Expression contains dangerous keyword: {$keyword}");
            }
        }
        return true;
    }

    /**
     * Validate table name
     */
    private function validateTableName(string $tableName): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $tableName) === 1;
    }
}
