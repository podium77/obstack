<?php

namespace App\Service;

use App\Entity\LocalUser;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Connection;
use PDO;

/**
 * Service for performance metrics and query profiling
 */
class PerformanceService
{
    private array $metrics = [];
    private array $queryStats = [];

    public function __construct(
        private Connection $connection,
        private AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Get query performance statistics
     */
    public function getQueryMetrics(int $hours = 24): array
    {
        $sinceDate = new \DateTime("-$hours hours");

        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select('al.endpoint, COUNT(al.id) as executions, AVG(al.executionTime) as avgTime, MAX(al.executionTime) as maxTime, MIN(al.executionTime) as minTime')
            ->where('al.createdAt >= :since')
            ->andWhere('al.action = :action')
            ->setParameter('since', $sinceDate)
            ->setParameter('action', 'query_execute')
            ->groupBy('al.endpoint')
            ->orderBy('avgTime', 'DESC')
            ->setMaxResults(50);

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'endpoint' => $row['endpoint'] ?? 'unknown',
                'executions' => (int)($row['executions'] ?? 0),
                'avgTime' => round((float)($row['avgTime'] ?? 0), 2),
                'maxTime' => round((float)($row['maxTime'] ?? 0), 2),
                'minTime' => round((float)($row['minTime'] ?? 0), 2),
            ];
        }, $results);
    }

    /**
     * Get slow queries (queries taking longer than threshold)
     */
    public function getSlowQueries(int $thresholdMs = 1000, int $limit = 50): array
    {
        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select('al.id, al.endpoint, al.executionTime, al.description, al.createdAt, al.httpMethod')
            ->where('al.action = :action')
            ->andWhere('al.executionTime > :threshold')
            ->setParameter('action', 'query_execute')
            ->setParameter('threshold', $thresholdMs)
            ->orderBy('al.executionTime', 'DESC')
            ->setMaxResults($limit);

        $slowQueries = $qb->getQuery()->getResult();

        return array_map(function ($query) {
            return [
                'id' => $query['id'] ?? null,
                'endpoint' => $query['endpoint'] ?? 'unknown',
                'executionTime' => (int)($query['executionTime'] ?? 0),
                'query' => $query['description'] ?? 'N/A',
                'method' => $query['httpMethod'] ?? 'UNKNOWN',
                'timestamp' => $query['createdAt']?->format('c') ?? null,
            ];
        }, $slowQueries);
    }

    /**
     * Get database connection statistics
     */
    public function getDatabaseStats(): array
    {
        $stats = [];

        try {
            // Get connection info
            $stats['driver'] = $this->connection->getDriver()->getName();
            
            // Get table information
            $tableInfo = $this->getTableStats();
            $stats['tables'] = $tableInfo['count'] ?? 0;
            $stats['totalSize'] = $tableInfo['size'] ?? 0;

            // Get connection status
            $stmt = $this->connection->executeQuery('SELECT 1');
            $stats['status'] = 'connected';
            $stats['lastCheck'] = (new \DateTime())->format('c');

        } catch (\Exception $e) {
            $stats['status'] = 'error';
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Get table size statistics
     */
    private function getTableStats(): array
    {
        try {
            $result = [
                'count' => 0,
                'size' => 0,
            ];

            $driverName = $this->connection->getDriver()->getName();

            if (strpos($driverName, 'pgsql') !== false) {
                // PostgreSQL
                $stmt = $this->connection->executeQuery(
                    "SELECT COUNT(*) as count, COALESCE(SUM(pg_total_relation_size(schemaname||'.'||tablename)), 0) as size 
                    FROM pg_tables 
                    WHERE schemaname NOT IN ('pg_catalog', 'information_schema')"
                );
                $row = $stmt->fetchAssociative();
                $result = [
                    'count' => (int)($row['count'] ?? 0),
                    'size' => (int)($row['size'] ?? 0),
                ];
            } elseif (strpos($driverName, 'mysql') !== false) {
                // MySQL
                $database = $this->connection->getDatabase();
                $stmt = $this->connection->executeQuery(
                    "SELECT COUNT(*) as count, COALESCE(SUM(data_length + index_length), 0) as size 
                    FROM information_schema.tables 
                    WHERE table_schema = ?"
                );
                $row = $stmt->fetchAssociative();
                $result = [
                    'count' => (int)($row['count'] ?? 0),
                    'size' => (int)($row['size'] ?? 0),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return ['count' => 0, 'size' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get execution statistics for time range
     */
    public function getExecutionStats(int $hours = 24, string $interval = 'hour'): array
    {
        $sinceDate = new \DateTime("-$hours hours");

        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select(
                'DATE_TRUNC(:interval, al.createdAt) as period, 
                COUNT(al.id) as total, 
                SUM(CASE WHEN al.status = :success THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN al.status = :failure THEN 1 ELSE 0 END) as failed,
                AVG(al.executionTime) as avgTime'
            )
            ->where('al.createdAt >= :since')
            ->andWhere('al.action IN (:actions)')
            ->setParameter('since', $sinceDate)
            ->setParameter('interval', $interval)
            ->setParameter('actions', ['query_execute', 'connection_test', 'data_browse'])
            ->setParameter('success', 'success')
            ->setParameter('failure', 'failure')
            ->groupBy('DATE_TRUNC(:interval, al.createdAt)')
            ->orderBy('period', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'period' => $row['period']?->format('c') ?? null,
                'total' => (int)($row['total'] ?? 0),
                'successful' => (int)($row['successful'] ?? 0),
                'failed' => (int)($row['failed'] ?? 0),
                'avgTime' => round((float)($row['avgTime'] ?? 0), 2),
            ];
        }, $results);
    }

    /**
     * Get user activity statistics
     */
    public function getUserActivityStats(int $days = 7): array
    {
        $sinceDate = new \DateTime("-$days days");

        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select('IDENTITY(al.user) as userId, COUNT(al.id) as actions, MAX(al.createdAt) as lastAction')
            ->where('al.createdAt >= :since')
            ->setParameter('since', $sinceDate)
            ->groupBy('IDENTITY(al.user)')
            ->orderBy('actions', 'DESC')
            ->setMaxResults(20);

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'userId' => $row['userId'],
                'actions' => (int)($row['actions'] ?? 0),
                'lastAction' => $row['lastAction']?->format('c') ?? null,
            ];
        }, $results);
    }

    /**
     * Get most accessed endpoints
     */
    public function getMostAccessedEndpoints(int $limit = 20): array
    {
        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select('al.endpoint, al.httpMethod, COUNT(al.id) as accessCount, AVG(al.executionTime) as avgTime')
            ->groupBy('al.endpoint, al.httpMethod')
            ->orderBy('accessCount', 'DESC')
            ->setMaxResults($limit);

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'endpoint' => $row['endpoint'] ?? 'unknown',
                'method' => $row['httpMethod'] ?? 'UNKNOWN',
                'accessCount' => (int)($row['accessCount'] ?? 0),
                'avgTime' => round((float)($row['avgTime'] ?? 0), 2),
            ];
        }, $results);
    }

    /**
     * Get error statistics
     */
    public function getErrorStats(int $hours = 24): array
    {
        $sinceDate = new \DateTime("-$hours hours");

        $qb = $this->auditLogRepository->createQueryBuilder('al')
            ->select('al.endpoint, al.status, COUNT(al.id) as count, al.errorMessage')
            ->where('al.createdAt >= :since')
            ->andWhere('al.status = :status')
            ->setParameter('since', $sinceDate)
            ->setParameter('status', 'failure')
            ->groupBy('al.endpoint, al.status, al.errorMessage')
            ->orderBy('count', 'DESC')
            ->setMaxResults(50);

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'endpoint' => $row['endpoint'] ?? 'unknown',
                'count' => (int)($row['count'] ?? 0),
                'error' => $row['errorMessage'] ?? 'Unknown error',
            ];
        }, $results);
    }

    /**
     * Calculate performance score (0-100)
     */
    public function getPerformanceScore(): int
    {
        $score = 100;

        // Get average execution time
        $slowQueryCount = $this->auditLogRepository->count([
            'action' => 'query_execute',
            'executionTime' => 1000, // queries > 1 second
        ]);

        if ($slowQueryCount > 10) {
            $score -= min(20, $slowQueryCount / 2);
        }

        // Get error rate
        $auditCount = $this->auditLogRepository->count(['action' => 'query_execute']);
        if ($auditCount > 0) {
            $errorCount = $this->auditLogRepository->count([
                'action' => 'query_execute',
                'status' => 'failure',
            ]);
            $errorRate = ($errorCount / $auditCount) * 100;
            $score -= min(30, $errorRate);
        }

        return max(0, min(100, $score));
    }
}
