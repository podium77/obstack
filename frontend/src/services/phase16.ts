import { apiClient } from './api';

/**
 * Phase 16: WebSocket Server & Presence TypeScript Service
 * 
 * Provides complete client-side API for WebSocket server management,
 * presence tracking, cursor synchronization, collaboration indicators, and typing notifications.
 */

// ============================================================
// TypeScript Interfaces
// ============================================================

export interface WebSocketConnection {
  id: string;
  connection_id: string;
  user_id: number;
  workspace_id?: string;
  document_id?: string;
  status: 'connected' | 'disconnected';
}

export interface UserPresence {
  user_id: number;
  status: 'online' | 'idle' | 'away' | 'offline';
  workspace_id?: string;
  document_id?: string;
  last_seen: string;
}

export interface CursorPosition {
  user_id: number;
  user_name: string;
  cursor: {
    line: number;
    column: number;
  };
  selection?: {
    start: { line: number; column: number };
    end: { line: number; column: number };
  };
  updated_at: string;
}

export interface CollaborationIndicator {
  user_id: number;
  user_name: string;
  role: 'editor' | 'viewer';
  status: 'active' | 'inactive';
  edit_count?: number;
  last_edit?: string;
}

export interface TypingUser {
  user_id: number;
  user_name: string;
  position?: { line: number; column: number };
  characters_typed: number;
  expires_at: string;
}

export interface ServerStats {
  total_active_connections: number;
  total_rooms: number;
  active_workspaces: number;
  active_documents: number;
  avg_connection_time_seconds: number;
  server_status: string;
}

export interface PresenceStats {
  online: number;
  idle: number;
  away: number;
  offline: number;
  total: number;
  active_workspaces: number;
}

export interface CollaborationStats {
  document_id: string;
  active_editors: number;
  active_viewers: number;
  total_participants: number;
  recent_edits: number;
  editors: CollaborationIndicator[];
  viewers: CollaborationIndicator[];
}

export interface TypingStats {
  document_id: string;
  users_typing: number;
  total_characters_typed: number;
  typing_users: TypingUser[];
}

// ============================================================
// WebSocket Server Functions (8)
// ============================================================

export async function registerConnection(
  userId: number,
  connectionId: string,
  workspaceId?: string,
  documentId?: string,
  clientIp?: string
): Promise<WebSocketConnection> {
  const response = await apiClient.post('/api/admin/phase16/websocket/register', {
    user_id: userId,
    connection_id: connectionId,
    workspace_id: workspaceId,
    document_id: documentId,
    client_ip: clientIp
  });
  return response.data.data;
}

export async function unregisterConnection(connectionId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/websocket/unregister', {
    connection_id: connectionId
  });
  return response.data.success;
}

export async function sendHeartbeat(connectionId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/websocket/heartbeat', {
    connection_id: connectionId
  });
  return response.data.success;
}

export async function subscribeToRoom(connectionId: string, roomId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/websocket/subscribe-room', {
    connection_id: connectionId,
    room_id: roomId
  });
  return response.data.success;
}

export async function unsubscribeFromRoom(connectionId: string, roomId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/websocket/unsubscribe-room', {
    connection_id: connectionId,
    room_id: roomId
  });
  return response.data.success;
}

export async function getRoomConnections(roomId: string): Promise<WebSocketConnection[]> {
  const response = await apiClient.get(`/api/admin/phase16/websocket/room-connections/${roomId}`);
  return response.data.data.connections;
}

export async function getServerStats(): Promise<ServerStats> {
  const response = await apiClient.get('/api/admin/phase16/websocket/server-stats');
  return response.data.data;
}

export async function cleanupStaleConnections(): Promise<{ cleaned_count: number }> {
  const response = await apiClient.post('/api/admin/phase16/websocket/cleanup-stale');
  return response.data.data;
}

// ============================================================
// Presence Functions (8)
// ============================================================

export async function updatePresence(
  userId: number,
  status: 'online' | 'idle' | 'away' | 'offline',
  workspaceId?: string,
  documentId?: string
): Promise<UserPresence> {
  const response = await apiClient.post('/api/admin/phase16/presence/update', {
    user_id: userId,
    status,
    workspace_id: workspaceId,
    document_id: documentId
  });
  return response.data.data;
}

export async function setUserOffline(userId: number): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/presence/set-offline', {
    user_id: userId
  });
  return response.data.success;
}

export async function getUserPresence(userId: number): Promise<UserPresence | null> {
  const response = await apiClient.get(`/api/admin/phase16/presence/user/${userId}`);
  return response.data.data;
}

export async function getWorkspaceOnlineUsers(workspaceId: string): Promise<UserPresence[]> {
  const response = await apiClient.get(`/api/admin/phase16/presence/workspace/${workspaceId}`);
  return response.data.data.users;
}

export async function getDocumentUsers(documentId: string): Promise<UserPresence[]> {
  const response = await apiClient.get(`/api/admin/phase16/presence/document/${documentId}`);
  return response.data.data.users;
}

export async function getPresenceStats(): Promise<PresenceStats> {
  const response = await apiClient.get('/api/admin/phase16/presence/stats');
  return response.data.data;
}

export async function recordActivity(userId: number): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/presence/record-activity', {
    user_id: userId
  });
  return response.data.success;
}

export async function isUserOnline(userId: number): Promise<boolean> {
  const response = await apiClient.get(`/api/admin/phase16/presence/check-online/${userId}`);
  return response.data.data.is_online;
}

// ============================================================
// Cursor Tracking Functions (8)
// ============================================================

export async function updateCursorPosition(
  userId: number,
  documentId: string,
  line: number,
  column: number,
  selectionStartLine?: number,
  selectionStartColumn?: number,
  selectionEndLine?: number,
  selectionEndColumn?: number
): Promise<{ cursor: { line: number; column: number }; selection?: object; timestamp: string }> {
  const response = await apiClient.post('/api/admin/phase16/cursor/update', {
    user_id: userId,
    document_id: documentId,
    line,
    column,
    selection_start_line: selectionStartLine,
    selection_start_column: selectionStartColumn,
    selection_end_line: selectionEndLine,
    selection_end_column: selectionEndColumn
  });
  return response.data.data;
}

export async function getDocumentCursors(documentId: string): Promise<CursorPosition[]> {
  const response = await apiClient.get(`/api/admin/phase16/cursor/document/${documentId}`);
  return response.data.data.cursors;
}

export async function getUserCursor(userId: number, documentId: string): Promise<CursorPosition | null> {
  const response = await apiClient.get(`/api/admin/phase16/cursor/user/${userId}/${documentId}`);
  return response.data.data;
}

export async function clearCursor(userId: number, documentId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/cursor/clear', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.success;
}

export async function getCursorStats(documentId: string): Promise<{ active_cursors: number; unique_users: number }> {
  const response = await apiClient.get(`/api/admin/phase16/cursor/stats/${documentId}`);
  return response.data.data;
}

export async function detectCursorCollisions(documentId: string): Promise<{ collision_count: number; collisions: any[] }> {
  const response = await apiClient.get(`/api/admin/phase16/cursor/collisions/${documentId}`);
  return response.data.data;
}

export async function getCursorHistory(documentId: string): Promise<CursorPosition[]> {
  const response = await apiClient.get(`/api/admin/phase16/cursor/history/${documentId}`);
  return response.data.data.history;
}

export async function cleanupStaleCursors(): Promise<{ cleaned_count: number }> {
  const response = await apiClient.post('/api/admin/phase16/cursor/cleanup-stale');
  return response.data.data;
}

// ============================================================
// Collaboration Indicator Functions (9)
// ============================================================

export async function registerEditor(userId: number, documentId: string): Promise<CollaborationIndicator> {
  const response = await apiClient.post('/api/admin/phase16/collaboration/register-editor', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.data;
}

export async function registerViewer(userId: number, documentId: string): Promise<CollaborationIndicator> {
  const response = await apiClient.post('/api/admin/phase16/collaboration/register-viewer', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.data;
}

export async function recordEdit(userId: number, documentId: string, changeType?: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/collaboration/record-edit', {
    user_id: userId,
    document_id: documentId,
    change_type: changeType
  });
  return response.data.success;
}

export async function unregisterEditor(userId: number, documentId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/collaboration/unregister-editor', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.success;
}

export async function unregisterViewer(userId: number, documentId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/collaboration/unregister-viewer', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.success;
}

export async function getCollaborationStats(documentId: string): Promise<CollaborationStats> {
  const response = await apiClient.get(`/api/admin/phase16/collaboration/stats/${documentId}`);
  return response.data.data;
}

export async function detectConflicts(documentId: string, userId: number): Promise<{ conflict_count: number; conflicts: any[] }> {
  const response = await apiClient.get(`/api/admin/phase16/collaboration/conflicts/${documentId}/${userId}`);
  return response.data.data;
}

export async function getEditHistory(documentId: string): Promise<any[]> {
  const response = await apiClient.get(`/api/admin/phase16/collaboration/history/${documentId}`);
  return response.data.data.history;
}

export async function getCollaborationSummary(documentId: string): Promise<any> {
  const response = await apiClient.get(`/api/admin/phase16/collaboration/summary/${documentId}`);
  return response.data.data;
}

// ============================================================
// Typing Notification Functions (8)
// ============================================================

export async function recordTyping(
  userId: number,
  documentId: string,
  line?: number,
  column?: number,
  charactersAdded?: number
): Promise<{ typing: boolean; expires_at: string }> {
  const response = await apiClient.post('/api/admin/phase16/typing/record', {
    user_id: userId,
    document_id: documentId,
    line,
    column,
    characters_added: charactersAdded
  });
  return response.data.data;
}

export async function recordStoppedTyping(userId: number, documentId: string): Promise<boolean> {
  const response = await apiClient.post('/api/admin/phase16/typing/stop', {
    user_id: userId,
    document_id: documentId
  });
  return response.data.success;
}

export async function getTypingUsers(documentId: string): Promise<TypingUser[]> {
  const response = await apiClient.get(`/api/admin/phase16/typing/document/${documentId}`);
  return response.data.data.typing_users;
}

export async function getTypingCount(documentId: string): Promise<number> {
  const response = await apiClient.get(`/api/admin/phase16/typing/count/${documentId}`);
  return response.data.data.typing_count;
}

export async function getTypingStats(documentId: string): Promise<TypingStats> {
  const response = await apiClient.get(`/api/admin/phase16/typing/stats/${documentId}`);
  return response.data.data;
}

export async function detectTypingBurst(documentId: string): Promise<{ burst_count: number; burst_users: TypingUser[] }> {
  const response = await apiClient.get(`/api/admin/phase16/typing/burst/${documentId}`);
  return response.data.data;
}

export async function cleanupExpiredTyping(): Promise<{ cleaned_count: number }> {
  const response = await apiClient.post('/api/admin/phase16/typing/cleanup-expired');
  return response.data.data;
}

export async function isUserTyping(userId: number, documentId: string): Promise<boolean> {
  const response = await apiClient.get(`/api/admin/phase16/typing/check-user/${userId}/${documentId}`);
  return response.data.data.is_typing;
}
