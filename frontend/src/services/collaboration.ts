import { apiClient } from './api';

// Types for Collaboration features

export interface SharedQuery {
  id: number;
  name: string;
  connectionId: number;
  queryText: string;
  owner: string;
  accessLevel: 'view' | 'edit' | 'delete';
}

export interface QueryShare {
  id: string;
  sharedWithUserId?: number;
  sharedWithGroupId?: string;
  accessLevel: string;
  userName?: string;
  groupName?: string;
  createdAt: string;
}

export interface Workspace {
  id: string;
  name: string;
  description: string;
  ownerId: number;
  isPublic: boolean;
  memberCount: number;
  createdAt: string;
}

export interface WorkspaceStats {
  memberCount: number;
  queryCount: number;
  lastActivity: string | null;
}

export interface WorkspaceMember {
  userId: number;
  displayName: string;
  email: string;
  role: 'admin' | 'member' | 'viewer';
  joinedAt: string;
}

export interface AccessControlGroup {
  id: string;
  name: string;
  description: string;
  permissions: string[];
  memberCount: number;
  createdAt: string;
}

export interface GroupDetails extends AccessControlGroup {
  members: GroupMember[];
}

export interface GroupMember {
  id: number;
  displayName: string;
  email: string;
  joinedAt: string;
}

export interface Comment {
  id: string;
  queryId: number;
  authorId: number;
  authorName: string;
  email: string;
  content: string;
  createdAt: string;
  updatedAt: string;
  replyCount: number;
  replies?: Comment[];
}

export interface CommentStats {
  totalComments: number;
  rootComments: number;
  recentComments: number;
  topContributors: Array<{ id: number; displayName: string; count: number }>;
}

export interface Annotation {
  id: string;
  type: string;
  position: number;
  content: string;
  color: string;
  authorId: number;
  displayName: string;
  createdAt: string;
}

// Query Sharing Methods

export async function shareQuery(queryId: number, shareWith: Record<string, unknown>, accessLevel: string = 'view'): Promise<{ success: boolean; data: unknown }> {
  const response = await apiClient.post('/api/admin/collaboration/queries/share', {
    queryId,
    shareWith,
    accessLevel,
  });
  return response.data;
}

export async function getSharedQueries(): Promise<SharedQuery[]> {
  const response = await apiClient.get('/api/admin/collaboration/queries/shared');
  return response.data.data || [];
}

export async function getQueryShares(queryId: number): Promise<QueryShare[]> {
  const response = await apiClient.get(`/api/admin/collaboration/queries/${queryId}/shares`);
  return response.data.data || [];
}

export async function updateSharePermission(queryId: number, userId: number, accessLevel: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.put('/api/admin/collaboration/queries/share', {
    queryId,
    userId,
    accessLevel,
  });
  return response.data;
}

export async function revokeShare(queryId: number, userId?: number, groupId?: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete('/api/admin/collaboration/queries/share', {
    data: { queryId, userId, groupId },
  });
  return response.data;
}

// Workspace Methods

export async function createWorkspace(workspace: Omit<Workspace, 'id' | 'memberCount' | 'createdAt'>): Promise<Workspace> {
  const response = await apiClient.post('/api/admin/collaboration/workspaces', workspace);
  return response.data.data;
}

export async function getUserWorkspaces(): Promise<Workspace[]> {
  const response = await apiClient.get('/api/admin/collaboration/workspaces');
  return response.data.data || [];
}

export async function getWorkspaceMembers(workspaceId: string): Promise<WorkspaceMember[]> {
  const response = await apiClient.get(`/api/admin/collaboration/workspaces/${workspaceId}/members`);
  return response.data.data || [];
}

export async function addWorkspaceMember(workspaceId: string, userId: number, role: string = 'member'): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post(`/api/admin/collaboration/workspaces/${workspaceId}/members`, {
    userId,
    role,
  });
  return response.data;
}

export async function removeWorkspaceMember(workspaceId: string, userId: number): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete(`/api/admin/collaboration/workspaces/${workspaceId}/members/${userId}`);
  return response.data;
}

export async function getWorkspaceQueries(workspaceId: string): Promise<unknown[]> {
  const response = await apiClient.get(`/api/admin/collaboration/workspaces/${workspaceId}/queries`);
  return response.data.data || [];
}

export async function getWorkspaceStats(workspaceId: string): Promise<WorkspaceStats> {
  const response = await apiClient.get(`/api/admin/collaboration/workspaces/${workspaceId}/stats`);
  return response.data.data;
}

// Access Control Methods

export async function createGroup(group: Omit<AccessControlGroup, 'id' | 'memberCount' | 'createdAt'>): Promise<AccessControlGroup> {
  const response = await apiClient.post('/api/admin/collaboration/groups', group);
  return response.data.data;
}

export async function listGroups(): Promise<AccessControlGroup[]> {
  const response = await apiClient.get('/api/admin/collaboration/groups');
  return response.data.data || [];
}

export async function getGroupDetails(groupId: string): Promise<GroupDetails> {
  const response = await apiClient.get(`/api/admin/collaboration/groups/${groupId}`);
  return response.data.data;
}

export async function addGroupMember(groupId: string, userId: number): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post(`/api/admin/collaboration/groups/${groupId}/members`, { userId });
  return response.data;
}

export async function removeGroupMember(groupId: string, userId: number): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete(`/api/admin/collaboration/groups/${groupId}/members/${userId}`);
  return response.data;
}

export async function updateGroupPermissions(groupId: string, permissions: string[]): Promise<{ success: boolean; data: { groupId: string; permissions: string[] } }> {
  const response = await apiClient.put(`/api/admin/collaboration/groups/${groupId}/permissions`, { permissions });
  return response.data;
}

export async function getUserGroups(): Promise<AccessControlGroup[]> {
  const response = await apiClient.get('/api/admin/collaboration/user/groups');
  return response.data.data || [];
}

export async function deleteGroup(groupId: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete(`/api/admin/collaboration/groups/${groupId}`);
  return response.data;
}

// Comment Methods

export async function addComment(queryId: number, content: string, parentCommentId?: number): Promise<Comment> {
  const response = await apiClient.post(`/api/admin/collaboration/queries/${queryId}/comments`, {
    content,
    parentCommentId,
  });
  return response.data.data;
}

export async function getComments(queryId: number): Promise<Comment[]> {
  const response = await apiClient.get(`/api/admin/collaboration/queries/${queryId}/comments`);
  return response.data.data || [];
}

export async function updateComment(commentId: string, content: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.put(`/api/admin/collaboration/comments/${commentId}`, { content });
  return response.data;
}

export async function deleteComment(commentId: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete(`/api/admin/collaboration/comments/${commentId}`);
  return response.data;
}

export async function getCommentStats(queryId: number): Promise<CommentStats> {
  const response = await apiClient.get(`/api/admin/collaboration/queries/${queryId}/comments/stats`);
  return response.data.data;
}

// Annotation Methods

export async function addAnnotation(queryId: number, annotation: Omit<Annotation, 'id' | 'authorId' | 'displayName' | 'createdAt'>): Promise<Annotation> {
  const response = await apiClient.post(`/api/admin/collaboration/queries/${queryId}/annotations`, annotation);
  return response.data.data;
}

export async function getAnnotations(queryId: number): Promise<Annotation[]> {
  const response = await apiClient.get(`/api/admin/collaboration/queries/${queryId}/annotations`);
  return response.data.data || [];
}
