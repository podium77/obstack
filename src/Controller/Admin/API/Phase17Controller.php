<?php

namespace App\Controller\Admin\API;

use App\Service\RealtimeMessagingService;
use App\Service\ConflictResolutionService;
use App\Service\MessageQueueService;
use App\Service\WebSocketGatewayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Phase 17: Real-time Messaging & WebSocket Server API
 * 
 * REST API for managing real-time messaging, conflict resolution,
 * message queues, and WebSocket gateway operations.
 */
#[Route('/api/admin/phase17', name: 'phase17_')]
#[IsGranted('ROLE_ADMIN')]
class Phase17Controller extends AbstractController
{
    public function __construct(
        private RealtimeMessagingService $messagingService,
        private ConflictResolutionService $conflictService,
        private MessageQueueService $queueService,
        private WebSocketGatewayService $gatewayService
    ) {}

    // ==================== Real-time Messaging Endpoints ====================

    #[Route('/messaging/send', name: 'messaging_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->messagingService->sendMessage(
                (int) $data['from_user_id'],
                (int) $data['to_user_id'],
                $data['message_type'],
                $data['payload'] ?? [],
                $data['workspace_id'] ?? null,
                $data['document_id'] ?? null
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/broadcast', name: 'messaging_broadcast', methods: ['POST'])]
    public function broadcastMessage(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->messagingService->broadcastMessage(
                (int) $data['from_user_id'],
                $data['to_user_ids'],
                $data['message_type'],
                $data['payload'] ?? [],
                $data['workspace_id'] ?? null
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/pending/{userId}', name: 'messaging_pending', methods: ['GET'])]
    public function getPendingMessages($userId): JsonResponse {
        try {
            $result = $this->messagingService->getPendingMessages((int) $userId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/history/{userId1}/{userId2}', name: 'messaging_history', methods: ['GET'])]
    public function getMessageHistory($userId1, $userId2): JsonResponse {
        try {
            $result = $this->messagingService->getMessageHistory(
                (int) $userId1,
                (int) $userId2
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/mark-delivered/{messageId}', name: 'messaging_mark_delivered', methods: ['POST'])]
    public function markDelivered($messageId): JsonResponse {
        try {
            $result = $this->messagingService->markDelivered($messageId);
            return $this->json(['success' => $result, 'data' => ['marked' => $result]]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/mark-acknowledged/{messageId}', name: 'messaging_mark_acknowledged', methods: ['POST'])]
    public function markAcknowledged($messageId): JsonResponse {
        try {
            $result = $this->messagingService->markAcknowledged($messageId);
            return $this->json(['success' => $result, 'data' => ['marked' => $result]]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/stats', name: 'messaging_stats', methods: ['GET'])]
    public function getMessagingStats(): JsonResponse {
        try {
            $result = $this->messagingService->getDeliveryStats();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/partners/{userId}', name: 'messaging_partners', methods: ['GET'])]
    public function getConversationPartners($userId): JsonResponse {
        try {
            $result = $this->messagingService->getConversationPartners((int) $userId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/messaging/workspace/{workspaceId}', name: 'messaging_workspace', methods: ['GET'])]
    public function getWorkspaceMessages($workspaceId): JsonResponse {
        try {
            $result = $this->messagingService->getWorkspaceMessages($workspaceId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== Conflict Resolution Endpoints ====================

    #[Route('/conflicts/record', name: 'conflicts_record', methods: ['POST'])]
    public function recordOperation(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->conflictService->recordOperation(
                (int) $data['user_id'],
                $data['document_id'],
                $data['operation_type'],
                (int) $data['position'],
                $data['content'] ?? null,
                (int) ($data['length'] ?? 0)
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/detect', name: 'conflicts_detect', methods: ['POST'])]
    public function detectConflicts(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->conflictService->detectConflicts(
                $data['document_id'],
                $data['operations']
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/transform', name: 'conflicts_transform', methods: ['POST'])]
    public function transformOperation(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->conflictService->transformOperation(
                $data['operation1'],
                $data['operation2']
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/resolve/{conflictId}', name: 'conflicts_resolve', methods: ['POST'])]
    public function resolveConflict($conflictId, Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->conflictService->resolveConflict(
                $conflictId,
                $data['strategy'] ?? 'last_write_wins'
            );

            return $this->json(['success' => $result, 'data' => ['resolved' => $result]]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/history/{documentId}', name: 'conflicts_history', methods: ['GET'])]
    public function getConflictHistory($documentId): JsonResponse {
        try {
            $result = $this->conflictService->getConflictHistory($documentId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/stats/{documentId}', name: 'conflicts_stats', methods: ['GET'])]
    public function getConflictStats($documentId): JsonResponse {
        try {
            $result = $this->conflictService->getConflictStats($documentId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/conflicts/operations/{documentId}', name: 'conflicts_operations', methods: ['GET'])]
    public function getOperationHistory($documentId): JsonResponse {
        try {
            $result = $this->conflictService->getOperationHistory($documentId);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== Message Queue Endpoints ====================

    #[Route('/queue/enqueue', name: 'queue_enqueue', methods: ['POST'])]
    public function enqueueMessage(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->queueService->enqueue(
                $data['message_id'],
                $data['event_type'],
                $data['payload'] ?? [],
                (int) ($data['priority'] ?? 5)
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/dequeue', name: 'queue_dequeue', methods: ['POST'])]
    public function dequeueMessage(): JsonResponse {
        try {
            $result = $this->queueService->dequeue();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/process', name: 'queue_process', methods: ['POST'])]
    public function processQueue(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $batchSize = (int) ($data['batch_size'] ?? 50);
            
            $result = $this->queueService->processQueue($batchSize);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/stats', name: 'queue_stats', methods: ['GET'])]
    public function getQueueStats(): JsonResponse {
        try {
            $result = $this->queueService->getQueueStats();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/status/{status}', name: 'queue_status', methods: ['GET'])]
    public function getQueueByStatus($status): JsonResponse {
        try {
            $result = $this->queueService->getQueueByStatus($status);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/failed', name: 'queue_failed', methods: ['GET'])]
    public function getFailedMessages(): JsonResponse {
        try {
            $result = $this->queueService->getFailedMessages();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/requeue/{queueId}', name: 'queue_requeue', methods: ['POST'])]
    public function requeueMessage($queueId): JsonResponse {
        try {
            $result = $this->queueService->requeue($queueId);
            return $this->json(['success' => $result, 'data' => ['requeued' => $result]]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/queue/events-stats', name: 'queue_events_stats', methods: ['GET'])]
    public function getEventTypeStats(): JsonResponse {
        try {
            $result = $this->queueService->getEventTypeStats();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    // ==================== WebSocket Gateway Endpoints ====================

    #[Route('/gateway/register-listener', name: 'gateway_register_listener', methods: ['POST'])]
    public function registerEventListener(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->gatewayService->registerEventListener(
                $data['event_type'],
                $data['handler_class'],
                $data['handler_method']
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/emit-event', name: 'gateway_emit_event', methods: ['POST'])]
    public function emitEvent(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->gatewayService->emitEvent(
                $data['event_type'],
                $data['data'] ?? [],
                (int) ($data['user_id'] ?? 0) ?: null,
                $data['room_id'] ?? null,
                $data['broadcast_scope'] ?? 'room'
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/register-namespace', name: 'gateway_register_namespace', methods: ['POST'])]
    public function registerNamespace(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->gatewayService->registerNamespace(
                $data['namespace'],
                $data['pattern'] ?? null
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/create-room', name: 'gateway_create_room', methods: ['POST'])]
    public function createRoom(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            $result = $this->gatewayService->createRoom(
                $data['room_id'],
                $data['room_type'],
                (int) ($data['max_capacity'] ?? 0) ?: null,
                $data['metadata'] ?? []
            );

            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/stats', name: 'gateway_stats', methods: ['GET'])]
    public function getGatewayStats(): JsonResponse {
        try {
            $result = $this->gatewayService->getGatewayStats();
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/events', name: 'gateway_events', methods: ['GET'])]
    public function getRecentEvents(Request $request): JsonResponse {
        try {
            $eventType = $request->query->get('event_type');
            $limit = (int) ($request->query->get('limit') ?? 100);
            
            $result = $this->gatewayService->getRecentEvents($limit, $eventType);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/metrics/{eventType}', name: 'gateway_metrics', methods: ['GET'])]
    public function getEventMetrics($eventType): JsonResponse {
        try {
            $result = $this->gatewayService->getEventMetrics($eventType);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/gateway/throughput', name: 'gateway_throughput', methods: ['GET'])]
    public function getConnectionThroughput(Request $request): JsonResponse {
        try {
            $intervalMinutes = (int) ($request->query->get('interval') ?? 5);
            $result = $this->gatewayService->getConnectionThroughput($intervalMinutes);
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
