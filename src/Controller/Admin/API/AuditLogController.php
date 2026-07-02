<?php

namespace App\Controller\Admin\API;

use App\Repository\AuditLogRepository;
use App\Repository\LocalUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur API pour la consultation du journal d'audit.
 * 
 * Tous les endpoints requièrent le rôle ROLE_ADMIN et la permission admin.view_audit.
 */
#[Route('/api/admin/audit', name: 'api_admin_audit_')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    public function __construct(
        private AuditLogRepository $auditLogRepository,
        private LocalUserRepository $userRepository,
    ) {
    }

    /**
     * Liste les entrées d'audit récentes avec filtrage optionnel.
     * 
     * GET /api/admin/audit/logs
     * Query params:
     *   - action: filter by action (e.g., "database_query_executed")
     *   - userId: filter by user
     *   - resourceType: filter by resource type
     *   - resourceId: filter by resource id
     *   - status: filter by status (success, failure, partial)
     *   - limit: number of results (default 50, max 500)
     *   - offset: pagination offset (default 0)
     */
    #[Route('/logs', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $limit = min((int)$request->query->get('limit', 50), 500);
            $offset = (int)$request->query->get('offset', 0);
            $action = $request->query->get('action');
            $userId = $request->query->get('userId');
            $resourceType = $request->query->get('resourceType');
            $status = $request->query->get('status');

            $qb = $this->auditLogRepository->createQueryBuilder('a')
                ->orderBy('a.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->setFirstResult($offset);

            if ($action) {
                $qb->andWhere('a.action = :action')
                    ->setParameter('action', $action);
            }

            if ($userId) {
                $qb->andWhere('a.user = :userId')
                    ->setParameter('userId', $userId);
            }

            if ($resourceType) {
                $qb->andWhere('a.resourceType = :resourceType')
                    ->setParameter('resourceType', $resourceType);
            }

            if ($status) {
                $qb->andWhere('a.status = :status')
                    ->setParameter('status', $status);
            }

            $logs = $qb->getQuery()->getResult();

            // Formater les données
            $data = array_map(function ($log) {
                return [
                    'id' => $log->getId(),
                    'action' => $log->getAction(),
                    'user' => $log->getUser() ? [
                        'id' => $log->getUser()->getId(),
                        'email' => $log->getUser()->getEmail(),
                    ] : null,
                    'resourceType' => $log->getResourceType(),
                    'resourceId' => $log->getResourceId(),
                    'description' => $log->getDescription(),
                    'status' => $log->getStatus(),
                    'ipAddress' => $log->getIpAddress(),
                    'httpMethod' => $log->getHttpMethod(),
                    'endpoint' => $log->getEndpoint(),
                    'oldValues' => $log->getOldValues(),
                    'newValues' => $log->getNewValues(),
                    'errorMessage' => $log->getErrorMessage(),
                    'createdAt' => $log->getCreatedAt()->format('c'),
                ];
            }, $logs);

            return $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtient l'historique d'activité d'un utilisateur spécifique.
     * 
     * GET /api/admin/audit/user/{userId}
     */
    #[Route('/user/{userId}', name: 'user_activity', methods: ['GET'])]
    public function userActivity(int $userId, Request $request): JsonResponse
    {
        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'error' => 'Utilisateur non trouvé',
                ], 404);
            }

            $limit = min((int)$request->query->get('limit', 50), 500);
            $logs = $this->auditLogRepository->findByUser($user, $limit);

            // Formater les données
            $data = array_map(function ($log) {
                return [
                    'id' => $log->getId(),
                    'action' => $log->getAction(),
                    'resourceType' => $log->getResourceType(),
                    'resourceId' => $log->getResourceId(),
                    'description' => $log->getDescription(),
                    'status' => $log->getStatus(),
                    'ipAddress' => $log->getIpAddress(),
                    'httpMethod' => $log->getHttpMethod(),
                    'endpoint' => $log->getEndpoint(),
                    'createdAt' => $log->getCreatedAt()->format('c'),
                ];
            }, $logs);

            return $this->json([
                'success' => true,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtient les tentatives d'accès refusé récentes.
     * 
     * GET /api/admin/audit/access-denied
     * Query params:
     *   - hours: number of hours to look back (default 24)
     *   - limit: number of results (default 100, max 500)
     */
    #[Route('/access-denied', name: 'access_denied', methods: ['GET'])]
    public function accessDenied(Request $request): JsonResponse
    {
        try {
            $hours = (int)$request->query->get('hours', 24);
            $limit = min((int)$request->query->get('limit', 100), 500);

            $logs = $this->auditLogRepository->findAccessDeniedAttempts($hours, $limit);

            // Formater les données
            $data = array_map(function ($log) {
                return [
                    'id' => $log->getId(),
                    'action' => $log->getAction(),
                    'user' => $log->getUser() ? [
                        'id' => $log->getUser()->getId(),
                        'email' => $log->getUser()->getEmail(),
                    ] : null,
                    'description' => $log->getDescription(),
                    'ipAddress' => $log->getIpAddress(),
                    'endpoint' => $log->getEndpoint(),
                    'errorMessage' => $log->getErrorMessage(),
                    'createdAt' => $log->getCreatedAt()->format('c'),
                ];
            }, $logs);

            return $this->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtient l'historique d'une ressource spécifique.
     * 
     * GET /api/admin/audit/resource/{resourceType}/{resourceId}
     */
    #[Route('/resource/{resourceType}/{resourceId}', name: 'resource_history', methods: ['GET'])]
    public function resourceHistory(string $resourceType, int $resourceId, Request $request): JsonResponse
    {
        try {
            $limit = min((int)$request->query->get('limit', 50), 500);

            $logs = $this->auditLogRepository->findByResourceType(
                $resourceType,
                null,
                $limit,
            );

            // Filtrer par resourceId
            $logs = array_filter($logs, fn ($log) => $log->getResourceId() === $resourceId);

            // Formater les données
            $data = array_map(function ($log) {
                return [
                    'id' => $log->getId(),
                    'action' => $log->getAction(),
                    'user' => $log->getUser() ? [
                        'id' => $log->getUser()->getId(),
                        'email' => $log->getUser()->getEmail(),
                    ] : null,
                    'description' => $log->getDescription(),
                    'status' => $log->getStatus(),
                    'oldValues' => $log->getOldValues(),
                    'newValues' => $log->getNewValues(),
                    'createdAt' => $log->getCreatedAt()->format('c'),
                ];
            }, $logs);

            return $this->json([
                'success' => true,
                'resource' => [
                    'type' => $resourceType,
                    'id' => $resourceId,
                ],
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage(),
            ]);
        }
    }
}
