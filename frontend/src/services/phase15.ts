import { apiClient } from './api';

// ============================================================
// TypeScript Interfaces
// ============================================================

export interface WebSocketMessage {
  id: string;
  event_type: string;
  workspace_id?: string;
  user_id?: number;
  data: Record<string, unknown>;
  timestamp: string;
  broadcast: boolean;
}

export interface UserMessage {
  id: string;
  user_id: number;
  event_type: string;
  data: string;
  read: boolean;
  created_at: string;
}

export interface ConnectionStats {
  total_active_connections: number;
  total_messages_pending: number;
  workspaces_active: number;
}

export interface PushNotification {
  id: string;
  user_id: number;
  title: string;
  message: string;
  action_url?: string;
  metadata: Record<string, unknown>;
  read: boolean;
  created_at: string;
}

export interface NotificationStats {
  total_notifications: number;
  unread_notifications: number;
  read_notifications: number;
}

export interface ActivityDigest {
  user_id: number;
  frequency: string;
  period: string;
  generated_at: string;
  workspaces: Array<{
    name: string;
    activity_count: number;
    comment_count: number;
    activities: Array<Record<string, unknown>>;
    top_comments: Array<Record<string, unknown>>;
  }>;
}

export interface NotificationSettings {
  notify_mentions: boolean;
  notify_replies: boolean;
  notify_reactions: boolean;
  notify_votes: boolean;
  digest_frequency: 'never' | 'daily' | 'weekly' | 'monthly';
  quiet_hours_enabled: boolean;
  quiet_hours_start: string;
  quiet_hours_end: string;
}

export interface WorkspaceNotificationSettings {
  mute_workspace: boolean;
  mute_all_comments: boolean;
}

// ============================================================
// WebSocket Service
// ============================================================

export async function broadcastToWorkspace(
  workspaceId: string,
  eventType: string,
  data: Record<string, unknown> = {}
): Promise<WebSocketMessage> {
  const response = await apiClient.post('/api/admin/phase15/websocket/broadcast-workspace', {
    workspace_id: workspaceId,
    event_type: eventType,
    data,
  });
  return response.data.data;
}

export async function broadcastToUser(
  userId: number,
  eventType: string,
  data: Record<string, unknown> = {}
): Promise<WebSocketMessage> {
  const response = await apiClient.post('/api/admin/phase15/websocket/broadcast-user', {
    user_id: userId,
    event_type: eventType,
    data,
  });
  return response.data.data;
}

export async function getChannelSubscribers(workspaceId: string): Promise<{ channel: string; subscribers: number }> {
  const response = await apiClient.get(`/api/admin/phase15/websocket/channel-subscribers/${workspaceId}`);
  return response.data.data;
}

export async function storeMessage(
  userId: number,
  eventType: string,
  data: Record<string, unknown> = {}
): Promise<UserMessage> {
  const response = await apiClient.post('/api/admin/phase15/websocket/store-message', {
    user_id: userId,
    event_type: eventType,
    data,
  });
  return response.data.data;
}

export async function getPendingMessages(userId: number, limit: number = 50): Promise<UserMessage[]> {
  const response = await apiClient.get('/api/admin/phase15/websocket/pending-messages', {
    params: { user_id: userId, limit },
  });
  return response.data.data;
}

export async function markMessageRead(messageId: string): Promise<void> {
  await apiClient.put(`/api/admin/phase15/websocket/mark-read/${messageId}`);
}

export async function getConnectionStats(): Promise<ConnectionStats> {
  const response = await apiClient.get('/api/admin/phase15/websocket/connection-stats');
  return response.data.data;
}

// ============================================================
// Push Notification Service
// ============================================================

export async function sendNotification(
  userId: number,
  title: string,
  message: string,
  actionUrl?: string,
  metadata?: Record<string, unknown>
): Promise<PushNotification> {
  const response = await apiClient.post('/api/admin/phase15/notifications/send', {
    user_id: userId,
    title,
    message,
    action_url: actionUrl,
    metadata,
  });
  return response.data.data;
}

export async function sendBulkNotification(
  userIds: number[],
  title: string,
  message: string,
  actionUrl?: string
): Promise<{ total: number; sent: number }> {
  const response = await apiClient.post('/api/admin/phase15/notifications/send-bulk', {
    user_ids: userIds,
    title,
    message,
    action_url: actionUrl,
  });
  return response.data.data;
}

export async function sendWorkspaceNotification(
  workspaceId: string,
  title: string,
  message: string,
  excludeUserId?: number
): Promise<{ total: number; sent: number }> {
  const response = await apiClient.post('/api/admin/phase15/notifications/send-workspace', {
    workspace_id: workspaceId,
    title,
    message,
    exclude_user_id: excludeUserId,
  });
  return response.data.data;
}

export async function getUserNotifications(
  userId: number,
  limit: number = 50,
  offset: number = 0
): Promise<{ data: PushNotification[]; total: number }> {
  const response = await apiClient.get('/api/admin/phase15/notifications/user', {
    params: { user_id: userId, limit, offset },
  });
  return {
    data: response.data.data,
    total: response.data.total,
  };
}

export async function getUnreadCount(userId: number): Promise<number> {
  const response = await apiClient.get('/api/admin/phase15/notifications/unread-count', {
    params: { user_id: userId },
  });
  return response.data.data.unread_count;
}

export async function markNotificationRead(notificationId: string): Promise<void> {
  await apiClient.put(`/api/admin/phase15/notifications/${notificationId}/read`);
}

export async function markAllNotificationsRead(userId: number): Promise<void> {
  await apiClient.put('/api/admin/phase15/notifications/mark-all-read', {}, {
    params: { user_id: userId },
  });
}

export async function deleteNotification(notificationId: string): Promise<void> {
  await apiClient.delete(`/api/admin/phase15/notifications/${notificationId}`);
}

export async function getNotificationStats(userId: number): Promise<NotificationStats> {
  const response = await apiClient.get('/api/admin/phase15/notifications/stats', {
    params: { user_id: userId },
  });
  return response.data.data;
}

// ============================================================
// Comment Notification Service
// ============================================================

export async function notifyMentions(
  commentId: string,
  authorId: number,
  mentionedUserIds: number[]
): Promise<{ notified: number }> {
  const response = await apiClient.post('/api/admin/phase15/comment-notifications/mentions', {
    comment_id: commentId,
    author_id: authorId,
    mentioned_user_ids: mentionedUserIds,
  });
  return response.data.data;
}

export async function notifyReply(
  parentCommentId: string,
  replyerId: number,
  replyContent: string
): Promise<{ notified: number }> {
  const response = await apiClient.post('/api/admin/phase15/comment-notifications/reply', {
    parent_comment_id: parentCommentId,
    replier_id: replyerId,
    reply_content: replyContent,
  });
  return response.data.data;
}

export async function notifyReaction(
  commentId: string,
  reactorId: number,
  reactionType: string
): Promise<{ notified: number }> {
  const response = await apiClient.post('/api/admin/phase15/comment-notifications/reaction', {
    comment_id: commentId,
    reactor_id: reactorId,
    reaction_type: reactionType,
  });
  return response.data.data;
}

export async function notifyVote(
  commentId: string,
  voterId: number,
  voteType: 'up' | 'down'
): Promise<{ notified: number }> {
  const response = await apiClient.post('/api/admin/phase15/comment-notifications/vote', {
    comment_id: commentId,
    voter_id: voterId,
    vote_type: voteType,
  });
  return response.data.data;
}

// ============================================================
// Activity Digest Service
// ============================================================

export async function generateDigest(
  userId: number,
  frequency: 'daily' | 'weekly' | 'monthly' = 'daily'
): Promise<ActivityDigest> {
  const response = await apiClient.post('/api/admin/phase15/digests/generate', {
    user_id: userId,
    frequency,
  });
  return response.data.data;
}

export async function sendDigest(
  userId: number,
  frequency: 'daily' | 'weekly' | 'monthly' = 'daily'
): Promise<void> {
  await apiClient.post('/api/admin/phase15/digests/send', {
    user_id: userId,
    frequency,
  });
}

export async function sendScheduledDigests(frequency: 'daily' | 'weekly' | 'monthly'): Promise<{ total_users: number; digests_sent: number }> {
  const response = await apiClient.post(`/api/admin/phase15/digests/send-scheduled/${frequency}`);
  return response.data.data;
}

export async function getDigestHistory(userId: number, limit: number = 20): Promise<Array<Record<string, unknown>>> {
  const response = await apiClient.get('/api/admin/phase15/digests/history', {
    params: { user_id: userId, limit },
  });
  return response.data.data;
}

export async function getDigestStats(frequency: 'daily' | 'weekly' | 'monthly'): Promise<Record<string, unknown>> {
  const response = await apiClient.get(`/api/admin/phase15/digests/stats/${frequency}`);
  return response.data.data;
}

// ============================================================
// Notification Settings Service
// ============================================================

export async function getUserSettings(userId: number): Promise<NotificationSettings> {
  const response = await apiClient.get('/api/admin/phase15/settings/user', {
    params: { user_id: userId },
  });
  return response.data.data;
}

export async function updateUserSettings(userId: number, settings: Partial<NotificationSettings>): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/user', {
    user_id: userId,
    ...settings,
  });
}

export async function toggleNotificationType(userId: number, type: string, enabled: boolean): Promise<void> {
  await apiClient.put(`/api/admin/phase15/settings/toggle/${type}`, {
    user_id: userId,
    enabled,
  });
}

export async function setDigestFrequency(
  userId: number,
  frequency: 'never' | 'daily' | 'weekly' | 'monthly'
): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/digest-frequency', {
    user_id: userId,
    frequency,
  });
}

export async function setQuietHours(
  userId: number,
  startTime: string,
  endTime: string,
  enabled: boolean = true
): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/quiet-hours', {
    user_id: userId,
    start_time: startTime,
    end_time: endTime,
    enabled,
  });
}

export async function isInQuietHours(userId: number): Promise<boolean> {
  const response = await apiClient.get('/api/admin/phase15/settings/quiet-hours-check', {
    params: { user_id: userId },
  });
  return response.data.data.in_quiet_hours;
}

export async function getWorkspaceSettings(
  userId: number,
  workspaceId: string
): Promise<WorkspaceNotificationSettings> {
  const response = await apiClient.get('/api/admin/phase15/settings/workspace', {
    params: { user_id: userId, workspace_id: workspaceId },
  });
  return response.data.data;
}

export async function updateWorkspaceSettings(
  userId: number,
  workspaceId: string,
  settings: Partial<WorkspaceNotificationSettings>
): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/workspace', {
    user_id: userId,
    workspace_id: workspaceId,
    ...settings,
  });
}

export async function muteWorkspace(userId: number, workspaceId: string): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/mute-workspace', {
    user_id: userId,
    workspace_id: workspaceId,
  });
}

export async function unmuteWorkspace(userId: number, workspaceId: string): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/unmute-workspace', {
    user_id: userId,
    workspace_id: workspaceId,
  });
}

export async function getMutedWorkspaces(userId: number): Promise<Array<{ id: string; name: string }>> {
  const response = await apiClient.get('/api/admin/phase15/settings/muted-workspaces', {
    params: { user_id: userId },
  });
  return response.data.data;
}

export async function resetSettingsToDefaults(userId: number): Promise<void> {
  await apiClient.put('/api/admin/phase15/settings/reset', {
    user_id: userId,
  });
}
