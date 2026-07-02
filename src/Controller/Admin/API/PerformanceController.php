<?php

namespace App\Controller\Admin\API;

use App\Service\PerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API Controller for Performance Monitoring
 */
#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class PerformanceController extends AbstractController
{
    public function __construct(
        private PerformanceService $performanceService,
    ) {}

    /**
     * Get query performance metrics
     * 
     * GET /api/admin/performance/metrics?hours=24
     */
    #[Route('/performance/metrics', name: 'metrics', methods: ['GET'])]
    public function getMetrics(Request $request): JsonResponse
    {
        $hours = (int)$request->query->get('hours', 24);
        $hours = max(1, min(720, $hours)); // 1 hour to 30 days

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getQueryMetrics($hours),
            'metadata' => [
                'hours' => $hours,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get slow queries
     * 
     * GET /api/admin/performance/slow-queries?threshold=1000&limit=50
     */
    #[Route('/performance/slow-queries', name: 'slow_queries', methods: ['GET'])]
    public function getSlowQueries(Request $request): JsonResponse
    {
        $threshold = (int)$request->query->get('threshold', 1000);
        $limit = (int)$request->query->get('limit', 50);

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getSlowQueries($threshold, $limit),
            'metadata' => [
                'threshold' => $threshold,
                'limit' => $limit,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get database statistics
     * 
     * GET /api/admin/performance/database-stats
     */
    #[Route('/performance/database-stats', name: 'database_stats', methods: ['GET'])]
    public function getDatabaseStats(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getDatabaseStats(),
            'metadata' => [
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get execution statistics over time
     * 
     * GET /api/admin/performance/execution-stats?hours=24&interval=hour
     */
    #[Route('/performance/execution-stats', name: 'execution_stats', methods: ['GET'])]
    public function getExecutionStats(Request $request): JsonResponse
    {
        $hours = (int)$request->query->get('hours', 24);
        $interval = $request->query->get('interval', 'hour');

        $allowedIntervals = ['hour', 'day'];
        if (!in_array($interval, $allowedIntervals)) {
            $interval = 'hour';
        }

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getExecutionStats($hours, $interval),
            'metadata' => [
                'hours' => $hours,
                'interval' => $interval,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get user activity statistics
     * 
     * GET /api/admin/performance/user-activity?days=7
     */
    #[Route('/performance/user-activity', name: 'user_activity', methods: ['GET'])]
    public function getUserActivity(Request $request): JsonResponse
    {
        $days = (int)$request->query->get('days', 7);
        $days = max(1, min(90, $days));

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getUserActivityStats($days),
            'metadata' => [
                'days' => $days,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get most accessed endpoints
     * 
     * GET /api/admin/performance/top-endpoints?limit=20
     */
    #[Route('/performance/top-endpoints', name: 'top_endpoints', methods: ['GET'])]
    public function getTopEndpoints(Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 20);
        $limit = max(5, min(100, $limit));

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getMostAccessedEndpoints($limit),
            'metadata' => [
                'limit' => $limit,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get error statistics
     * 
     * GET /api/admin/performance/errors?hours=24
     */
    #[Route('/performance/errors', name: 'error_stats', methods: ['GET'])]
    public function getErrors(Request $request): JsonResponse
    {
        $hours = (int)$request->query->get('hours', 24);
        $hours = max(1, min(720, $hours));

        return $this->json([
            'success' => true,
            'data' => $this->performanceService->getErrorStats($hours),
            'metadata' => [
                'hours' => $hours,
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get overall performance score
     * 
     * GET /api/admin/performance/score
     */
    #[Route('/performance/score', name: 'performance_score', methods: ['GET'])]
    public function getPerformanceScore(): JsonResponse
    {
        $score = $this->performanceService->getPerformanceScore();

        $rating = match(true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'good',
            $score >= 50 => 'fair',
            $score >= 25 => 'poor',
            default => 'critical',
        };

        return $this->json([
            'success' => true,
            'data' => [
                'score' => $score,
                'rating' => $rating,
                'maxScore' => 100,
            ],
            'metadata' => [
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }

    /**
     * Get comprehensive performance dashboard data
     * 
     * GET /api/admin/performance/dashboard
     */
    #[Route('/performance/dashboard', name: 'dashboard', methods: ['GET'])]
    public function getDashboard(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => [
                'performanceScore' => $this->performanceService->getPerformanceScore(),
                'metrics' => array_slice($this->performanceService->getQueryMetrics(24), 0, 10),
                'slowQueries' => $this->performanceService->getSlowQueries(1000, 10),
                'databaseStats' => $this->performanceService->getDatabaseStats(),
                'topEndpoints' => $this->performanceService->getMostAccessedEndpoints(10),
                'errorStats' => $this->performanceService->getErrorStats(24),
                'executionStats' => $this->performanceService->getExecutionStats(24, 'hour'),
            ],
            'metadata' => [
                'timestamp' => (new \DateTime())->format('c'),
            ]
        ]);
    }
}
