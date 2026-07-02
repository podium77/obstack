import { apiClient } from './api'

// ==================== TypeScript Interfaces ====================

export interface RealtimeMessage {
  id: string
  from_user_id: number
  from_user_name?: string
  to_user_id: number
  message_type: string
  payload: Record<string, any>
  delivery_status: 'pending' | 'delivered' | 'acknowledged' | 'failed'
  created_at: string
  workspace_id?: string
  document_id?: string
}

export interface EditOperation {
  id: string
  user_id: number
  user_name?: string
  document_id: string
  operation_type: 'insert' | 'delete' | 'update'
  position: number
  content?: string
  length: number
  timestamp: string
}

export interface ConflictDetection {
  operation1_id?: string
  operation2_id?: string
  user1_id: number
  user2_id: number
  conflict_type: string
  position_overlap: number[]
  severity: 'low' | 'medium' | 'high'
}

export interface QueueMessage {
  id: string
  message_id: string
  event_type: string
  status: 'pending' | 'processing' | 'processed' | 'failed'
  priority: number
  retry_count: number
  created_at: string
}

export interface WebSocketGatewayStats {
  active_connections: number
  active_rooms: number
  registered_listeners: number
  recent_events_1h: number
  event_types: Array<{ event_type: string; count: number }>
  gateway_status: string
}

// ==================== Real-time Messaging API ====================

export async function sendMessage(
  fromUserId: number,
  toUserId: number,
  messageType: string,
  payload: Record<string, any>,
  workspaceId?: string,
  documentId?: string
): Promise<RealtimeMessage> {
  const response = await apiClient.post('/phase17/messaging/send', {
    from_user_id: fromUserId,
    to_user_id: toUserId,
    message_type: messageType,
    payload,
    workspace_id: workspaceId,
    document_id: documentId
  })
  return response.data
}

export async function broadcastMessage(
  fromUserId: number,
  toUserIds: number[],
  messageType: string,
  payload: Record<string, any>,
  workspaceId?: string
): Promise<{ message_ids: string[]; recipient_count: number }> {
  const response = await apiClient.post('/phase17/messaging/broadcast', {
    from_user_id: fromUserId,
    to_user_ids: toUserIds,
    message_type: messageType,
    payload,
    workspace_id: workspaceId
  })
  return response.data
}

export async function getPendingMessages(userId: number): Promise<RealtimeMessage[]> {
  const response = await apiClient.get(`/phase17/messaging/pending/${userId}`)
  return response.data
}

export async function getMessageHistory(userId1: number, userId2: number): Promise<RealtimeMessage[]> {
  const response = await apiClient.get(`/phase17/messaging/history/${userId1}/${userId2}`)
  return response.data
}

export async function markMessageDelivered(messageId: string): Promise<boolean> {
  const response = await apiClient.post(`/phase17/messaging/mark-delivered/${messageId}`, {})
  return response.success
}

export async function markMessageAcknowledged(messageId: string): Promise<boolean> {
  const response = await apiClient.post(`/phase17/messaging/mark-acknowledged/${messageId}`, {})
  return response.success
}

export async function getMessagingStats(): Promise<{
  pending: number
  delivered: number
  acknowledged: number
  failed: number
  total: number
  avg_delivery_time_seconds: number
}> {
  const response = await apiClient.get('/phase17/messaging/stats')
  return response.data
}

export async function getConversationPartners(userId: number): Promise<
  Array<{ partner_id: number; name: string; email: string }>
> {
  const response = await apiClient.get(`/phase17/messaging/partners/${userId}`)
  return response.data
}

export async function getWorkspaceMessages(workspaceId: string): Promise<RealtimeMessage[]> {
  const response = await apiClient.get(`/phase17/messaging/workspace/${workspaceId}`)
  return response.data
}

// ==================== Conflict Resolution API ====================

export async function recordOperation(
  userId: number,
  documentId: string,
  operationType: 'insert' | 'delete' | 'update',
  position: number,
  content?: string,
  length?: number
): Promise<EditOperation> {
  const response = await apiClient.post('/phase17/conflicts/record', {
    user_id: userId,
    document_id: documentId,
    operation_type: operationType,
    position,
    content,
    length
  })
  return response.data
}

export async function detectConflicts(
  documentId: string,
  operations: EditOperation[]
): Promise<{
  document_id: string
  conflict_count: number
  conflicts: ConflictDetection[]
  has_conflicts: boolean
}> {
  const response = await apiClient.post('/phase17/conflicts/detect', {
    document_id: documentId,
    operations
  })
  return response.data
}

export async function transformOperation(
  operation1: EditOperation,
  operation2: EditOperation
): Promise<EditOperation> {
  const response = await apiClient.post('/phase17/conflicts/transform', {
    operation1,
    operation2
  })
  return response.data
}

export async function resolveConflict(
  conflictId: string,
  strategy: 'first_write_wins' | 'last_write_wins' | 'user_priority' | 'merge'
): Promise<boolean> {
  const response = await apiClient.post(`/phase17/conflicts/resolve/${conflictId}`, {
    strategy
  })
  return response.success
}

export async function getConflictHistory(documentId: string): Promise<ConflictDetection[]> {
  const response = await apiClient.get(`/phase17/conflicts/history/${documentId}`)
  return response.data
}

export async function getConflictStats(documentId: string): Promise<{
  document_id: string
  total_conflicts: number
  resolved: number
  unresolved: number
  by_type: Array<{ conflict_type: string; count: number }>
}> {
  const response = await apiClient.get(`/phase17/conflicts/stats/${documentId}`)
  return response.data
}

export async function getOperationHistory(documentId: string): Promise<EditOperation[]> {
  const response = await apiClient.get(`/phase17/conflicts/operations/${documentId}`)
  return response.data
}

// ==================== Message Queue API ====================

export async function enqueueMessage(
  messageId: string,
  eventType: string,
  payload: Record<string, any>,
  priority?: number
): Promise<{ queue_id: string; status: string }> {
  const response = await apiClient.post('/phase17/queue/enqueue', {
    message_id: messageId,
    event_type: eventType,
    payload,
    priority
  })
  return response.data
}

export async function dequeueMessage(): Promise<QueueMessage | null> {
  const response = await apiClient.post('/phase17/queue/dequeue', {})
  return response.data
}

export async function processQueue(batchSize: number = 50): Promise<{
  batch_size: number
  processed: number
  failed: number
  remaining: number
}> {
  const response = await apiClient.post('/phase17/queue/process', {
    batch_size: batchSize
  })
  return response.data
}

export async function getQueueStats(): Promise<{
  pending: number
  processing: number
  processed: number
  failed: number
  total: number
  avg_processing_time_seconds: number
  queue_utilization: string
}> {
  const response = await apiClient.get('/phase17/queue/stats')
  return response.data
}

export async function getQueueByStatus(status: string): Promise<QueueMessage[]> {
  const response = await apiClient.get(`/phase17/queue/status/${status}`)
  return response.data
}

export async function getFailedMessages(): Promise<QueueMessage[]> {
  const response = await apiClient.get('/phase17/queue/failed')
  return response.data
}

export async function requeueMessage(queueId: string): Promise<boolean> {
  const response = await apiClient.post(`/phase17/queue/requeue/${queueId}`, {})
  return response.success
}

export async function getEventTypeStats(): Promise<
  Array<{ event_type: string; count: number; status: string }>
> {
  const response = await apiClient.get('/phase17/queue/events-stats')
  return response.data
}

// ==================== WebSocket Gateway API ====================

export async function registerEventListener(
  eventType: string,
  handlerClass: string,
  handlerMethod: string
): Promise<{ id: string; event_type: string; status: string }> {
  const response = await apiClient.post('/phase17/gateway/register-listener', {
    event_type: eventType,
    handler_class: handlerClass,
    handler_method: handlerMethod
  })
  return response.data
}

export async function emitEvent(
  eventType: string,
  data: Record<string, any>,
  userId?: number,
  roomId?: string,
  broadcastScope?: 'room' | 'user' | 'broadcast'
): Promise<{ event_id: string; event_type: string; scope: string }> {
  const response = await apiClient.post('/phase17/gateway/emit-event', {
    event_type: eventType,
    data,
    user_id: userId,
    room_id: roomId,
    broadcast_scope: broadcastScope || 'room'
  })
  return response.data
}

export async function registerNamespace(
  namespace: string,
  pattern?: string
): Promise<{ id: string; namespace: string; status: string }> {
  const response = await apiClient.post('/phase17/gateway/register-namespace', {
    namespace,
    pattern
  })
  return response.data
}

export async function createRoom(
  roomId: string,
  roomType: string,
  maxCapacity?: number,
  metadata?: Record<string, any>
): Promise<{ id: string; room_id: string; status: string }> {
  const response = await apiClient.post('/phase17/gateway/create-room', {
    room_id: roomId,
    room_type: roomType,
    max_capacity: maxCapacity,
    metadata
  })
  return response.data
}

export async function getGatewayStats(): Promise<WebSocketGatewayStats> {
  const response = await apiClient.get('/phase17/gateway/stats')
  return response.data
}

export async function getRecentEvents(
  eventType?: string,
  limit?: number
): Promise<Array<{ id: string; event_type: string; data: Record<string, any> }>> {
  const params = new URLSearchParams()
  if (eventType) params.append('event_type', eventType)
  if (limit) params.append('limit', limit.toString())

  const response = await apiClient.get(`/phase17/gateway/events?${params.toString()}`)
  return response.data
}

export async function getEventMetrics(eventType: string): Promise<{
  event_type: string
  total: number
  successful: number
  failed: number
  avg_latency_ms: number
  min_latency_ms: number
  max_latency_ms: number
}> {
  const response = await apiClient.get(`/phase17/gateway/metrics/${eventType}`)
  return response.data
}

export async function getConnectionThroughput(intervalMinutes: number = 5): Promise<{
  interval_minutes: number
  active_connections: number
  events_emitted: number
  messages_sent: number
  events_per_second: number
  messages_per_second: number
}> {
  const response = await apiClient.get(`/phase17/gateway/throughput?interval=${intervalMinutes}`)
  return response.data
}
