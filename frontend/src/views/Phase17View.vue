<template>
  <div class="phase17-container">
    <h1>Phase 17: Real-time Messaging & WebSocket Server</h1>
    
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab"
        :class="['tab-button', { active: activeTab === tab }]"
        @click="activeTab = tab"
      >
        {{ getTabLabel(tab) }}
      </button>
    </div>

    <!-- Tab 1: Real-time Messaging -->
    <div v-if="activeTab === 'messaging'" class="tab-content">
      <h2>Real-time Messaging</h2>
      
      <div class="section">
        <h3>Send Message</h3>
        <div class="form-group">
          <input v-model.number="forms.message.fromUserId" type="number" placeholder="From User ID" />
          <input v-model.number="forms.message.toUserId" type="number" placeholder="To User ID" />
          <input v-model="forms.message.messageType" type="text" placeholder="Message Type" />
          <textarea v-model="forms.message.payload" placeholder="Payload (JSON)" rows="3"></textarea>
          <button @click="sendMessage">Send Message</button>
        </div>
      </div>

      <div class="section">
        <h3>Broadcast Message</h3>
        <div class="form-group">
          <input v-model.number="forms.broadcast.fromUserId" type="number" placeholder="From User ID" />
          <input v-model="forms.broadcast.toUserIds" type="text" placeholder="To User IDs (comma-separated)" />
          <input v-model="forms.broadcast.messageType" type="text" placeholder="Message Type" />
          <textarea v-model="forms.broadcast.payload" placeholder="Payload (JSON)" rows="3"></textarea>
          <button @click="broadcastMessage">Broadcast Message</button>
        </div>
      </div>

      <div class="section">
        <h3>Messaging Statistics</h3>
        <div v-if="stats.messaging" class="stats-grid">
          <div class="stat">
            <span>Pending:</span>
            <strong>{{ stats.messaging.pending }}</strong>
          </div>
          <div class="stat">
            <span>Delivered:</span>
            <strong>{{ stats.messaging.delivered }}</strong>
          </div>
          <div class="stat">
            <span>Acknowledged:</span>
            <strong>{{ stats.messaging.acknowledged }}</strong>
          </div>
          <div class="stat">
            <span>Failed:</span>
            <strong>{{ stats.messaging.failed }}</strong>
          </div>
          <div class="stat">
            <span>Avg Delivery Time:</span>
            <strong>{{ stats.messaging.avg_delivery_time_seconds }}s</strong>
          </div>
        </div>
        <button @click="loadMessagingStats">Refresh Stats</button>
      </div>

      <div class="section">
        <h3>Pending Messages for User</h3>
        <div class="form-group">
          <input v-model.number="forms.viewMessages.userId" type="number" placeholder="User ID" />
          <button @click="getPendingMessages">Load Pending</button>
        </div>
        <div v-if="messages.pending.length > 0" class="list">
          <div v-for="msg in messages.pending" :key="msg.id" class="list-item">
            <strong>{{ msg.message_type }}</strong> from {{ msg.from_user_name }}
            <button @click="markDelivered(msg.id)">Mark Delivered</button>
            <button @click="markAcknowledged(msg.id)">Mark Ack</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Conflict Resolution -->
    <div v-if="activeTab === 'conflicts'" class="tab-content">
      <h2>Conflict Resolution & Operational Transformation</h2>

      <div class="section">
        <h3>Record Operation</h3>
        <div class="form-group">
          <input v-model.number="forms.operation.userId" type="number" placeholder="User ID" />
          <input v-model="forms.operation.documentId" type="text" placeholder="Document ID" />
          <select v-model="forms.operation.operationType">
            <option value="insert">Insert</option>
            <option value="delete">Delete</option>
            <option value="update">Update</option>
          </select>
          <input v-model.number="forms.operation.position" type="number" placeholder="Position" />
          <input v-model="forms.operation.content" type="text" placeholder="Content" />
          <input v-model.number="forms.operation.length" type="number" placeholder="Length" />
          <button @click="recordOperation">Record Operation</button>
        </div>
      </div>

      <div class="section">
        <h3>Detect Conflicts</h3>
        <div class="form-group">
          <input v-model="forms.detectConflicts.documentId" type="text" placeholder="Document ID" />
          <button @click="detectConflicts">Detect Conflicts</button>
        </div>
        <div v-if="conflicts.detected" class="detection-result">
          <strong>Conflict Count:</strong> {{ conflicts.detected.conflict_count }}
          <div v-if="conflicts.detected.conflicts.length > 0" class="list">
            <div v-for="(c, i) in conflicts.detected.conflicts" :key="i" class="list-item">
              <span>{{ c.conflict_type }} - Severity: {{ c.severity }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="section">
        <h3>Conflict Statistics</h3>
        <div class="form-group">
          <input v-model="forms.conflictStats.documentId" type="text" placeholder="Document ID" />
          <button @click="getConflictStats">Load Stats</button>
        </div>
        <div v-if="stats.conflicts" class="stats-grid">
          <div class="stat">
            <span>Total Conflicts:</span>
            <strong>{{ stats.conflicts.total_conflicts }}</strong>
          </div>
          <div class="stat">
            <span>Resolved:</span>
            <strong>{{ stats.conflicts.resolved }}</strong>
          </div>
          <div class="stat">
            <span>Unresolved:</span>
            <strong>{{ stats.conflicts.unresolved }}</strong>
          </div>
        </div>
      </div>

      <div class="section">
        <h3>Operation History</h3>
        <div class="form-group">
          <input v-model="forms.operationHistory.documentId" type="text" placeholder="Document ID" />
          <button @click="getOperationHistory">Load History</button>
        </div>
        <div v-if="history.operations.length > 0" class="list">
          <div v-for="op in history.operations" :key="op.id" class="list-item">
            <strong>{{ op.operation_type }}</strong> by {{ op.user_name }} at position {{ op.position }}
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 3: Message Queue -->
    <div v-if="activeTab === 'queue'" class="tab-content">
      <h2>Message Queue Management</h2>

      <div class="section">
        <h3>Enqueue Message</h3>
        <div class="form-group">
          <input v-model="forms.enqueue.messageId" type="text" placeholder="Message ID" />
          <input v-model="forms.enqueue.eventType" type="text" placeholder="Event Type" />
          <textarea v-model="forms.enqueue.payload" placeholder="Payload (JSON)" rows="3"></textarea>
          <input v-model.number="forms.enqueue.priority" type="number" placeholder="Priority" />
          <button @click="enqueueMessage">Enqueue</button>
        </div>
      </div>

      <div class="section">
        <h3>Queue Statistics</h3>
        <div v-if="stats.queue" class="stats-grid">
          <div class="stat">
            <span>Pending:</span>
            <strong>{{ stats.queue.pending }}</strong>
          </div>
          <div class="stat">
            <span>Processing:</span>
            <strong>{{ stats.queue.processing }}</strong>
          </div>
          <div class="stat">
            <span>Processed:</span>
            <strong>{{ stats.queue.processed }}</strong>
          </div>
          <div class="stat">
            <span>Failed:</span>
            <strong>{{ stats.queue.failed }}</strong>
          </div>
          <div class="stat">
            <span>Avg Processing Time:</span>
            <strong>{{ stats.queue.avg_processing_time_seconds }}s</strong>
          </div>
          <div class="stat">
            <span>Queue Utilization:</span>
            <strong>{{ stats.queue.queue_utilization }}</strong>
          </div>
        </div>
        <button @click="loadQueueStats">Refresh Stats</button>
      </div>

      <div class="section">
        <h3>Process Queue</h3>
        <div class="form-group">
          <input v-model.number="forms.processQueue.batchSize" type="number" placeholder="Batch Size" />
          <button @click="processQueue">Process Queue</button>
        </div>
        <div v-if="queue.processResult" class="result">
          <strong>Processed:</strong> {{ queue.processResult.processed }} /
          <strong>Failed:</strong> {{ queue.processResult.failed }} /
          <strong>Remaining:</strong> {{ queue.processResult.remaining }}
        </div>
      </div>

      <div class="section">
        <h3>Failed Messages</h3>
        <button @click="getFailedMessages">Load Failed Messages</button>
        <div v-if="queue.failed.length > 0" class="list">
          <div v-for="msg in queue.failed" :key="msg.id" class="list-item">
            <span>{{ msg.event_type }} - Retry Count: {{ msg.retry_count }}</span>
            <button @click="requeueMessage(msg.id)">Requeue</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 4: WebSocket Gateway -->
    <div v-if="activeTab === 'gateway'" class="tab-content">
      <h2>WebSocket Gateway Management</h2>

      <div class="section">
        <h3>Gateway Statistics</h3>
        <div v-if="stats.gateway" class="stats-grid">
          <div class="stat">
            <span>Active Connections:</span>
            <strong>{{ stats.gateway.active_connections }}</strong>
          </div>
          <div class="stat">
            <span>Active Rooms:</span>
            <strong>{{ stats.gateway.active_rooms }}</strong>
          </div>
          <div class="stat">
            <span>Registered Listeners:</span>
            <strong>{{ stats.gateway.registered_listeners }}</strong>
          </div>
          <div class="stat">
            <span>Events (1h):</span>
            <strong>{{ stats.gateway.recent_events_1h }}</strong>
          </div>
          <div class="stat">
            <span>Status:</span>
            <strong>{{ stats.gateway.gateway_status }}</strong>
          </div>
        </div>
        <button @click="loadGatewayStats">Refresh Stats</button>
      </div>

      <div class="section">
        <h3>Register Event Listener</h3>
        <div class="form-group">
          <input v-model="forms.listener.eventType" type="text" placeholder="Event Type" />
          <input v-model="forms.listener.handlerClass" type="text" placeholder="Handler Class" />
          <input v-model="forms.listener.handlerMethod" type="text" placeholder="Handler Method" />
          <button @click="registerEventListener">Register</button>
        </div>
      </div>

      <div class="section">
        <h3>Create Room</h3>
        <div class="form-group">
          <input v-model="forms.room.roomId" type="text" placeholder="Room ID" />
          <input v-model="forms.room.roomType" type="text" placeholder="Room Type" />
          <input v-model.number="forms.room.maxCapacity" type="number" placeholder="Max Capacity" />
          <button @click="createRoom">Create Room</button>
        </div>
      </div>

      <div class="section">
        <h3>Emit Event</h3>
        <div class="form-group">
          <input v-model="forms.emit.eventType" type="text" placeholder="Event Type" />
          <textarea v-model="forms.emit.data" placeholder="Data (JSON)" rows="3"></textarea>
          <select v-model="forms.emit.broadcastScope">
            <option value="room">Room</option>
            <option value="user">User</option>
            <option value="broadcast">Broadcast</option>
          </select>
          <button @click="emitEvent">Emit Event</button>
        </div>
      </div>

      <div class="section">
        <h3>Connection Throughput</h3>
        <div v-if="gateway.throughput" class="stats-grid">
          <div class="stat">
            <span>Active Connections:</span>
            <strong>{{ gateway.throughput.active_connections }}</strong>
          </div>
          <div class="stat">
            <span>Events/sec:</span>
            <strong>{{ gateway.throughput.events_per_second }}</strong>
          </div>
          <div class="stat">
            <span>Messages/sec:</span>
            <strong>{{ gateway.throughput.messages_per_second }}</strong>
          </div>
        </div>
        <button @click="getConnectionThroughput">Refresh Throughput</button>
      </div>
    </div>

    <!-- Tab 5: Live Demo -->
    <div v-if="activeTab === 'demo'" class="tab-content">
      <h2>Live Demo & Simulation</h2>

      <div class="section">
        <h3>Multi-User Messaging Demo</h3>
        <div class="form-group">
          <input v-model.number="forms.demo.userCount" type="number" placeholder="Number of Users" />
          <input v-model.number="forms.demo.messageCount" type="number" placeholder="Messages per User" />
          <button @click="simulateMultiUserMessaging">Start Simulation</button>
        </div>
        <div v-if="demo.messagingResult" class="result">
          <strong>Messages Sent:</strong> {{ demo.messagingResult.totalMessages }} /
          <strong>Avg Delivery Time:</strong> {{ demo.messagingResult.avgDeliveryTime }}ms
        </div>
      </div>

      <div class="section">
        <h3>Concurrent Editing Conflict Demo</h3>
        <div class="form-group">
          <input v-model="forms.demo.documentId" type="text" placeholder="Document ID" />
          <input v-model.number="forms.demo.concurrentEdits" type="number" placeholder="Concurrent Edits" />
          <button @click="simulateConcurrentEdits">Simulate Conflicts</button>
        </div>
        <div v-if="demo.conflictResult" class="result">
          <strong>Conflicts Detected:</strong> {{ demo.conflictResult.conflictCount }} /
          <strong>Resolution Time:</strong> {{ demo.conflictResult.resolutionTime }}ms
        </div>
      </div>

      <div class="section">
        <h3>Queue Performance Test</h3>
        <div class="form-group">
          <input v-model.number="forms.demo.messageQueueSize" type="number" placeholder="Queue Size" />
          <button @click="simulateQueuePerformance">Test Queue</button>
        </div>
        <div v-if="demo.queueResult" class="result">
          <strong>Throughput:</strong> {{ demo.queueResult.throughput }} msg/s /
          <strong>Avg Latency:</strong> {{ demo.queueResult.avgLatency }}ms
        </div>
      </div>

      <div class="section">
        <h3>WebSocket Load Test</h3>
        <div class="form-group">
          <input v-model.number="forms.demo.connectionCount" type="number" placeholder="Connection Count" />
          <input v-model.number="forms.demo.eventRate" type="number" placeholder="Events per second" />
          <button @click="simulateWebSocketLoad">Start Load Test</button>
        </div>
        <div v-if="demo.loadResult" class="result">
          <strong>Peak Connections:</strong> {{ demo.loadResult.peakConnections }} /
          <strong>Success Rate:</strong> {{ demo.loadResult.successRate }}%
        </div>
      </div>

      <div class="section">
        <h3>End-to-End Flow Demo</h3>
        <div class="form-group">
          <input v-model.number="forms.demo.e2eUsers" type="number" placeholder="Number of Users" />
          <button @click="runE2EDemo">Run E2E Demo</button>
        </div>
        <div v-if="demo.e2eResult" class="result">
          <strong>Total Time:</strong> {{ demo.e2eResult.totalTime }}ms /
          <strong>Success Rate:</strong> {{ demo.e2eResult.successRate }}%
        </div>
      </div>
    </div>

    <!-- Status Messages -->
    <div v-if="status.message" :class="['status-message', status.type]">
      {{ status.message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import * as phase17 from '@/services/phase17'

const activeTab = ref('messaging')
const tabs = ['messaging', 'conflicts', 'queue', 'gateway', 'demo']

const status = reactive({
  message: '',
  type: 'info'
})

const forms = reactive({
  message: {
    fromUserId: 1,
    toUserId: 2,
    messageType: 'text',
    payload: '{}'
  },
  broadcast: {
    fromUserId: 1,
    toUserIds: '2,3,4',
    messageType: 'announcement',
    payload: '{}'
  },
  viewMessages: {
    userId: 1
  },
  operation: {
    userId: 1,
    documentId: 'doc-1',
    operationType: 'insert',
    position: 0,
    content: '',
    length: 0
  },
  detectConflicts: {
    documentId: 'doc-1'
  },
  conflictStats: {
    documentId: 'doc-1'
  },
  operationHistory: {
    documentId: 'doc-1'
  },
  enqueue: {
    messageId: 'msg-1',
    eventType: 'message:sent',
    payload: '{}',
    priority: 5
  },
  processQueue: {
    batchSize: 50
  },
  listener: {
    eventType: 'user:joined',
    handlerClass: 'App\\Handler\\UserJoinedHandler',
    handlerMethod: 'handle'
  },
  room: {
    roomId: 'room-1',
    roomType: 'collaboration',
    maxCapacity: 100
  },
  emit: {
    eventType: 'message:received',
    data: '{}',
    broadcastScope: 'room'
  },
  demo: {
    userCount: 5,
    messageCount: 10,
    documentId: 'doc-demo',
    concurrentEdits: 10,
    messageQueueSize: 1000,
    connectionCount: 100,
    eventRate: 100,
    e2eUsers: 5
  }
})

const stats = reactive({
  messaging: null as any,
  conflicts: null as any,
  queue: null as any,
  gateway: null as any
})

const messages = reactive({
  pending: [] as any[]
})

const conflicts = reactive({
  detected: null as any
})

const history = reactive({
  operations: [] as any[]
})

const queue = reactive({
  processResult: null as any,
  failed: [] as any[]
})

const gateway = reactive({
  throughput: null as any
})

const demo = reactive({
  messagingResult: null as any,
  conflictResult: null as any,
  queueResult: null as any,
  loadResult: null as any,
  e2eResult: null as any
})

function showStatus(message: string, type: string = 'success') {
  status.message = message
  status.type = type
  setTimeout(() => {
    status.message = ''
  }, 3000)
}

// Messaging functions
async function sendMessage() {
  try {
    const payload = JSON.parse(forms.message.payload)
    const result = await phase17.sendMessage(
      forms.message.fromUserId,
      forms.message.toUserId,
      forms.message.messageType,
      payload
    )
    showStatus('Message sent successfully')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function broadcastMessage() {
  try {
    const payload = JSON.parse(forms.broadcast.payload)
    const toUserIds = forms.broadcast.toUserIds
      .split(',')
      .map(id => parseInt(id.trim()))
    const result = await phase17.broadcastMessage(
      forms.broadcast.fromUserId,
      toUserIds,
      forms.broadcast.messageType,
      payload
    )
    showStatus(`Broadcast sent to ${result.recipient_count} users`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function getPendingMessages() {
  try {
    messages.pending = await phase17.getPendingMessages(forms.viewMessages.userId)
    showStatus(`Loaded ${messages.pending.length} pending messages`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function markDelivered(messageId: string) {
  try {
    await phase17.markMessageDelivered(messageId)
    showStatus('Message marked as delivered')
    getPendingMessages()
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function markAcknowledged(messageId: string) {
  try {
    await phase17.markMessageAcknowledged(messageId)
    showStatus('Message marked as acknowledged')
    getPendingMessages()
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function loadMessagingStats() {
  try {
    stats.messaging = await phase17.getMessagingStats()
    showStatus('Messaging stats loaded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

// Conflict functions
async function recordOperation() {
  try {
    const result = await phase17.recordOperation(
      forms.operation.userId,
      forms.operation.documentId,
      forms.operation.operationType as any,
      forms.operation.position,
      forms.operation.content,
      forms.operation.length
    )
    showStatus('Operation recorded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function detectConflicts() {
  try {
    const ops = await phase17.getOperationHistory(forms.detectConflicts.documentId)
    conflicts.detected = await phase17.detectConflicts(
      forms.detectConflicts.documentId,
      ops
    )
    showStatus(`Detected ${conflicts.detected.conflict_count} conflicts`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function getConflictStats() {
  try {
    stats.conflicts = await phase17.getConflictStats(forms.conflictStats.documentId)
    showStatus('Conflict stats loaded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function getOperationHistory() {
  try {
    history.operations = await phase17.getOperationHistory(
      forms.operationHistory.documentId
    )
    showStatus(`Loaded ${history.operations.length} operations`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

// Queue functions
async function enqueueMessage() {
  try {
    const payload = JSON.parse(forms.enqueue.payload)
    await phase17.enqueueMessage(
      forms.enqueue.messageId,
      forms.enqueue.eventType,
      payload,
      forms.enqueue.priority
    )
    showStatus('Message enqueued')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function processQueue() {
  try {
    queue.processResult = await phase17.processQueue(forms.processQueue.batchSize)
    showStatus(`Processed ${queue.processResult.processed} messages`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function loadQueueStats() {
  try {
    stats.queue = await phase17.getQueueStats()
    showStatus('Queue stats loaded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function getFailedMessages() {
  try {
    queue.failed = await phase17.getFailedMessages()
    showStatus(`Loaded ${queue.failed.length} failed messages`)
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function requeueMessage(queueId: string) {
  try {
    await phase17.requeueMessage(queueId)
    showStatus('Message requeued')
    getFailedMessages()
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

// Gateway functions
async function loadGatewayStats() {
  try {
    stats.gateway = await phase17.getGatewayStats()
    showStatus('Gateway stats loaded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function registerEventListener() {
  try {
    await phase17.registerEventListener(
      forms.listener.eventType,
      forms.listener.handlerClass,
      forms.listener.handlerMethod
    )
    showStatus('Event listener registered')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function createRoom() {
  try {
    await phase17.createRoom(
      forms.room.roomId,
      forms.room.roomType,
      forms.room.maxCapacity
    )
    showStatus('Room created')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function emitEvent() {
  try {
    const data = JSON.parse(forms.emit.data)
    await phase17.emitEvent(
      forms.emit.eventType,
      data,
      undefined,
      undefined,
      forms.emit.broadcastScope as any
    )
    showStatus('Event emitted')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function getConnectionThroughput() {
  try {
    gateway.throughput = await phase17.getConnectionThroughput(5)
    showStatus('Throughput data loaded')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

// Demo functions
async function simulateMultiUserMessaging() {
  try {
    const startTime = Date.now()
    let totalTime = 0
    let count = 0

    for (let i = 0; i < forms.demo.userCount; i++) {
      for (let j = 0; j < forms.demo.messageCount; j++) {
        const t1 = Date.now()
        await phase17.sendMessage(
          i + 1,
          ((i + 1) % forms.demo.userCount) + 1,
          'demo:message',
          { content: `Message ${j}` }
        )
        totalTime += Date.now() - t1
        count++
      }
    }

    demo.messagingResult = {
      totalMessages: count,
      avgDeliveryTime: Math.round(totalTime / count)
    }
    showStatus('Multi-user messaging demo completed')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function simulateConcurrentEdits() {
  try {
    const startTime = Date.now()
    const ops: any[] = []

    for (let i = 0; i < forms.demo.concurrentEdits; i++) {
      const op = await phase17.recordOperation(
        (i % 3) + 1,
        forms.demo.documentId,
        i % 2 === 0 ? 'insert' : 'delete',
        Math.floor(Math.random() * 100),
        i % 2 === 0 ? `text${i}` : undefined,
        i % 2 === 0 ? 5 : 3
      )
      ops.push(op)
    }

    const detected = await phase17.detectConflicts(forms.demo.documentId, ops)
    const resolutionTime = Date.now() - startTime

    demo.conflictResult = {
      conflictCount: detected.conflict_count,
      resolutionTime
    }
    showStatus('Concurrent edits simulation completed')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function simulateQueuePerformance() {
  try {
    const startTime = Date.now()
    let processed = 0

    for (let i = 0; i < forms.demo.messageQueueSize; i++) {
      await phase17.enqueueMessage(
        `msg-${i}`,
        `event:${i % 5}`,
        { index: i }
      )
    }

    const queueStats = await phase17.getQueueStats()
    const throughput = forms.demo.messageQueueSize / ((Date.now() - startTime) / 1000)

    demo.queueResult = {
      throughput: Math.round(throughput),
      avgLatency: queueStats.avg_processing_time_seconds * 1000
    }
    showStatus('Queue performance test completed')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function simulateWebSocketLoad() {
  try {
    const startTime = Date.now()
    let successCount = 0

    for (let i = 0; i < forms.demo.connectionCount; i++) {
      try {
        await phase17.registerNamespace(`ns-${i}`)
        successCount++
      } catch {
        // Simulate some failures
      }
    }

    demo.loadResult = {
      peakConnections: forms.demo.connectionCount,
      successRate: Math.round((successCount / forms.demo.connectionCount) * 100)
    }
    showStatus('WebSocket load test completed')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

async function runE2EDemo() {
  try {
    const startTime = Date.now()
    let successCount = 0

    for (let i = 0; i < forms.demo.e2eUsers; i++) {
      try {
        // Send message
        await phase17.sendMessage(i + 1, ((i + 1) % forms.demo.e2eUsers) + 1, 'e2e:test', {})
        // Record operation
        await phase17.recordOperation(i + 1, 'e2e-doc', 'insert', i * 10, 'test', 4)
        // Enqueue
        await phase17.enqueueMessage(`e2e-${i}`, 'e2e:event', {})
        successCount++
      } catch {
        // Track failures
      }
    }

    const totalTime = Date.now() - startTime
    demo.e2eResult = {
      totalTime,
      successRate: Math.round((successCount / forms.demo.e2eUsers) * 100)
    }
    showStatus('E2E demo completed')
  } catch (error: any) {
    showStatus(error.message, 'error')
  }
}

function getTabLabel(tab: string): string {
  const labels: Record<string, string> = {
    messaging: '💬 Messaging',
    conflicts: '⚡ Conflicts',
    queue: '📦 Queue',
    gateway: '🚀 Gateway',
    demo: '🎯 Demo'
  }
  return labels[tab]
}

// Load initial stats
loadMessagingStats()
loadQueueStats()
loadGatewayStats()
</script>

<style scoped lang="css">
.phase17-container {
  padding: 20px;
  max-width: 1400px;
  margin: 0 auto;
}

h1 {
  color: #333;
  margin-bottom: 20px;
  font-size: 28px;
}

h2 {
  color: #555;
  margin-top: 20px;
  margin-bottom: 15px;
  font-size: 22px;
}

h3 {
  color: #777;
  margin-top: 15px;
  margin-bottom: 10px;
  font-size: 16px;
}

.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  flex-wrap: wrap;
}

.tab-button {
  padding: 10px 20px;
  border: 2px solid #ddd;
  background: white;
  cursor: pointer;
  border-radius: 5px;
  font-size: 14px;
  transition: all 0.3s ease;
}

.tab-button:hover {
  border-color: #0084ff;
  color: #0084ff;
}

.tab-button.active {
  border-color: #0084ff;
  background: #0084ff;
  color: white;
}

.tab-content {
  display: none;
  animation: fadeIn 0.3s ease;
}

.tab-content {
  display: block;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.section {
  background: #f8f9fa;
  padding: 20px;
  margin-bottom: 20px;
  border-radius: 8px;
  border: 1px solid #e9ecef;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 15px;
}

input,
select,
textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  font-size: 14px;
}

textarea {
  resize: vertical;
  min-height: 60px;
}

button {
  padding: 10px 20px;
  background: #0084ff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  transition: background 0.3s ease;
}

button:hover {
  background: #0073e6;
}

button:active {
  background: #0063cc;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-bottom: 15px;
}

.stat {
  background: white;
  padding: 15px;
  border-radius: 4px;
  text-align: center;
  border: 1px solid #e9ecef;
}

.stat span {
  display: block;
  color: #777;
  font-size: 12px;
  margin-bottom: 8px;
}

.stat strong {
  display: block;
  font-size: 18px;
  color: #0084ff;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 400px;
  overflow-y: auto;
}

.list-item {
  background: white;
  padding: 12px;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid #e9ecef;
  font-size: 13px;
}

.list-item button {
  padding: 5px 10px;
  font-size: 12px;
  margin-left: 10px;
}

.result {
  background: white;
  padding: 15px;
  border-radius: 4px;
  border-left: 4px solid #28a745;
  margin-top: 10px;
  font-size: 13px;
}

.detection-result {
  margin-top: 10px;
}

.status-message {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 15px 20px;
  border-radius: 4px;
  font-size: 14px;
  animation: slideIn 0.3s ease;
  z-index: 1000;
}

.status-message.success {
  background: #28a745;
  color: white;
}

.status-message.error {
  background: #dc3545;
  color: white;
}

.status-message.info {
  background: #0084ff;
  color: white;
}

@keyframes slideIn {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .tabs {
    gap: 5px;
  }

  .tab-button {
    padding: 8px 12px;
    font-size: 12px;
  }

  .section {
    padding: 15px;
  }

  input,
  select,
  textarea {
    font-size: 13px;
  }
}
</style>
