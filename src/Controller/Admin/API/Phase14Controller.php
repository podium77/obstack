<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Entity\LocalUser;
use App\Service\ActivityFeedService;
use App\Service\CollaborationAuditService;
use App\Service\ReactionService;
use App\Service\RealtimeService;
use App\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/phase14', name: 'api_admin_phase14')]
#[IsGranted('ROLE_ADMIN')]
class Phase14Controller extends AbstractController
{
    public function __construct(
        private RealtimeService $realtimeService,
        private ActivityFeedService $activityFeedService,
        private SearchService $searchService,
        private ReactionService $reactionService,
        private CollaborationAuditService $collaborationAuditService,
    ) {}

    // ============================================================
    // REALTIME ENDPOINTS
    // ============================================================

    /**
     * Register a WebSocket connection
     */
    #[Route('/realtime/connect', name: 'api_realtime_connect', methods: ['POST'])]
    public function registerConnection(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        if (!isset($data['connectionId'])) {
            return $this->json(['success' => false, 'error' => 'Connection ID required'], 400);
        }

        $result = $this->realtimeService->registerConnection(
            $user->getId(),
            $data['connectionId'],
            $request->getClientIp() ?? 'unknown'
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Unregister a WebSocket connection
     */
    #[Route('/realtime/disconnect', name: 'api_realtime_disconnect', methods: ['POST'])]
    public function unregisterConnection(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['connectionId'])) {
            return $this->json(['success' => false, 'error' => 'Connection ID required'], 400);
        }

        $result = $this->realtimeService->unregisterConnection($data['connectionId']);
        return $this->json($result);
    }

    /**
     * Get active users in a workspace
     */
    #[Route('/realtime/workspaces/{id}/active-users', name: 'api_active_users', methods: ['GET'])]
    public function getActiveUsers(string $id): JsonResponse
    {
        $result = $this->realtimeService->getActiveUsers($id);
        return $this->json($result);
    }

    /**
     * Update connection heartbeat
     */
    #[Route('/realtime/heartbeat', name: 'api_heartbeat', methods: ['POST'])]
    public function updateHeartbeat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['connectionId'])) {
            return $this->json(['success' => false, 'error' => 'Connection ID required'], 400);
        }

        $result = $this->realtimeService->updateConnectionHeartbeat($data['connectionId']);
        return $this->json($result);
    }

    /**
     * Subscribe to workspace
     */
    #[Route('/realtime/subscribe', name: 'api_subscribe_workspace', methods: ['POST'])]
    public function subscribeToWorkspace(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['connectionId'], $data['workspaceId'])) {
            return $this->json(['success' => false, 'error' => 'Required fields missing'], 400);
        }

        $result = $this->realtimeService->subscribeToWorkspace(
            $data['connectionId'],
            $data['workspaceId']
        );

        return $this->json($result);
    }

    // ============================================================
    // ACTIVITY FEED ENDPOINTS
    // ============================================================

    /**
     * Get workspace activity feed
     */
    #[Route('/activity/workspaces/{id}', name: 'api_workspace_activity', methods: ['GET'])]
    public function getWorkspaceActivity(string $id, Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 50);
        $offset = (int)$request->query->get('offset', 0);

        $result = $this->activityFeedService->getWorkspaceActivityFeed($id, $limit, $offset);
        return $this->json($result);
    }

    /**
     * Get user's activity feed
     */
    #[Route('/activity/user', name: 'api_user_activity', methods: ['GET'])]
    public function getUserActivity(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $limit = (int)$request->query->get('limit', 50);
        $offset = (int)$request->query->get('offset', 0);

        $result = $this->activityFeedService->getUserActivityFeed($user->getId(), $limit, $offset);
        return $this->json($result);
    }

    /**
     * Get query activity
     */
    #[Route('/activity/queries/{id}', name: 'api_query_activity', methods: ['GET'])]
    public function getQueryActivity(int $id): JsonResponse
    {
        $result = $this->activityFeedService->getQueryActivity($id);
        return $this->json($result);
    }

    /**
     * Get activity statistics
     */
    #[Route('/activity/workspaces/{id}/stats', name: 'api_activity_stats', methods: ['GET'])]
    public function getActivityStats(string $id): JsonResponse
    {
        $result = $this->activityFeedService->getActivityStats($id);
        return $this->json($result);
    }

    // ============================================================
    // SEARCH ENDPOINTS
    // ============================================================

    /**
     * Advanced search for queries
     */
    #[Route('/search/queries', name: 'api_search_queries', methods: ['GET'])]
    public function searchQueries(Request $request): JsonResponse
    {
        $term = $request->query->get('q', '');
        $workspaceId = $request->query->get('workspaceId');
        $sortBy = $request->query->get('sortBy', 'relevance');
        $limit = (int)$request->query->get('limit', 50);
        $offset = (int)$request->query->get('offset', 0);

        if (!$term) {
            return $this->json(['success' => false, 'error' => 'Search term required'], 400);
        }

        /** @var LocalUser $user */
        $user = $this->getUser();

        $result = $this->searchService->searchQueries(
            $term,
            $workspaceId,
            $user instanceof LocalUser ? $user->getId() : null,
            $sortBy,
            $limit,
            $offset
        );

        return $this->json($result);
    }

    /**
     * Filter queries with criteria
     */
    #[Route('/search/queries/filter', name: 'api_filter_queries', methods: ['POST'])]
    public function filterQueries(Request $request): JsonResponse
    {
        $filters = json_decode($request->getContent(), true) ?? [];
        $result = $this->searchService->filterQueries($filters);
        return $this->json($result);
    }

    /**
     * Search comments
     */
    #[Route('/search/comments', name: 'api_search_comments', methods: ['GET'])]
    public function searchComments(Request $request): JsonResponse
    {
        $term = $request->query->get('q', '');

        if (!$term) {
            return $this->json(['success' => false, 'error' => 'Search term required'], 400);
        }

        $result = $this->searchService->searchComments($term);
        return $this->json($result);
    }

    /**
     * Search users
     */
    #[Route('/search/users', name: 'api_search_users', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $term = $request->query->get('q', '');
        $workspaceId = $request->query->get('workspaceId');

        if (!$term) {
            return $this->json(['success' => false, 'error' => 'Search term required'], 400);
        }

        $result = $this->searchService->searchUsers($term, $workspaceId);
        return $this->json($result);
    }

    /**
     * Get search suggestions
     */
    #[Route('/search/suggestions', name: 'api_search_suggestions', methods: ['GET'])]
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $term = $request->query->get('q', '');

        if (!$term) {
            return $this->json(['success' => false, 'error' => 'Search term required'], 400);
        }

        $result = $this->searchService->getSearchSuggestions($term);
        return $this->json($result);
    }

    // ============================================================
    // REACTION ENDPOINTS
    // ============================================================

    /**
     * Add reaction to comment
     */
    #[Route('/reactions/comments', name: 'api_add_reaction', methods: ['POST'])]
    public function addReaction(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['commentId'], $data['reactionType'])) {
            return $this->json(['success' => false, 'error' => 'Required fields missing'], 400);
        }

        $result = $this->reactionService->addReaction(
            $data['commentId'],
            $user->getId(),
            $data['reactionType']
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Remove reaction from comment
     */
    #[Route('/reactions/comments', name: 'api_remove_reaction', methods: ['DELETE'])]
    public function removeReaction(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['commentId'], $data['reactionType'])) {
            return $this->json(['success' => false, 'error' => 'Required fields missing'], 400);
        }

        $result = $this->reactionService->removeReaction(
            $data['commentId'],
            $user->getId(),
            $data['reactionType']
        );

        return $this->json($result);
    }

    /**
     * Get comment reactions
     */
    #[Route('/reactions/comments/{id}', name: 'api_comment_reactions', methods: ['GET'])]
    public function getCommentReactions(string $id): JsonResponse
    {
        $result = $this->reactionService->getCommentReactions($id);
        return $this->json($result);
    }

    /**
     * Vote on comment
     */
    #[Route('/reactions/votes', name: 'api_vote_comment', methods: ['POST'])]
    public function voteOnComment(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['commentId'], $data['voteType'])) {
            return $this->json(['success' => false, 'error' => 'Required fields missing'], 400);
        }

        $result = $this->reactionService->voteOnComment(
            $data['commentId'],
            $user->getId(),
            $data['voteType']
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get comment votes
     */
    #[Route('/reactions/votes/{id}', name: 'api_comment_votes', methods: ['GET'])]
    public function getCommentVotes(string $id): JsonResponse
    {
        $result = $this->reactionService->getCommentVotes($id);
        return $this->json($result);
    }

    /**
     * Get most reacted comments
     */
    #[Route('/reactions/queries/{id}/most-reacted', name: 'api_most_reacted', methods: ['GET'])]
    public function getMostReactedComments(int $id): JsonResponse
    {
        $result = $this->reactionService->getMostReactedComments($id);
        return $this->json($result);
    }

    /**
     * Get most voted comments
     */
    #[Route('/reactions/queries/{id}/most-voted', name: 'api_most_voted', methods: ['GET'])]
    public function getMostVotedComments(int $id): JsonResponse
    {
        $result = $this->reactionService->getMostVotedComments($id);
        return $this->json($result);
    }

    // ============================================================
    // COLLABORATION AUDIT ENDPOINTS
    // ============================================================

    /**
     * Get workspace audit logs
     */
    #[Route('/audit/workspaces/{id}', name: 'api_workspace_audit', methods: ['GET'])]
    public function getWorkspaceAuditLogs(string $id, Request $request): JsonResponse
    {
        $limit = (int)$request->query->get('limit', 100);
        $offset = (int)$request->query->get('offset', 0);

        $result = $this->collaborationAuditService->getWorkspaceAuditLogs($id, $limit, $offset);
        return $this->json($result);
    }

    /**
     * Get user audit trail
     */
    #[Route('/audit/user', name: 'api_user_audit', methods: ['GET'])]
    public function getUserAuditTrail(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $result = $this->collaborationAuditService->getUserAuditTrail($user->getId());
        return $this->json($result);
    }

    /**
     * Get audit logs by action
     */
    #[Route('/audit/workspaces/{id}/actions/{action}', name: 'api_audit_by_action', methods: ['GET'])]
    public function getAuditByAction(string $id, string $action): JsonResponse
    {
        $result = $this->collaborationAuditService->getAuditLogsByAction($id, $action);
        return $this->json($result);
    }

    /**
     * Get audit statistics
     */
    #[Route('/audit/workspaces/{id}/stats', name: 'api_audit_stats', methods: ['GET'])]
    public function getAuditStats(string $id): JsonResponse
    {
        $result = $this->collaborationAuditService->getAuditStats($id);
        return $this->json($result);
    }

    /**
     * Export audit logs
     */
    #[Route('/audit/workspaces/{id}/export', name: 'api_audit_export', methods: ['GET'])]
    public function exportAuditLogs(string $id, Request $request): JsonResponse
    {
        $format = $request->query->get('format', 'csv');
        $result = $this->collaborationAuditService->exportAuditLogs($id, $format);
        return $this->json($result);
    }

    /**
     * Generate audit report
     */
    #[Route('/audit/workspaces/{id}/report', name: 'api_audit_report', methods: ['GET'])]
    public function generateAuditReport(string $id, Request $request): JsonResponse
    {
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        if (!$startDate || !$endDate) {
            return $this->json(['success' => false, 'error' => 'Date range required'], 400);
        }

        $result = $this->collaborationAuditService->generateAuditReport($id, $startDate, $endDate);
        return $this->json($result);
    }
}
