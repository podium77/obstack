<template>
  <div class="phase16-container">
    <div class="header">
      <h1>Phase 16: WebSocket Server & Presence Management</h1>
      <p class="subtitle">Real-time presence tracking, cursor synchronization, and collaboration indicators</p>
    </div>

    <div class="alerts" v-if="alert.show">
      <div :class="['alert', `alert-${alert.type}`]">
        {{ alert.message }}
      </div>
    </div>

    <div class="tabs">
      <div class="tab-buttons">
        <button 
          v-for="tab in tabs" 
          :key="tab"
          :class="['tab-button', { active: activeTab === tab }]"
          @click="activeTab = tab"
        >
          {{ getTabLabel(tab) }}
        </button>
      </div>

      <div class="tab-content">
        <!-- Tab 1: WebSocket Server Status -->
        <div v-if="activeTab === 'websocket'" class="tab-panel">
          <div class="section">
            <h2>WebSocket Server Status</h2>
            
            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-label">Active Connections</div>
                <div class="stat-value">{{ serverStats.total_active_connections }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Active Rooms</div>
                <div class="stat-value">{{ serverStats.total_rooms }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Active Workspaces</div>
                <div class="stat-value">{{ serverStats.active_workspaces }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Active Documents</div>
                <div class="stat-value">{{ serverStats.active_documents }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Avg Connection Time</div>
                <div class="stat-value">{{ serverStats.avg_connection_time_seconds }}s</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Server Status</div>
                <div class="stat-value" :style="{ color: serverStats.server_status === 'running' ? '#10b981' : '#ef4444' }">
                  {{ serverStats.server_status || 'Unknown' }}
                </div>
              </div>
            </div>

            <div class="form-group">
              <h3>Connection Management</h3>
              <div class="form-row">
                <input v-model="forms.connection.userId" type="number" placeholder="User ID" />
                <input v-model="forms.connection.connectionId" type="text" placeholder="Connection ID" />
                <input v-model="forms.connection.workspaceId" type="text" placeholder="Workspace ID (optional)" />
                <input v-model="forms.connection.documentId" type="text" placeholder="Document ID (optional)" />
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" @click="registerConnection" :disabled="isLoading">
                  {{ isLoading ? 'Loading...' : 'Register Connection' }}
                </button>
                <button class="btn btn-secondary" @click="unregisterConnection" :disabled="isLoading">
                  Unregister Connection
                </button>
                <button class="btn btn-warning" @click="refreshServerStats" :disabled="isLoading">
                  Refresh Stats
                </button>
              </div>
            </div>

            <div class="form-group">
              <h3>Room Management</h3>
              <div class="form-row">
                <input v-model="forms.room.connectionId" type="text" placeholder="Connection ID" />
                <input v-model="forms.room.roomId" type="text" placeholder="Room ID" />
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" @click="subscribeToRoom" :disabled="isLoading">
                  Subscribe to Room
                </button>
                <button class="btn btn-secondary" @click="unsubscribeFromRoom" :disabled="isLoading">
                  Unsubscribe from Room
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 2: User Presence -->
        <div v-if="activeTab === 'presence'" class="tab-panel">
          <div class="section">
            <h2>User Presence Management</h2>

            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-label">Online Users</div>
                <div class="stat-value" style="color: #10b981;">{{ presenceStats.online }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Idle Users</div>
                <div class="stat-value" style="color: #f59e0b;">{{ presenceStats.idle }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Away Users</div>
                <div class="stat-value" style="color: #8b5cf6;">{{ presenceStats.away }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Offline Users</div>
                <div class="stat-value" style="color: #6b7280;">{{ presenceStats.offline }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value">{{ presenceStats.total }}</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Active Workspaces</div>
                <div class="stat-value">{{ presenceStats.active_workspaces }}</div>
              </div>
            </div>

            <div class="form-group">
              <h3>Update Presence</h3>
              <div class="form-row">
                <input v-model="forms.presence.userId" type="number" placeholder="User ID" />
                <select v-model="forms.presence.status">
                  <option value="online">Online</option>
                  <option value="idle">Idle</option>
                  <option value="away">Away</option>
                  <option value="offline">Offline</option>
                </select>
                <input v-model="forms.presence.workspaceId" type="text" placeholder="Workspace ID (optional)" />
                <input v-model="forms.presence.documentId" type="text" placeholder="Document ID (optional)" />
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" @click="updateUserPresence" :disabled="isLoading">
                  {{ isLoading ? 'Loading...' : 'Update Presence' }}
                </button>
                <button class="btn btn-warning" @click="refreshPresenceStats" :disabled="isLoading">
                  Refresh Stats
                </button>
              </div>
            </div>

            <div class="form-group">
              <h3>Get Workspace Users</h3>
              <div class="form-row">
                <input v-model="forms.presence.queryWorkspaceId" type="text" placeholder="Workspace ID" />
              </div>
              <button class="btn btn-secondary" @click="getWorkspaceUsers" :disabled="isLoading">
                Get Online Users
              </button>
              <div v-if="workspaceUsers.length > 0" class="data-list">
                <div v-for="user in workspaceUsers" :key="user.user_id" class="list-item">
                  <strong>{{ user.name }}</strong> ({{ user.status }}) - {{ user.email }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 3: Cursor Tracking -->
        <div v-if="activeTab === 'cursor'" class="tab-panel">
          <div class="section">
            <h2>Real-time Cursor Tracking</h2>

            <div class="form-group">
              <h3>Update Cursor Position</h3>
              <div class="form-row">
                <input v-model="forms.cursor.userId" type="number" placeholder="User ID" />
                <input v-model="forms.cursor.documentId" type="text" placeholder="Document ID" />
                <input v-model="forms.cursor.line" type="number" placeholder="Line" />
                <input v-model="forms.cursor.column" type="number" placeholder="Column" />
              </div>
              <div class="form-row">
                <input v-model="forms.cursor.selectionStartLine" type="number" placeholder="Selection Start Line (optional)" />
                <input v-model="forms.cursor.selectionStartColumn" type="number" placeholder="Selection Start Column (optional)" />
                <input v-model="forms.cursor.selectionEndLine" type="number" placeholder="Selection End Line (optional)" />
                <input v-model="forms.cursor.selectionEndColumn" type="number" placeholder="Selection End Column (optional)" />
              </div>
              <button class="btn btn-primary" @click="updateCursorPosition" :disabled="isLoading">
                {{ isLoading ? 'Loading...' : 'Update Cursor' }}
              </button>
            </div>

            <div class="form-group">
              <h3>View Document Cursors</h3>
              <div class="form-row">
                <input v-model="forms.cursor.queryDocumentId" type="text" placeholder="Document ID" />
              </div>
              <button class="btn btn-secondary" @click="getDocumentCursors" :disabled="isLoading">
                Get Document Cursors
              </button>
              <div v-if="documentCursors.length > 0" class="data-list">
                <div v-for="cursor in documentCursors" :key="cursor.user_id" class="list-item cursor-item">
                  <strong>{{ cursor.user_name }}</strong><br>
                  Cursor: {{ cursor.cursor.line }}:{{ cursor.cursor.column }}
                  <span v-if="cursor.selection">(Selection: {{ cursor.selection.start.line }}:{{ cursor.selection.start.column }} - {{ cursor.selection.end.line }}:{{ cursor.selection.end.column }})</span>
                </div>
              </div>
            </div>

            <div class="form-group">
              <h3>Cursor Statistics</h3>
              <div class="form-row">
                <input v-model="forms.cursor.statsDocumentId" type="text" placeholder="Document ID" />
              </div>
              <button class="btn btn-secondary" @click="getCursorStats" :disabled="isLoading">
                Get Cursor Stats
              </button>
              <div v-if="cursorStats.active_cursors !== undefined" class="stats-grid">
                <div class="stat-card">
                  <div class="stat-label">Active Cursors</div>
                  <div class="stat-value">{{ cursorStats.active_cursors }}</div>
                </div>
                <div class="stat-card">
                  <div class="stat-label">Unique Users</div>
                  <div class="stat-value">{{ cursorStats.unique_users }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 4: Collaboration Indicators -->
        <div v-if="activeTab === 'collaboration'" class="tab-panel">
          <div class="section">
            <h2>Live Collaboration Indicators</h2>

            <div class="form-group">
              <h3>Register Editor/Viewer</h3>
              <div class="form-row">
                <input v-model="forms.collaboration.userId" type="number" placeholder="User ID" />
                <input v-model="forms.collaboration.documentId" type="text" placeholder="Document ID" />
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" @click="registerEditor" :disabled="isLoading">
                  Register as Editor
                </button>
                <button class="btn btn-secondary" @click="registerViewer" :disabled="isLoading">
                  Register as Viewer
                </button>
              </div>
            </div>

            <div class="form-group">
              <h3>Record Edit Activity</h3>
              <div class="form-row">
                <input v-model="forms.collaboration.editUserId" type="number" placeholder="User ID" />
                <input v-model="forms.collaboration.editDocumentId" type="text" placeholder="Document ID" />
                <select v-model="forms.collaboration.changeType">
                  <option value="modify">Modify</option>
                  <option value="delete">Delete</option>
                  <option value="insert">Insert</option>
                  <option value="format">Format</option>
                </select>
              </div>
              <button class="btn btn-primary" @click="recordEdit" :disabled="isLoading">
                Record Edit
              </button>
            </div>

            <div class="form-group">
              <h3>View Collaboration Stats</h3>
              <div class="form-row">
                <input v-model="forms.collaboration.statsDocumentId" type="text" placeholder="Document ID" />
              </div>
              <button class="btn btn-secondary" @click="getCollaborationStats" :disabled="isLoading">
                Get Stats
              </button>
              <div v-if="collaborationStats.active_editors !== undefined">
                <div class="stats-grid">
                  <div class="stat-card">
                    <div class="stat-label">Active Editors</div>
                    <div class="stat-value">{{ collaborationStats.active_editors }}</div>
                  </div>
                  <div class="stat-card">
                    <div class="stat-label">Active Viewers</div>
                    <div class="stat-value">{{ collaborationStats.active_viewers }}</div>
                  </div>
                  <div class="stat-card">
                    <div class="stat-label">Total Participants</div>
                    <div class="stat-value">{{ collaborationStats.total_participants }}</div>
                  </div>
                  <div class="stat-card">
                    <div class="stat-label">Recent Edits</div>
                    <div class="stat-value">{{ collaborationStats.recent_edits }}</div>
                  </div>
                </div>
                <h4>Editors:</h4>
                <div class="data-list">
                  <div v-for="editor in collaborationStats.editors" :key="editor.user_id" class="list-item">
                    <strong>{{ editor.user_name }}</strong> - {{ editor.edit_count || 0 }} edits
                  </div>
                </div>
                <h4>Viewers:</h4>
                <div class="data-list">
                  <div v-for="viewer in collaborationStats.viewers" :key="viewer.user_id" class="list-item">
                    <strong>{{ viewer.user_name }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 5: Typing Indicators -->
        <div v-if="activeTab === 'typing'" class="tab-panel">
          <div class="section">
            <h2>Typing Notifications</h2>

            <div class="form-group">
              <h3>Record Typing Activity</h3>
              <div class="form-row">
                <input v-model="forms.typing.userId" type="number" placeholder="User ID" />
                <input v-model="forms.typing.documentId" type="text" placeholder="Document ID" />
                <input v-model="forms.typing.line" type="number" placeholder="Line (optional)" />
                <input v-model="forms.typing.column" type="number" placeholder="Column (optional)" />
              </div>
              <div class="form-row">
                <input v-model="forms.typing.charactersAdded" type="number" placeholder="Characters Added" value="1" />
              </div>
              <div class="form-actions">
                <button class="btn btn-primary" @click="recordTyping" :disabled="isLoading">
                  Record Typing
                </button>
                <button class="btn btn-secondary" @click="recordStoppedTyping" :disabled="isLoading">
                  Stop Typing
                </button>
              </div>
            </div>

            <div class="form-group">
              <h3>View Typing Users</h3>
              <div class="form-row">
                <input v-model="forms.typing.queryDocumentId" type="text" placeholder="Document ID" />
              </div>
              <button class="btn btn-secondary" @click="getTypingUsers" :disabled="isLoading">
                Get Typing Users
              </button>
              <div v-if="typingUsers.length > 0" class="data-list">
                <div v-for="user in typingUsers" :key="user.user_id" class="list-item">
                  <strong>{{ user.user_name }}</strong> is typing... ({{ user.characters_typed }} chars)
                </div>
              </div>
              <div v-else-if="typingUsersQueried" class="info-message">
                No users currently typing in this document
              </div>
            </div>

            <div class="form-group">
              <h3>Typing Statistics</h3>
              <div class="form-row">
                <input v-model="forms.typing.statsDocumentId" type="text" placeholder="Document ID" />
              </div>
              <button class="btn btn-secondary" @click="getTypingStats" :disabled="isLoading">
                Get Typing Stats
              </button>
              <div v-if="typingStats.users_typing !== undefined" class="stats-grid">
                <div class="stat-card">
                  <div class="stat-label">Users Typing</div>
                  <div class="stat-value">{{ typingStats.users_typing }}</div>
                </div>
                <div class="stat-card">
                  <div class="stat-label">Total Characters</div>
                  <div class="stat-value">{{ typingStats.total_characters_typed }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 6: Live Demo -->
        <div v-if="activeTab === 'demo'" class="tab-panel">
          <div class="section">
            <h2>Interactive Live Demo</h2>
            <p class="subtitle">Simulate real-time collaboration between multiple users</p>

            <div class="demo-container">
              <div class="demo-section">
                <h3>Simulated Users</h3>
                <div class="user-simulator">
                  <div class="form-row">
                    <input v-model="demo.userId1" type="number" placeholder="User 1 ID" />
                    <input v-model="demo.userId2" type="number" placeholder="User 2 ID" />
                    <input v-model="demo.userId3" type="number" placeholder="User 3 ID" />
                  </div>
                  <div class="form-row">
                    <input v-model="demo.demoDocumentId" type="text" placeholder="Document ID" />
                  </div>
                </div>
              </div>

              <div class="demo-section">
                <h3>Simulate Collaboration</h3>
                <div class="demo-actions">
                  <button class="btn btn-primary" @click="simulateMultiUserPresence" :disabled="isLoading">
                    Simulate Multi-User Online
                  </button>
                  <button class="btn btn-primary" @click="simulateCursorMovement" :disabled="isLoading">
                    Simulate Cursor Movement
                  </button>
                  <button class="btn btn-primary" @click="simulateMultiUserTyping" :disabled="isLoading">
                    Simulate Multi-User Typing
                  </button>
                  <button class="btn btn-primary" @click="simulateCollaborativeEditing" :disabled="isLoading">
                    Simulate Collaborative Editing
                  </button>
                </div>
              </div>

              <div class="demo-section">
                <h3>Demo Results</h3>
                <div class="demo-results">
                  <div v-if="demoResults.length === 0" class="info-message">
                    Click the buttons above to simulate real-time collaboration scenarios
                  </div>
                  <div v-for="(result, index) in demoResults" :key="index" class="demo-result-item">
                    <strong>{{ result.title }}</strong><br>
                    <small>{{ result.description }}</small>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue';
import * as Phase16 from '@/services/phase16';

// Tabs
const tabs = ['websocket', 'presence', 'cursor', 'collaboration', 'typing', 'demo'];
const activeTab = ref('websocket');

// Alert system
const alert = reactive({
  show: false,
  message: '',
  type: 'success'
});

const showAlert = (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') => {
  alert.message = message;
  alert.type = type;
  alert.show = true;
  setTimeout(() => {
    alert.show = false;
  }, 5000);
};

// Loading state
const isLoading = ref(false);

// Server stats
const serverStats = reactive({
  total_active_connections: 0,
  total_rooms: 0,
  active_workspaces: 0,
  active_documents: 0,
  avg_connection_time_seconds: 0,
  server_status: 'unknown'
});

// Presence stats
const presenceStats = reactive({
  online: 0,
  idle: 0,
  away: 0,
  offline: 0,
  total: 0,
  active_workspaces: 0
});

// Document cursors
const documentCursors = ref([]);
const cursorStats = reactive({
  active_cursors: undefined,
  unique_users: undefined
});

// Collaboration stats
const collaborationStats = reactive({
  active_editors: undefined,
  active_viewers: undefined,
  total_participants: undefined,
  recent_edits: undefined,
  editors: [],
  viewers: []
});

// Typing data
const typingUsers = ref([]);
const typingUsersQueried = ref(false);
const typingStats = reactive({
  users_typing: undefined,
  total_characters_typed: undefined
});

// Workspace users
const workspaceUsers = ref([]);

// Demo
const demoResults = ref([]);

// Forms
const forms = reactive({
  connection: {
    userId: 1,
    connectionId: 'conn_' + Math.random().toString(36).substr(2, 9),
    workspaceId: '',
    documentId: ''
  },
  room: {
    connectionId: '',
    roomId: ''
  },
  presence: {
    userId: 1,
    status: 'online',
    workspaceId: '',
    documentId: '',
    queryWorkspaceId: ''
  },
  cursor: {
    userId: 1,
    documentId: 'doc_123',
    line: 1,
    column: 0,
    selectionStartLine: null,
    selectionStartColumn: null,
    selectionEndLine: null,
    selectionEndColumn: null,
    queryDocumentId: 'doc_123',
    statsDocumentId: 'doc_123'
  },
  collaboration: {
    userId: 1,
    documentId: 'doc_123',
    editUserId: 1,
    editDocumentId: 'doc_123',
    changeType: 'modify',
    statsDocumentId: 'doc_123'
  },
  typing: {
    userId: 1,
    documentId: 'doc_123',
    line: null,
    column: null,
    charactersAdded: 1,
    queryDocumentId: 'doc_123',
    statsDocumentId: 'doc_123'
  }
});

const demo = reactive({
  userId1: 1,
  userId2: 2,
  userId3: 3,
  demoDocumentId: 'demo_doc_123'
});

// Functions
const getTabLabel = (tab: string): string => {
  const labels: Record<string, string> = {
    websocket: '🔌 WebSocket Server',
    presence: '👥 Presence',
    cursor: '📍 Cursor Tracking',
    collaboration: '🤝 Collaboration',
    typing: '⌨️ Typing',
    demo: '🎮 Live Demo'
  };
  return labels[tab] || tab;
};

// WebSocket functions
const registerConnection = async () => {
  isLoading.value = true;
  try {
    await Phase16.registerConnection(
      forms.connection.userId,
      forms.connection.connectionId,
      forms.connection.workspaceId || undefined,
      forms.connection.documentId || undefined
    );
    showAlert('Connection registered successfully', 'success');
    refreshServerStats();
  } catch (error: any) {
    showAlert(error.message || 'Failed to register connection', 'error');
  } finally {
    isLoading.value = false;
  }
};

const unregisterConnection = async () => {
  isLoading.value = true;
  try {
    await Phase16.unregisterConnection(forms.connection.connectionId);
    showAlert('Connection unregistered successfully', 'success');
    refreshServerStats();
  } catch (error: any) {
    showAlert(error.message || 'Failed to unregister connection', 'error');
  } finally {
    isLoading.value = false;
  }
};

const subscribeToRoom = async () => {
  isLoading.value = true;
  try {
    await Phase16.subscribeToRoom(forms.room.connectionId, forms.room.roomId);
    showAlert('Subscribed to room successfully', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to subscribe to room', 'error');
  } finally {
    isLoading.value = false;
  }
};

const unsubscribeFromRoom = async () => {
  isLoading.value = true;
  try {
    await Phase16.unsubscribeFromRoom(forms.room.connectionId, forms.room.roomId);
    showAlert('Unsubscribed from room successfully', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to unsubscribe from room', 'error');
  } finally {
    isLoading.value = false;
  }
};

const refreshServerStats = async () => {
  isLoading.value = true;
  try {
    const stats = await Phase16.getServerStats();
    Object.assign(serverStats, stats);
  } catch (error: any) {
    showAlert(error.message || 'Failed to refresh server stats', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Presence functions
const updateUserPresence = async () => {
  isLoading.value = true;
  try {
    await Phase16.updatePresence(
      forms.presence.userId,
      forms.presence.status as any,
      forms.presence.workspaceId || undefined,
      forms.presence.documentId || undefined
    );
    showAlert('Presence updated successfully', 'success');
    refreshPresenceStats();
  } catch (error: any) {
    showAlert(error.message || 'Failed to update presence', 'error');
  } finally {
    isLoading.value = false;
  }
};

const refreshPresenceStats = async () => {
  isLoading.value = true;
  try {
    const stats = await Phase16.getPresenceStats();
    Object.assign(presenceStats, stats);
  } catch (error: any) {
    showAlert(error.message || 'Failed to refresh presence stats', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getWorkspaceUsers = async () => {
  isLoading.value = true;
  try {
    const users = await Phase16.getWorkspaceOnlineUsers(forms.presence.queryWorkspaceId);
    workspaceUsers.value = users;
    showAlert(`Found ${users.length} online users`, 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get workspace users', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Cursor functions
const updateCursorPosition = async () => {
  isLoading.value = true;
  try {
    await Phase16.updateCursorPosition(
      forms.cursor.userId,
      forms.cursor.documentId,
      forms.cursor.line,
      forms.cursor.column,
      forms.cursor.selectionStartLine || undefined,
      forms.cursor.selectionStartColumn || undefined,
      forms.cursor.selectionEndLine || undefined,
      forms.cursor.selectionEndColumn || undefined
    );
    showAlert('Cursor position updated', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to update cursor position', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getDocumentCursors = async () => {
  isLoading.value = true;
  try {
    const cursors = await Phase16.getDocumentCursors(forms.cursor.queryDocumentId);
    documentCursors.value = cursors;
    showAlert(`Found ${cursors.length} active cursors`, 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get document cursors', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getCursorStats = async () => {
  isLoading.value = true;
  try {
    const stats = await Phase16.getCursorStats(forms.cursor.statsDocumentId);
    Object.assign(cursorStats, stats);
    showAlert('Cursor stats retrieved', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get cursor stats', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Collaboration functions
const registerEditor = async () => {
  isLoading.value = true;
  try {
    await Phase16.registerEditor(forms.collaboration.userId, forms.collaboration.documentId);
    showAlert('Editor registered successfully', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to register editor', 'error');
  } finally {
    isLoading.value = false;
  }
};

const registerViewer = async () => {
  isLoading.value = true;
  try {
    await Phase16.registerViewer(forms.collaboration.userId, forms.collaboration.documentId);
    showAlert('Viewer registered successfully', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to register viewer', 'error');
  } finally {
    isLoading.value = false;
  }
};

const recordEdit = async () => {
  isLoading.value = true;
  try {
    await Phase16.recordEdit(forms.collaboration.editUserId, forms.collaboration.editDocumentId, forms.collaboration.changeType);
    showAlert('Edit recorded successfully', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to record edit', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getCollaborationStats = async () => {
  isLoading.value = true;
  try {
    const stats = await Phase16.getCollaborationStats(forms.collaboration.statsDocumentId);
    Object.assign(collaborationStats, stats);
    showAlert('Collaboration stats retrieved', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get collaboration stats', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Typing functions
const recordTyping = async () => {
  isLoading.value = true;
  try {
    await Phase16.recordTyping(
      forms.typing.userId,
      forms.typing.documentId,
      forms.typing.line || undefined,
      forms.typing.column || undefined,
      forms.typing.charactersAdded
    );
    showAlert('Typing recorded', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to record typing', 'error');
  } finally {
    isLoading.value = false;
  }
};

const recordStoppedTyping = async () => {
  isLoading.value = true;
  try {
    await Phase16.recordStoppedTyping(forms.typing.userId, forms.typing.documentId);
    showAlert('Typing stopped', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to stop typing', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getTypingUsers = async () => {
  isLoading.value = true;
  typingUsersQueried.value = true;
  try {
    const users = await Phase16.getTypingUsers(forms.typing.queryDocumentId);
    typingUsers.value = users;
    showAlert(`Found ${users.length} typing users`, 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get typing users', 'error');
  } finally {
    isLoading.value = false;
  }
};

const getTypingStats = async () => {
  isLoading.value = true;
  try {
    const stats = await Phase16.getTypingStats(forms.typing.statsDocumentId);
    Object.assign(typingStats, stats);
    showAlert('Typing stats retrieved', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to get typing stats', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Demo functions
const simulateMultiUserPresence = async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      Phase16.updatePresence(demo.userId1, 'online', '', demo.demoDocumentId),
      Phase16.updatePresence(demo.userId2, 'online', '', demo.demoDocumentId),
      Phase16.updatePresence(demo.userId3, 'online', '', demo.demoDocumentId)
    ]);
    demoResults.value.push({
      title: 'Multi-User Presence',
      description: `Users ${demo.userId1}, ${demo.userId2}, ${demo.userId3} are now online in document ${demo.demoDocumentId}`
    });
    showAlert('Multi-user presence simulated', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to simulate presence', 'error');
  } finally {
    isLoading.value = false;
  }
};

const simulateCursorMovement = async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      Phase16.updateCursorPosition(demo.userId1, demo.demoDocumentId, 1, 0),
      Phase16.updateCursorPosition(demo.userId2, demo.demoDocumentId, 5, 10),
      Phase16.updateCursorPosition(demo.userId3, demo.demoDocumentId, 10, 25)
    ]);
    demoResults.value.push({
      title: 'Cursor Movement',
      description: 'All users moving cursors to different positions simultaneously'
    });
    showAlert('Cursor movement simulated', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to simulate cursor movement', 'error');
  } finally {
    isLoading.value = false;
  }
};

const simulateMultiUserTyping = async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      Phase16.recordTyping(demo.userId1, demo.demoDocumentId, 1, 0, 5),
      Phase16.recordTyping(demo.userId2, demo.demoDocumentId, 5, 10, 8),
      Phase16.recordTyping(demo.userId3, demo.demoDocumentId, 10, 25, 3)
    ]);
    demoResults.value.push({
      title: 'Multi-User Typing',
      description: 'Three users typing simultaneously with different character counts'
    });
    showAlert('Multi-user typing simulated', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to simulate typing', 'error');
  } finally {
    isLoading.value = false;
  }
};

const simulateCollaborativeEditing = async () => {
  isLoading.value = true;
  try {
    await Promise.all([
      Phase16.registerEditor(demo.userId1, demo.demoDocumentId),
      Phase16.registerEditor(demo.userId2, demo.demoDocumentId),
      Phase16.registerViewer(demo.userId3, demo.demoDocumentId),
      Phase16.recordEdit(demo.userId1, demo.demoDocumentId, 'insert'),
      Phase16.recordEdit(demo.userId2, demo.demoDocumentId, 'modify')
    ]);
    demoResults.value.push({
      title: 'Collaborative Editing',
      description: '2 editors and 1 viewer all active, with concurrent edits being tracked'
    });
    showAlert('Collaborative editing simulated', 'success');
  } catch (error: any) {
    showAlert(error.message || 'Failed to simulate collaborative editing', 'error');
  } finally {
    isLoading.value = false;
  }
};

// Initialize
refreshServerStats();
refreshPresenceStats();
</script>

<style scoped>
.phase16-container {
  padding: 20px;
  background: #f8f9fa;
  min-height: 100vh;
}

.header {
  margin-bottom: 30px;
}

.header h1 {
  font-size: 28px;
  font-weight: bold;
  color: #111;
  margin: 0 0 10px 0;
}

.subtitle {
  color: #666;
  margin: 0;
  font-size: 14px;
}

.alerts {
  margin-bottom: 20px;
}

.alert {
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 10px;
}

.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.alert-error {
  background: #fee2e2;
  color: #7f1d1d;
  border: 1px solid #fecaca;
}

.alert-info {
  background: #dbeafe;
  color: #0c2340;
  border: 1px solid #bfdbfe;
}

.alert-warning {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
}

.tabs {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.tab-buttons {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  overflow-x: auto;
}

.tab-button {
  padding: 15px 20px;
  background: none;
  border: none;
  cursor: pointer;
  color: #666;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
  transition: all 0.3s;
  border-bottom: 3px solid transparent;
}

.tab-button:hover {
  color: #111;
  background: #f9fafb;
}

.tab-button.active {
  color: #0066cc;
  border-bottom-color: #0066cc;
}

.tab-content {
  padding: 20px;
}

.tab-panel {
  display: block;
}

.section {
  margin-bottom: 30px;
}

.section h2 {
  font-size: 20px;
  font-weight: bold;
  color: #111;
  margin-bottom: 20px;
}

.section h3 {
  font-size: 16px;
  font-weight: 600;
  color: #333;
  margin-bottom: 12px;
  margin-top: 20px;
}

.section h4 {
  font-size: 14px;
  font-weight: 600;
  color: #333;
  margin-bottom: 10px;
  margin-top: 15px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.stat-card {
  background: #f9fafb;
  padding: 15px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  text-align: center;
}

.stat-label {
  font-size: 12px;
  color: #666;
  font-weight: 500;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  color: #0066cc;
}

.form-group {
  background: #f9fafb;
  padding: 15px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  margin-bottom: 15px;
}

.form-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.form-row input,
.form-row select {
  flex: 1;
  min-width: 120px;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 14px;
}

.form-row input:focus,
.form-row select:focus {
  outline: none;
  border-color: #0066cc;
  box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
}

.form-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.btn {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #0066cc;
  color: white;
}

.btn-primary:hover {
  background: #0052a3;
}

.btn-primary:disabled {
  background: #d1d5db;
  cursor: not-allowed;
}

.btn-secondary {
  background: #e5e7eb;
  color: #111;
}

.btn-secondary:hover {
  background: #d1d5db;
}

.btn-secondary:disabled {
  background: #f3f4f6;
  cursor: not-allowed;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover {
  background: #d97706;
}

.data-list {
  margin-top: 10px;
}

.list-item {
  padding: 10px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  margin-bottom: 8px;
  font-size: 14px;
}

.list-item strong {
  color: #0066cc;
}

.list-item.cursor-item {
  font-family: monospace;
  font-size: 13px;
}

.info-message {
  padding: 10px;
  background: #dbeafe;
  border: 1px solid #bfdbfe;
  color: #0c2340;
  border-radius: 4px;
  font-size: 14px;
}

.demo-container {
  background: #f9fafb;
  padding: 20px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
}

.demo-section {
  margin-bottom: 20px;
}

.demo-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.demo-results {
  background: white;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  margin-top: 15px;
}

.demo-result-item {
  padding: 10px;
  border-left: 3px solid #0066cc;
  background: #f0f7ff;
  margin-bottom: 10px;
  border-radius: 2px;
}

.demo-result-item strong {
  color: #0066cc;
}

.demo-result-item small {
  color: #666;
  display: block;
  margin-top: 5px;
}

.user-simulator {
  background: white;
  padding: 12px;
  border-radius: 4px;
  border: 1px solid #e5e7eb;
  margin-bottom: 12px;
}
</style>
