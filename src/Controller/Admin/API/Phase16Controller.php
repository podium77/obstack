<?php

namespace App\Controller\Admin\API;

use App\Service\WebSocketServerService;
use App\Service\PresenceService;
use App\Service\CursorTrackingService;
use App\Service\CollaborationIndicatorService;
use App\Service\TypingNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Phase 16: WebSocket Server & Presence Management API Controller
 * 
 * Provides REST API endpoints for WebSocket server management, presence tracking,
 * cursor synchronization, collaboration indicators, and typing notifications.
 */
#[Route('/api/admin/phase16')]
#[IsGranted('ROLE_ADMIN')]
class Phase16Controller extends AbstractController
{
    public function __construct(
        private WebSocketServerService $webSocketServerService,
        private PresenceService $presenceService,
        private CursorTrackingService $cursorTrackingService,
        private CollaborationIndicatorService $collaborationIndicatorService,
        private TypingNotificationService $typingNotificationService
    ) {}

    // ============================================================
    // WebSocket Server Endpoints (8)
    // ============================================================

    #[Route('/websocket/register', name: 'phase16_ws_register', methods: ['POST'])]
    public function registerConnection(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->webSocketServerService->registerConnection(
                $data['user_id'],
                $data['connection_id'],
                $data['workspace_id'] ?? null,
                $data['document_id'] ?? null,
                $data['client_ip'] ?? null
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/unregister', name: 'phase16_ws_unregister', methods: ['POST'])]
    public function unregisterConnection(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->webSocketServerService->unregisterConnection($data['connection_id']);
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/heartbeat', name: 'phase16_ws_heartbeat', methods: ['POST'])]
    public function sendHeartbeat(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->webSocketServerService->sendHeartbeat($data['connection_id']);
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/subscribe-room', name: 'phase16_ws_subscribe', methods: ['POST'])]
    public function subscribeToRoom(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->webSocketServerService->subscribeToRoom(
                $data['connection_id'],
                $data['room_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/unsubscribe-room', name: 'phase16_ws_unsubscribe', methods: ['POST'])]
    public function unsubscribeFromRoom(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->webSocketServerService->unsubscribeFromRoom(
                $data['connection_id'],
                $data['room_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/room-connections/{roomId}', name: 'phase16_ws_room_connections', methods: ['GET'])]
    public function getRoomConnections(string $roomId): JsonResponse {
        try {
            $connections = $this->webSocketServerService->getRoomConnections($roomId);
            return $this->json(['success' => true, 'data' => ['connections' => $connections]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/server-stats', name: 'phase16_ws_stats', methods: ['GET'])]
    public function getServerStats(): JsonResponse {
        try {
            $stats = $this->webSocketServerService->getServerStats();
            return $this->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/websocket/cleanup-stale', name: 'phase16_ws_cleanup', methods: ['POST'])]
    public function cleanupStaleConnections(): JsonResponse {
        try {
            $count = $this->webSocketServerService->cleanupStaleConnections();
            return $this->json(['success' => true, 'data' => ['cleaned_count' => $count]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ============================================================
    // Presence Endpoints (8)
    // ============================================================

    #[Route('/presence/update', name: 'phase16_presence_update', methods: ['POST'])]
    public function updatePresence(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->presenceService->updatePresence(
                $data['user_id'],
                $data['status'],
                $data['workspace_id'] ?? null,
                $data['document_id'] ?? null
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/set-offline', name: 'phase16_presence_offline', methods: ['POST'])]
    public function setOffline(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->presenceService->setOffline($data['user_id']);
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/user/{userId}', name: 'phase16_presence_user', methods: ['GET'])]
    public function getUserPresence(int $userId): JsonResponse {
        try {
            $presence = $this->presenceService->getUserPresence($userId);
            return $this->json(['success' => true, 'data' => $presence]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/workspace/{workspaceId}', name: 'phase16_presence_workspace', methods: ['GET'])]
    public function getWorkspaceOnlineUsers(string $workspaceId): JsonResponse {
        try {
            $users = $this->presenceService->getWorkspaceOnlineUsers($workspaceId);
            return $this->json(['success' => true, 'data' => ['users' => $users]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/document/{documentId}', name: 'phase16_presence_document', methods: ['GET'])]
    public function getDocumentUsers(string $documentId): JsonResponse {
        try {
            $users = $this->presenceService->getDocumentUsers($documentId);
            return $this->json(['success' => true, 'data' => ['users' => $users]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/stats', name: 'phase16_presence_stats', methods: ['GET'])]
    public function getPresenceStats(): JsonResponse {
        try {
            $stats = $this->presenceService->getPresenceStats();
            return $this->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/record-activity', name: 'phase16_presence_activity', methods: ['POST'])]
    public function recordActivity(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->presenceService->recordActivity($data['user_id']);
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/presence/check-online/{userId}', name: 'phase16_presence_check', methods: ['GET'])]
    public function isOnline(int $userId): JsonResponse {
        try {
            $isOnline = $this->presenceService->isOnline($userId);
            return $this->json(['success' => true, 'data' => ['is_online' => $isOnline]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ============================================================
    // Cursor Tracking Endpoints (8)
    // ============================================================

    #[Route('/cursor/update', name: 'phase16_cursor_update', methods: ['POST'])]
    public function updateCursorPosition(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->cursorTrackingService->updateCursorPosition(
                $data['user_id'],
                $data['document_id'],
                $data['line'],
                $data['column'],
                $data['selection_start_line'] ?? null,
                $data['selection_start_column'] ?? null,
                $data['selection_end_line'] ?? null,
                $data['selection_end_column'] ?? null
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/document/{documentId}', name: 'phase16_cursor_document', methods: ['GET'])]
    public function getDocumentCursors(string $documentId): JsonResponse {
        try {
            $cursors = $this->cursorTrackingService->getDocumentCursors($documentId);
            return $this->json(['success' => true, 'data' => ['cursors' => $cursors]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/user/{userId}/{documentId}', name: 'phase16_cursor_user', methods: ['GET'])]
    public function getUserCursor(int $userId, string $documentId): JsonResponse {
        try {
            $cursor = $this->cursorTrackingService->getUserCursor($userId, $documentId);
            return $this->json(['success' => true, 'data' => $cursor]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/clear', name: 'phase16_cursor_clear', methods: ['POST'])]
    public function clearCursor(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->cursorTrackingService->clearCursor(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/stats/{documentId}', name: 'phase16_cursor_stats', methods: ['GET'])]
    public function getCursorStats(string $documentId): JsonResponse {
        try {
            $stats = $this->cursorTrackingService->getDocumentCursorStats($documentId);
            return $this->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/collisions/{documentId}', name: 'phase16_cursor_collisions', methods: ['GET'])]
    public function detectCursorCollisions(string $documentId): JsonResponse {
        try {
            $collisions = $this->cursorTrackingService->detectCursorCollisions($documentId);
            return $this->json(['success' => true, 'data' => $collisions]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/history/{documentId}', name: 'phase16_cursor_history', methods: ['GET'])]
    public function getCursorHistory(string $documentId): JsonResponse {
        try {
            $history = $this->cursorTrackingService->getCursorHistory($documentId);
            return $this->json(['success' => true, 'data' => ['history' => $history]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/cursor/cleanup-stale', name: 'phase16_cursor_cleanup', methods: ['POST'])]
    public function cleanupStaleCursors(): JsonResponse {
        try {
            $count = $this->cursorTrackingService->cleanupStaleCursors();
            return $this->json(['success' => true, 'data' => ['cleaned_count' => $count]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ============================================================
    // Collaboration Indicator Endpoints (9)
    // ============================================================

    #[Route('/collaboration/register-editor', name: 'phase16_collab_editor', methods: ['POST'])]
    public function registerEditor(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->collaborationIndicatorService->registerEditor(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/register-viewer', name: 'phase16_collab_viewer', methods: ['POST'])]
    public function registerViewer(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->collaborationIndicatorService->registerViewer(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/record-edit', name: 'phase16_collab_edit', methods: ['POST'])]
    public function recordEdit(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->collaborationIndicatorService->recordEdit(
                $data['user_id'],
                $data['document_id'],
                $data['change_type'] ?? 'modify'
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/unregister-editor', name: 'phase16_collab_unregister_editor', methods: ['POST'])]
    public function unregisterEditor(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->collaborationIndicatorService->unregisterEditor(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/unregister-viewer', name: 'phase16_collab_unregister_viewer', methods: ['POST'])]
    public function unregisterViewer(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->collaborationIndicatorService->unregisterViewer(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/stats/{documentId}', name: 'phase16_collab_stats', methods: ['GET'])]
    public function getCollaborationStats(string $documentId): JsonResponse {
        try {
            $stats = $this->collaborationIndicatorService->getDocumentCollaborationStats($documentId);
            return $this->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/conflicts/{documentId}/{userId}', name: 'phase16_collab_conflicts', methods: ['GET'])]
    public function detectConflicts(string $documentId, int $userId): JsonResponse {
        try {
            $conflicts = $this->collaborationIndicatorService->detectConflicts($documentId, $userId, 0, 100);
            return $this->json(['success' => true, 'data' => $conflicts]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/history/{documentId}', name: 'phase16_collab_history', methods: ['GET'])]
    public function getEditHistory(string $documentId): JsonResponse {
        try {
            $history = $this->collaborationIndicatorService->getEditHistory($documentId);
            return $this->json(['success' => true, 'data' => ['history' => $history]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/collaboration/summary/{documentId}', name: 'phase16_collab_summary', methods: ['GET'])]
    public function getCollaborationSummary(string $documentId): JsonResponse {
        try {
            $summary = $this->collaborationIndicatorService->getCollaborationSummary($documentId);
            return $this->json(['success' => true, 'data' => $summary]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ============================================================
    // Typing Notification Endpoints (8)
    // ============================================================

    #[Route('/typing/record', name: 'phase16_typing_record', methods: ['POST'])]
    public function recordTyping(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $result = $this->typingNotificationService->recordTyping(
                $data['user_id'],
                $data['document_id'],
                $data['line'] ?? null,
                $data['column'] ?? null,
                $data['characters_added'] ?? 1
            );
            return $this->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/stop', name: 'phase16_typing_stop', methods: ['POST'])]
    public function recordStoppedTyping(Request $request): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $success = $this->typingNotificationService->recordStoppedTyping(
                $data['user_id'],
                $data['document_id']
            );
            return $this->json(['success' => $success]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/document/{documentId}', name: 'phase16_typing_document', methods: ['GET'])]
    public function getTypingUsers(string $documentId): JsonResponse {
        try {
            $users = $this->typingNotificationService->getTypingUsers($documentId);
            return $this->json(['success' => true, 'data' => ['typing_users' => $users]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/count/{documentId}', name: 'phase16_typing_count', methods: ['GET'])]
    public function getTypingCount(string $documentId): JsonResponse {
        try {
            $count = $this->typingNotificationService->getTypingCount($documentId);
            return $this->json(['success' => true, 'data' => ['typing_count' => $count]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/stats/{documentId}', name: 'phase16_typing_stats', methods: ['GET'])]
    public function getTypingStats(string $documentId): JsonResponse {
        try {
            $stats = $this->typingNotificationService->getTypingStats($documentId);
            return $this->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/burst/{documentId}', name: 'phase16_typing_burst', methods: ['GET'])]
    public function detectTypingBurst(string $documentId): JsonResponse {
        try {
            $burst = $this->typingNotificationService->detectTypingBurst($documentId);
            return $this->json(['success' => true, 'data' => $burst]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/cleanup-expired', name: 'phase16_typing_cleanup', methods: ['POST'])]
    public function cleanupExpiredTyping(): JsonResponse {
        try {
            $count = $this->typingNotificationService->cleanupExpiredTyping();
            return $this->json(['success' => true, 'data' => ['cleaned_count' => $count]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/typing/check-user/{userId}/{documentId}', name: 'phase16_typing_check', methods: ['GET'])]
    public function isUserTyping(int $userId, string $documentId): JsonResponse {
        try {
            $isTyping = $this->typingNotificationService->isUserTyping($userId, $documentId);
            return $this->json(['success' => true, 'data' => ['is_typing' => $isTyping]]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}
