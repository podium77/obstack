import { apiClient } from './api';

// ============================================================
// TYPES
// ============================================================

export interface RealtimeConnection {
  connectionId: string;
  userId: number;
  connectedAt: string;
}

export interface ActiveUser {
  user_id: number;
  displayName: string;
  email: string;
  connection_count: number;
  last_activity: string;
}

export interface Activity {
  id: string;
  event_type: string;
  user_id?: number;
  query_id?: number;
  workspace_id?: string;
  description: string;
  metadata: Record<string, unknown>;
  created_at: string;
  display_name?: string;
  email?: string;
  workspace_name?: string;
}

export interface ActivityStats {
  totalEvents: number;
  eventsByType: Array<{ event_type: string; count: number }>;
  topContributors: Array<{ user_id: number; displayName: string; count: number }>;
  recentEvents: number;
}

export interface SearchResult {
  id: number;
  name: string;
  description?: string;
  owner_name?: string;
  created_at?: string;
  updated_at?: string;
  relevance_score?: number;
}

export interface CommentReaction {
  reaction_type: string;
  count: number;
  users: Array<{ userId: number; displayName: string }>;
}

export interface VoteStats {
  upvotes: number;
  downvotes: number;
  score: number;
}

export interface AuditLog {
  id: string;
  user_id: number;
  action: string;
  entity_type: string;
  entity_id?: string;
  workspace_id?: string;
  changes: Record<string, unknown>;
  ip_address: string;
  created_at: string;
  display_name?: string;
  workspace_name?: string;
}

export interface AuditStats {
  totalEvents: number;
  eventsByAction: Array<{ action: string; count: number }>;
  eventsByEntity: Array<{ entity_type: string; count: number }>;
  topUsers: Array<{ user_id: number; display_name: string; count: number }>;
  recent24h: number;
}

// ============================================================
// REALTIME METHODS
// ============================================================

export async function registerConnection(connectionId: string): Promise<RealtimeConnection> {
  const response = await apiClient.post('/api/admin/phase14/realtime/connect', {
    connectionId,
  });
  return response.data.data;
}

export async function unregisterConnection(connectionId: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/phase14/realtime/disconnect', {
    connectionId,
  });
  return response.data;
}

export async function getActiveUsers(workspaceId: string): Promise<ActiveUser[]> {
  const response = await apiClient.get(`/api/admin/phase14/realtime/workspaces/${workspaceId}/active-users`);
  return response.data.data || [];
}

export async function updateHeartbeat(connectionId: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/phase14/realtime/heartbeat', {
    connectionId,
  });
  return response.data;
}

export async function subscribeToWorkspace(connectionId: string, workspaceId: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/phase14/realtime/subscribe', {
    connectionId,
    workspaceId,
  });
  return response.data;
}

// ============================================================
// ACTIVITY FEED METHODS
// ============================================================

export async function getWorkspaceActivity(workspaceId: string, limit = 50, offset = 0): Promise<Activity[]> {
  const response = await apiClient.get(`/api/admin/phase14/activity/workspaces/${workspaceId}`, {
    params: { limit, offset },
  });
  return response.data.data || [];
}

export async function getUserActivity(limit = 50, offset = 0): Promise<Activity[]> {
  const response = await apiClient.get('/api/admin/phase14/activity/user', {
    params: { limit, offset },
  });
  return response.data.data || [];
}

export async function getQueryActivity(queryId: number): Promise<Activity[]> {
  const response = await apiClient.get(`/api/admin/phase14/activity/queries/${queryId}`);
  return response.data.data || [];
}

export async function getActivityStats(workspaceId: string): Promise<ActivityStats> {
  const response = await apiClient.get(`/api/admin/phase14/activity/workspaces/${workspaceId}/stats`);
  return response.data.data;
}

// ============================================================
// SEARCH METHODS
// ============================================================

export async function searchQueries(
  term: string,
  workspaceId?: string,
  sortBy = 'relevance',
  limit = 50,
  offset = 0
): Promise<{ data: SearchResult[]; total: number; count: number; query: string }> {
  const response = await apiClient.get('/api/admin/phase14/search/queries', {
    params: { q: term, workspaceId, sortBy, limit, offset },
  });
  return response.data;
}

export async function filterQueries(filters: Record<string, unknown>): Promise<{ data: SearchResult[]; count: number }> {
  const response = await apiClient.post('/api/admin/phase14/search/queries/filter', filters);
  return response.data;
}

export async function searchComments(term: string): Promise<unknown[]> {
  const response = await apiClient.get('/api/admin/phase14/search/comments', {
    params: { q: term },
  });
  return response.data.data || [];
}

export async function searchUsers(term: string, workspaceId?: string): Promise<Array<{ id: number; email: string; display_name: string }>> {
  const response = await apiClient.get('/api/admin/phase14/search/users', {
    params: { q: term, workspaceId },
  });
  return response.data.data || [];
}

export async function getSearchSuggestions(term: string): Promise<{ queries: string[]; workspaces: string[] }> {
  const response = await apiClient.get('/api/admin/phase14/search/suggestions', {
    params: { q: term },
  });
  return response.data.data;
}

// ============================================================
// REACTION METHODS
// ============================================================

export async function addReaction(commentId: string, reactionType: string): Promise<{ success: boolean }> {
  const response = await apiClient.post('/api/admin/phase14/reactions/comments', {
    commentId,
    reactionType,
  });
  return response.data;
}

export async function removeReaction(commentId: string, reactionType: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete('/api/admin/phase14/reactions/comments', {
    data: { commentId, reactionType },
  });
  return response.data;
}

export async function getCommentReactions(commentId: string): Promise<CommentReaction[]> {
  const response = await apiClient.get(`/api/admin/phase14/reactions/comments/${commentId}`);
  return response.data.data || [];
}

export async function voteOnComment(commentId: string, voteType: 'up' | 'down'): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/phase14/reactions/votes', {
    commentId,
    voteType,
  });
  return response.data;
}

export async function getCommentVotes(commentId: string): Promise<VoteStats> {
  const response = await apiClient.get(`/api/admin/phase14/reactions/votes/${commentId}`);
  return response.data.data;
}

export async function getMostReactedComments(queryId: number): Promise<unknown[]> {
  const response = await apiClient.get(`/api/admin/phase14/reactions/queries/${queryId}/most-reacted`);
  return response.data.data || [];
}

export async function getMostVotedComments(queryId: number): Promise<unknown[]> {
  const response = await apiClient.get(`/api/admin/phase14/reactions/queries/${queryId}/most-voted`);
  return response.data.data || [];
}

// ============================================================
// AUDIT METHODS
// ============================================================

export async function getWorkspaceAuditLogs(workspaceId: string, limit = 100, offset = 0): Promise<AuditLog[]> {
  const response = await apiClient.get(`/api/admin/phase14/audit/workspaces/${workspaceId}`, {
    params: { limit, offset },
  });
  return response.data.data || [];
}

export async function getUserAuditTrail(): Promise<AuditLog[]> {
  const response = await apiClient.get('/api/admin/phase14/audit/user');
  return response.data.data || [];
}

export async function getAuditByAction(workspaceId: string, action: string): Promise<AuditLog[]> {
  const response = await apiClient.get(`/api/admin/phase14/audit/workspaces/${workspaceId}/actions/${action}`);
  return response.data.data || [];
}

export async function getAuditStats(workspaceId: string): Promise<AuditStats> {
  const response = await apiClient.get(`/api/admin/phase14/audit/workspaces/${workspaceId}/stats`);
  return response.data.data;
}

export async function exportAuditLogs(workspaceId: string, format = 'csv'): Promise<string> {
  const response = await apiClient.get(`/api/admin/phase14/audit/workspaces/${workspaceId}/export`, {
    params: { format },
  });
  return response.data.data;
}

export async function generateAuditReport(workspaceId: string, startDate: string, endDate: string): Promise<unknown[]> {
  const response = await apiClient.get(`/api/admin/phase14/audit/workspaces/${workspaceId}/report`, {
    params: { startDate, endDate },
  });
  return response.data.data || [];
}
