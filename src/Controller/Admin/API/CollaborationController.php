<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Entity\LocalUser;
use App\Service\AccessControlService;
use App\Service\CollaborationService;
use App\Service\CommentService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/collaboration', name: 'api_admin_collaboration')]
#[IsGranted('ROLE_ADMIN')]
class CollaborationController extends AbstractController
{
    public function __construct(
        private CollaborationService $collaborationService,
        private WorkspaceService $workspaceService,
        private AccessControlService $accessControlService,
        private CommentService $commentService,
    ) {}

    // ============================================================
    // QUERY SHARING ENDPOINTS
    // ============================================================

    /**
     * Share a query with users/groups
     */
    #[Route('/queries/share', name: 'api_share_query', methods: ['POST'])]
    public function shareQuery(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['queryId'], $data['shareWith'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400);
        }

        $result = $this->collaborationService->shareQuery(
            (int)$data['queryId'],
            $data['shareWith'],
            $data['accessLevel'] ?? 'view'
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get shared queries for current user
     */
    #[Route('/queries/shared', name: 'api_shared_queries', methods: ['GET'])]
    public function getSharedQueries(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $result = $this->collaborationService->getSharedQueries($user);
        return $this->json($result);
    }

    /**
     * Get query sharing details
     */
    #[Route('/queries/{id}/shares', name: 'api_query_shares', methods: ['GET'])]
    public function getQueryShares(int $id): JsonResponse
    {
        $result = $this->collaborationService->getQueryShares($id);
        return $this->json($result);
    }

    /**
     * Update share permission
     */
    #[Route('/queries/share', name: 'api_update_share', methods: ['PUT'])]
    public function updateSharePermission(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['queryId'], $data['userId'], $data['accessLevel'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400);
        }

        $result = $this->collaborationService->updateSharePermission(
            (int)$data['queryId'],
            (int)$data['userId'],
            $data['accessLevel']
        );

        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Revoke query share
     */
    #[Route('/queries/share', name: 'api_revoke_share', methods: ['DELETE'])]
    public function revokeShare(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['queryId'])) {
            return $this->json([
                'success' => false,
                'error' => 'Query ID required',
            ], 400);
        }

        $result = $this->collaborationService->revokeShare(
            (int)$data['queryId'],
            isset($data['userId']) ? (int)$data['userId'] : null,
            isset($data['groupId']) ? (int)$data['groupId'] : null
        );

        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    // ============================================================
    // WORKSPACE ENDPOINTS
    // ============================================================

    /**
     * Create workspace
     */
    #[Route('/workspaces', name: 'api_create_workspace', methods: ['POST'])]
    public function createWorkspace(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->workspaceService->createWorkspace($data, $user);

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get user's workspaces
     */
    #[Route('/workspaces', name: 'api_user_workspaces', methods: ['GET'])]
    public function getUserWorkspaces(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $result = $this->workspaceService->getUserWorkspaces($user);
        return $this->json($result);
    }

    /**
     * Get workspace members
     */
    #[Route('/workspaces/{id}/members', name: 'api_workspace_members', methods: ['GET'])]
    public function getWorkspaceMembers(string $id): JsonResponse
    {
        $result = $this->workspaceService->getWorkspaceMembers($id);
        return $this->json($result);
    }

    /**
     * Add member to workspace
     */
    #[Route('/workspaces/{id}/members', name: 'api_add_workspace_member', methods: ['POST'])]
    public function addWorkspaceMember(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['userId'])) {
            return $this->json([
                'success' => false,
                'error' => 'User ID required',
            ], 400);
        }

        $result = $this->workspaceService->addWorkspaceMember(
            $id,
            (int)$data['userId'],
            $data['role'] ?? 'member'
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Remove workspace member
     */
    #[Route('/workspaces/{id}/members/{userId}', name: 'api_remove_workspace_member', methods: ['DELETE'])]
    public function removeWorkspaceMember(string $id, int $userId): JsonResponse
    {
        $result = $this->workspaceService->removeWorkspaceMember($id, $userId);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get workspace queries
     */
    #[Route('/workspaces/{id}/queries', name: 'api_workspace_queries', methods: ['GET'])]
    public function getWorkspaceQueries(string $id): JsonResponse
    {
        $result = $this->workspaceService->getWorkspaceQueries($id);
        return $this->json($result);
    }

    /**
     * Get workspace statistics
     */
    #[Route('/workspaces/{id}/stats', name: 'api_workspace_stats', methods: ['GET'])]
    public function getWorkspaceStats(string $id): JsonResponse
    {
        $result = $this->workspaceService->getWorkspaceStats($id);
        return $this->json($result);
    }

    // ============================================================
    // ACCESS CONTROL ENDPOINTS
    // ============================================================

    /**
     * Create access control group
     */
    #[Route('/groups', name: 'api_create_group', methods: ['POST'])]
    public function createGroup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->accessControlService->createGroup($data);

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * List access control groups
     */
    #[Route('/groups', name: 'api_list_groups', methods: ['GET'])]
    public function listGroups(): JsonResponse
    {
        $result = $this->accessControlService->listGroups();
        return $this->json($result);
    }

    /**
     * Get group details
     */
    #[Route('/groups/{id}', name: 'api_group_details', methods: ['GET'])]
    public function getGroupDetails(string $id): JsonResponse
    {
        $result = $this->accessControlService->getGroupDetails($id);
        $statusCode = $result['success'] ? 200 : 404;
        return $this->json($result, $statusCode);
    }

    /**
     * Add group member
     */
    #[Route('/groups/{id}/members', name: 'api_add_group_member', methods: ['POST'])]
    public function addGroupMember(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['userId'])) {
            return $this->json([
                'success' => false,
                'error' => 'User ID required',
            ], 400);
        }

        $result = $this->accessControlService->addGroupMember($id, (int)$data['userId']);
        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Remove group member
     */
    #[Route('/groups/{id}/members/{userId}', name: 'api_remove_group_member', methods: ['DELETE'])]
    public function removeGroupMember(string $id, int $userId): JsonResponse
    {
        $result = $this->accessControlService->removeGroupMember($id, $userId);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Update group permissions
     */
    #[Route('/groups/{id}/permissions', name: 'api_update_group_permissions', methods: ['PUT'])]
    public function updateGroupPermissions(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['permissions'])) {
            return $this->json([
                'success' => false,
                'error' => 'Permissions required',
            ], 400);
        }

        $result = $this->accessControlService->updateGroupPermissions($id, $data['permissions']);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get user's groups
     */
    #[Route('/user/groups', name: 'api_user_groups', methods: ['GET'])]
    public function getUserGroups(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $result = $this->accessControlService->getUserGroups($user->getId());
        return $this->json($result);
    }

    /**
     * Delete group
     */
    #[Route('/groups/{id}', name: 'api_delete_group', methods: ['DELETE'])]
    public function deleteGroup(string $id): JsonResponse
    {
        $result = $this->accessControlService->deleteGroup($id);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    // ============================================================
    // COMMENTS ENDPOINTS
    // ============================================================

    /**
     * Add comment to query
     */
    #[Route('/queries/{id}/comments', name: 'api_add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['content'])) {
            return $this->json([
                'success' => false,
                'error' => 'Content required',
            ], 400);
        }

        $result = $this->commentService->addComment(
            $id,
            $user,
            $data['content'],
            isset($data['parentCommentId']) ? (int)$data['parentCommentId'] : null
        );

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get query comments
     */
    #[Route('/queries/{id}/comments', name: 'api_get_comments', methods: ['GET'])]
    public function getComments(int $id): JsonResponse
    {
        $result = $this->commentService->getComments($id);
        return $this->json($result);
    }

    /**
     * Update comment
     */
    #[Route('/comments/{id}', name: 'api_update_comment', methods: ['PUT'])]
    public function updateComment(string $id, Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['content'])) {
            return $this->json([
                'success' => false,
                'error' => 'Content required',
            ], 400);
        }

        $result = $this->commentService->updateComment($id, $data['content'], $user);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Delete comment
     */
    #[Route('/comments/{id}', name: 'api_delete_comment', methods: ['DELETE'])]
    public function deleteComment(string $id): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $result = $this->commentService->deleteComment($id, $user);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get comment statistics
     */
    #[Route('/queries/{id}/comments/stats', name: 'api_comment_stats', methods: ['GET'])]
    public function getCommentStats(int $id): JsonResponse
    {
        $result = $this->commentService->getCommentStats($id);
        return $this->json($result);
    }

    /**
     * Add annotation to query
     */
    #[Route('/queries/{id}/annotations', name: 'api_add_annotation', methods: ['POST'])]
    public function addAnnotation(int $id, Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();

        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->commentService->addAnnotation($id, $user, $data);

        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get query annotations
     */
    #[Route('/queries/{id}/annotations', name: 'api_get_annotations', methods: ['GET'])]
    public function getAnnotations(int $id): JsonResponse
    {
        $result = $this->commentService->getAnnotations($id);
        return $this->json($result);
    }
}
