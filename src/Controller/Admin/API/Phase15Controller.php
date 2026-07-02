<?php

namespace App\Controller\Admin\API;

use App\Service\WebSocketService;
use App\Service\PushNotificationService;
use App\Service\CommentNotificationService;
use App\Service\ActivityDigestService;
use App\Service\NotificationSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/phase15')]
#[IsGranted('ROLE_ADMIN')]
class Phase15Controller extends AbstractController
{
    public function __construct(
        private WebSocketService $webSocketService,
        private PushNotificationService $pushNotificationService,
        private CommentNotificationService $commentNotificationService,
        private ActivityDigestService $activityDigestService,
        private NotificationSettingsService $notificationSettingsService,
    ) {}

    // ============================================================
    // WebSocket Endpoints
    // ============================================================

    #[Route('/websocket/broadcast-workspace', methods: ['POST'])]
    public function broadcastToWorkspace(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $workspaceId = $data['workspace_id'] ?? null;
        $eventType = $data['event_type'] ?? null;

        if (!$workspaceId || !$eventType) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->webSocketService->broadcastToWorkspace($workspaceId, $eventType, $data['data'] ?? []);
        return $this->json($result);
    }

    #[Route('/websocket/broadcast-user', methods: ['POST'])]
    public function broadcastToUser(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $eventType = $data['event_type'] ?? null;

        if (!$userId || !$eventType) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->webSocketService->broadcastToUser($userId, $eventType, $data['data'] ?? []);
        return $this->json($result);
    }

    #[Route('/websocket/channel-subscribers/{workspaceId}', methods: ['GET'])]
    public function getChannelSubscribers(string $workspaceId): JsonResponse
    {
        $result = $this->webSocketService->getChannelSubscriberCount($workspaceId);
        return $this->json($result);
    }

    #[Route('/websocket/store-message', methods: ['POST'])]
    public function storeMessage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $eventType = $data['event_type'] ?? null;

        if (!$userId || !$eventType) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->webSocketService->storeMessage($userId, $eventType, $data['data'] ?? []);
        return $this->json($result);
    }

    #[Route('/websocket/pending-messages', methods: ['GET'])]
    public function getPendingMessages(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');
        $limit = $request->query->getInt('limit', 50);

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->webSocketService->getPendingMessages($userId, $limit);
        return $this->json($result);
    }

    #[Route('/websocket/mark-read/{messageId}', methods: ['PUT'])]
    public function markMessageRead(string $messageId): JsonResponse
    {
        $result = $this->webSocketService->markMessageAsRead($messageId);
        return $this->json($result);
    }

    #[Route('/websocket/connection-stats', methods: ['GET'])]
    public function getConnectionStats(): JsonResponse
    {
        $result = $this->webSocketService->getConnectionStats();
        return $this->json($result);
    }

    // ============================================================
    // Push Notification Endpoints
    // ============================================================

    #[Route('/notifications/send', methods: ['POST'])]
    public function sendNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $title = $data['title'] ?? null;
        $message = $data['message'] ?? null;

        if (!$userId || !$title || !$message) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->pushNotificationService->sendNotification(
            $userId,
            $title,
            $message,
            $data['action_url'] ?? null,
            $data['metadata'] ?? null
        );
        return $this->json($result);
    }

    #[Route('/notifications/send-bulk', methods: ['POST'])]
    public function sendBulkNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userIds = $data['user_ids'] ?? [];
        $title = $data['title'] ?? null;
        $message = $data['message'] ?? null;

        if (empty($userIds) || !$title || !$message) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->pushNotificationService->sendBulkNotification(
            $userIds,
            $title,
            $message,
            $data['action_url'] ?? null
        );
        return $this->json($result);
    }

    #[Route('/notifications/send-workspace', methods: ['POST'])]
    public function sendWorkspaceNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $workspaceId = $data['workspace_id'] ?? null;
        $title = $data['title'] ?? null;
        $message = $data['message'] ?? null;

        if (!$workspaceId || !$title || !$message) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->pushNotificationService->sendWorkspaceNotification(
            $workspaceId,
            $title,
            $message,
            $data['exclude_user_id'] ?? null
        );
        return $this->json($result);
    }

    #[Route('/notifications/user', methods: ['GET'])]
    public function getUserNotifications(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');
        $limit = $request->query->getInt('limit', 50);
        $offset = $request->query->getInt('offset', 0);

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->pushNotificationService->getUserNotifications($userId, $limit, $offset);
        return $this->json($result);
    }

    #[Route('/notifications/unread-count', methods: ['GET'])]
    public function getUnreadCount(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->pushNotificationService->getUnreadCount($userId);
        return $this->json($result);
    }

    #[Route('/notifications/{notificationId}/read', methods: ['PUT'])]
    public function markNotificationRead(string $notificationId): JsonResponse
    {
        $result = $this->pushNotificationService->markAsRead($notificationId);
        return $this->json($result);
    }

    #[Route('/notifications/mark-all-read', methods: ['PUT'])]
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->pushNotificationService->markAllAsRead($userId);
        return $this->json($result);
    }

    #[Route('/notifications/{notificationId}', methods: ['DELETE'])]
    public function deleteNotification(string $notificationId): JsonResponse
    {
        $result = $this->pushNotificationService->deleteNotification($notificationId);
        return $this->json($result);
    }

    #[Route('/notifications/stats', methods: ['GET'])]
    public function getNotificationStats(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->pushNotificationService->getNotificationStats($userId);
        return $this->json($result);
    }

    // ============================================================
    // Comment Notification Endpoints
    // ============================================================

    #[Route('/comment-notifications/mentions', methods: ['POST'])]
    public function notifyMentions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $commentId = $data['comment_id'] ?? null;
        $authorId = $data['author_id'] ?? null;
        $mentionedUserIds = $data['mentioned_user_ids'] ?? [];

        if (!$commentId || !$authorId) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->commentNotificationService->notifyMentions($commentId, $authorId, $mentionedUserIds);
        return $this->json($result);
    }

    #[Route('/comment-notifications/reply', methods: ['POST'])]
    public function notifyReply(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $parentCommentId = $data['parent_comment_id'] ?? null;
        $replyerId = $data['replier_id'] ?? null;
        $replyContent = $data['reply_content'] ?? null;

        if (!$parentCommentId || !$replyerId || !$replyContent) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->commentNotificationService->notifyReply($parentCommentId, $replyerId, $replyContent);
        return $this->json($result);
    }

    #[Route('/comment-notifications/reaction', methods: ['POST'])]
    public function notifyReaction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $commentId = $data['comment_id'] ?? null;
        $reactorId = $data['reactor_id'] ?? null;
        $reactionType = $data['reaction_type'] ?? null;

        if (!$commentId || !$reactorId || !$reactionType) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->commentNotificationService->notifyReaction($commentId, $reactorId, $reactionType);
        return $this->json($result);
    }

    #[Route('/comment-notifications/vote', methods: ['POST'])]
    public function notifyVote(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $commentId = $data['comment_id'] ?? null;
        $voterId = $data['voter_id'] ?? null;
        $voteType = $data['vote_type'] ?? null;

        if (!$commentId || !$voterId || !$voteType) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->commentNotificationService->notifyVote($commentId, $voterId, $voteType);
        return $this->json($result);
    }

    // ============================================================
    // Activity Digest Endpoints
    // ============================================================

    #[Route('/digests/generate', methods: ['POST'])]
    public function generateDigest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $frequency = $data['frequency'] ?? 'daily';

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->activityDigestService->generateDigest($userId, $frequency);
        return $this->json($result);
    }

    #[Route('/digests/send', methods: ['POST'])]
    public function sendDigest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $frequency = $data['frequency'] ?? 'daily';

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->activityDigestService->sendDigest($userId, $frequency);
        return $this->json($result);
    }

    #[Route('/digests/send-scheduled/{frequency}', methods: ['POST'])]
    public function sendScheduledDigests(string $frequency): JsonResponse
    {
        $result = $this->activityDigestService->sendScheduledDigests($frequency);
        return $this->json($result);
    }

    #[Route('/digests/history', methods: ['GET'])]
    public function getDigestHistory(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');
        $limit = $request->query->getInt('limit', 20);

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->activityDigestService->getDigestHistory($userId, $limit);
        return $this->json($result);
    }

    #[Route('/digests/stats/{frequency}', methods: ['GET'])]
    public function getDigestStats(string $frequency): JsonResponse
    {
        $result = $this->activityDigestService->getDigestStats($frequency);
        return $this->json($result);
    }

    // ============================================================
    // Notification Settings Endpoints
    // ============================================================

    #[Route('/settings/user', methods: ['GET'])]
    public function getUserSettings(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->notificationSettingsService->getUserSettings($userId);
        return $this->json($result);
    }

    #[Route('/settings/user', methods: ['PUT'])]
    public function updateUserSettings(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        unset($data['user_id']);
        $result = $this->notificationSettingsService->updateSettings($userId, $data);
        return $this->json($result);
    }

    #[Route('/settings/toggle/{type}', methods: ['PUT'])]
    public function toggleNotificationType(Request $request, string $type): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $enabled = $data['enabled'] ?? true;

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->notificationSettingsService->toggleNotificationType($userId, $type, $enabled);
        return $this->json($result);
    }

    #[Route('/settings/digest-frequency', methods: ['PUT'])]
    public function setDigestFrequency(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $frequency = $data['frequency'] ?? null;

        if (!$userId || !$frequency) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->notificationSettingsService->setDigestFrequency($userId, $frequency);
        return $this->json($result);
    }

    #[Route('/settings/quiet-hours', methods: ['PUT'])]
    public function setQuietHours(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        $enabled = $data['enabled'] ?? true;

        if (!$userId || !$startTime || !$endTime) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->notificationSettingsService->setQuietHours($userId, $startTime, $endTime, $enabled);
        return $this->json($result);
    }

    #[Route('/settings/quiet-hours-check', methods: ['GET'])]
    public function isInQuietHours(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->notificationSettingsService->isInQuietHours($userId);
        return $this->json($result);
    }

    #[Route('/settings/workspace', methods: ['GET'])]
    public function getWorkspaceSettings(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');
        $workspaceId = $request->query->get('workspace_id');

        if (!$userId || !$workspaceId) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->notificationSettingsService->getWorkspacePreferences($userId, $workspaceId);
        return $this->json($result);
    }

    #[Route('/settings/workspace', methods: ['PUT'])]
    public function updateWorkspaceSettings(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;

        if (!$userId || !$workspaceId) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        unset($data['user_id'], $data['workspace_id']);
        $result = $this->notificationSettingsService->updateWorkspacePreferences($userId, $workspaceId, $data);
        return $this->json($result);
    }

    #[Route('/settings/mute-workspace', methods: ['PUT'])]
    public function muteWorkspace(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;

        if (!$userId || !$workspaceId) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->notificationSettingsService->muteWorkspace($userId, $workspaceId);
        return $this->json($result);
    }

    #[Route('/settings/unmute-workspace', methods: ['PUT'])]
    public function unmuteWorkspace(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $workspaceId = $data['workspace_id'] ?? null;

        if (!$userId || !$workspaceId) {
            return $this->json(['success' => false, 'error' => 'Missing required parameters'], 400);
        }

        $result = $this->notificationSettingsService->unmuteWorkspace($userId, $workspaceId);
        return $this->json($result);
    }

    #[Route('/settings/muted-workspaces', methods: ['GET'])]
    public function getMutedWorkspaces(Request $request): JsonResponse
    {
        $userId = $request->query->getInt('user_id');

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->notificationSettingsService->getMutedWorkspaces($userId);
        return $this->json($result);
    }

    #[Route('/settings/reset', methods: ['PUT'])]
    public function resetSettings(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return $this->json(['success' => false, 'error' => 'user_id required'], 400);
        }

        $result = $this->notificationSettingsService->resetToDefaults($userId);
        return $this->json($result);
    }
}
