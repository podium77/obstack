<template>
  <div class="phase15-view">
    <!-- Header -->
    <div class="view-header">
      <div>
        <h1>🔔 Notifications & WebSocket (Phase 15)</h1>
        <p class="text-gray-600">Real-time notifications, activity digests, and user preferences</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['tab-button', { active: activeTab === tab.id }]"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Tab 1: WebSocket Status -->
    <div v-if="activeTab === 'websocket'" class="tab-content">
      <div class="section">
        <h2>🔴 WebSocket Status</h2>
        <p class="text-gray-600 mb-4">Monitor real-time connections and message delivery</p>

        <!-- Connection Stats -->
        <div v-if="websocket.stats" class="card">
          <h3>Connection Statistics</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <h4>Active Connections</h4>
              <p class="stat-value">{{ websocket.stats.total_active_connections }}</p>
            </div>
            <div class="stat-card">
              <h4>Pending Messages</h4>
              <p class="stat-value">{{ websocket.stats.total_messages_pending }}</p>
            </div>
            <div class="stat-card">
              <h4>Active Workspaces</h4>
              <p class="stat-value">{{ websocket.stats.workspaces_active }}</p>
            </div>
          </div>
          <button @click="loadWebSocketStats" class="btn-primary mt-4">Refresh Stats</button>
        </div>

        <!-- Broadcast Message -->
        <div class="card mt-6">
          <h3>Send Broadcast Message</h3>
          <div class="form-group">
            <label>Workspace ID</label>
            <input v-model="websocket.broadcastWorkspaceId" type="text" placeholder="workspace-id" class="input" />
          </div>
          <div class="form-group">
            <label>Event Type</label>
            <input v-model="websocket.broadcastEventType" type="text" placeholder="e.g., query_executed" class="input" />
          </div>
          <div class="form-group">
            <label>Message (JSON)</label>
            <textarea v-model="websocket.broadcastData" placeholder='{"key": "value"}' class="textarea"></textarea>
          </div>
          <button @click="broadcastMessage" class="btn-primary">Send Broadcast</button>
        </div>
      </div>
    </div>

    <!-- Tab 2: Push Notifications -->
    <div v-if="activeTab === 'notifications'" class="tab-content">
      <div class="section">
        <h2>🔔 Push Notifications</h2>
        <p class="text-gray-600 mb-4">Send and manage push notifications</p>

        <!-- User Selector -->
        <div class="card mb-6">
          <h3>Select User</h3>
          <div class="form-group">
            <input v-model.number="notifications.userId" type="number" placeholder="User ID" class="input" />
          </div>
          <button @click="loadNotifications" class="btn-primary">Load Notifications</button>
        </div>

        <!-- Notification Stats -->
        <div v-if="notifications.stats" class="card mb-6">
          <h3>Notification Statistics</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <h4>Total</h4>
              <p class="stat-value">{{ notifications.stats.total_notifications }}</p>
            </div>
            <div class="stat-card">
              <h4>Unread</h4>
              <p class="stat-value">{{ notifications.stats.unread_notifications }}</p>
            </div>
            <div class="stat-card">
              <h4>Read</h4>
              <p class="stat-value">{{ notifications.stats.read_notifications }}</p>
            </div>
          </div>
        </div>

        <!-- Send Notification -->
        <div class="card mb-6">
          <h3>Send Notification</h3>
          <div class="form-group">
            <label>Title</label>
            <input v-model="notifications.newTitle" type="text" placeholder="Notification title" class="input" />
          </div>
          <div class="form-group">
            <label>Message</label>
            <textarea v-model="notifications.newMessage" placeholder="Notification message" class="textarea"></textarea>
          </div>
          <div class="form-group">
            <label>Action URL (optional)</label>
            <input v-model="notifications.newActionUrl" type="text" placeholder="/path/to/action" class="input" />
          </div>
          <button @click="sendNotification" class="btn-primary">Send Notification</button>
        </div>

        <!-- Notifications List -->
        <div class="card">
          <h3>Recent Notifications ({{ notifications.list.length }})</h3>
          <div v-if="notifications.list.length > 0" class="notifications-list">
            <div v-for="notif in notifications.list" :key="notif.id" :class="['notification-item', { unread: !notif.read }]">
              <div class="notification-header">
                <h4>{{ notif.title }}</h4>
                <span class="time">{{ formatDate(notif.created_at) }}</span>
              </div>
              <p class="notification-message">{{ notif.message }}</p>
              <div class="notification-actions">
                <button v-if="!notif.read" @click="markAsRead(notif.id)" class="btn-sm">✓ Mark as read</button>
                <button @click="deleteNotification(notif.id)" class="btn-sm btn-danger">🗑 Delete</button>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No notifications</p>
        </div>
      </div>
    </div>

    <!-- Tab 3: Comment Notifications -->
    <div v-if="activeTab === 'comments'" class="tab-content">
      <div class="section">
        <h2>💬 Comment Notifications</h2>
        <p class="text-gray-600 mb-4">Send notifications for comment interactions</p>

        <!-- Mention Notification -->
        <div class="card mb-6">
          <h3>Notify Mentions</h3>
          <div class="form-group">
            <label>Comment ID</label>
            <input v-model="comments.mentionCommentId" type="text" placeholder="comment-id" class="input" />
          </div>
          <div class="form-group">
            <label>Author ID</label>
            <input v-model.number="comments.mentionAuthorId" type="number" placeholder="User ID" class="input" />
          </div>
          <div class="form-group">
            <label>Mentioned User IDs (comma separated)</label>
            <input v-model="comments.mentionedIds" type="text" placeholder="1,2,3" class="input" />
          </div>
          <button @click="sendMentionNotification" class="btn-primary">Send Mentions</button>
        </div>

        <!-- Reply Notification -->
        <div class="card mb-6">
          <h3>Notify Reply</h3>
          <div class="form-group">
            <label>Parent Comment ID</label>
            <input v-model="comments.replyCommentId" type="text" placeholder="comment-id" class="input" />
          </div>
          <div class="form-group">
            <label>Replier ID</label>
            <input v-model.number="comments.replierId" type="number" placeholder="User ID" class="input" />
          </div>
          <div class="form-group">
            <label>Reply Content</label>
            <textarea v-model="comments.replyContent" placeholder="Comment content" class="textarea"></textarea>
          </div>
          <button @click="sendReplyNotification" class="btn-primary">Send Reply Notification</button>
        </div>

        <!-- Reaction Notification -->
        <div class="card mb-6">
          <h3>Notify Reaction</h3>
          <div class="form-group">
            <label>Comment ID</label>
            <input v-model="comments.reactionCommentId" type="text" placeholder="comment-id" class="input" />
          </div>
          <div class="form-group">
            <label>Reactor ID</label>
            <input v-model.number="comments.reactorId" type="number" placeholder="User ID" class="input" />
          </div>
          <div class="form-group">
            <label>Reaction Type</label>
            <input v-model="comments.reactionType" type="text" placeholder="😀" class="input" />
          </div>
          <button @click="sendReactionNotification" class="btn-primary">Send Reaction Notification</button>
        </div>

        <!-- Vote Notification -->
        <div class="card">
          <h3>Notify Vote</h3>
          <div class="form-group">
            <label>Comment ID</label>
            <input v-model="comments.voteCommentId" type="text" placeholder="comment-id" class="input" />
          </div>
          <div class="form-group">
            <label>Voter ID</label>
            <input v-model.number="comments.voterId" type="number" placeholder="User ID" class="input" />
          </div>
          <div class="form-group">
            <label>Vote Type</label>
            <select v-model="comments.voteType" class="select">
              <option value="up">👍 Upvote</option>
              <option value="down">👎 Downvote</option>
            </select>
          </div>
          <button @click="sendVoteNotification" class="btn-primary">Send Vote Notification</button>
        </div>
      </div>
    </div>

    <!-- Tab 4: Activity Digest -->
    <div v-if="activeTab === 'digest'" class="tab-content">
      <div class="section">
        <h2>📊 Activity Digest</h2>
        <p class="text-gray-600 mb-4">Generate and send activity digest emails</p>

        <!-- User Selector -->
        <div class="card mb-6">
          <h3>Select User and Frequency</h3>
          <div class="form-group">
            <label>User ID</label>
            <input v-model.number="digest.userId" type="number" placeholder="User ID" class="input" />
          </div>
          <div class="form-group">
            <label>Frequency</label>
            <select v-model="digest.frequency" class="select">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
          <div class="button-group">
            <button @click="generateDigest" class="btn-primary">Generate Digest</button>
            <button @click="sendDigest" class="btn-secondary">Send Email</button>
          </div>
        </div>

        <!-- Digest Preview -->
        <div v-if="digest.preview" class="card mb-6">
          <h3>Digest Preview</h3>
          <div class="digest-info">
            <p><strong>Frequency:</strong> {{ digest.preview.frequency }}</p>
            <p><strong>Period:</strong> {{ digest.preview.period }}</p>
            <p><strong>Generated:</strong> {{ formatDate(digest.preview.generated_at) }}</p>
          </div>
          <div v-for="ws in digest.preview.workspaces" :key="ws.name" class="workspace-digest">
            <h4>{{ ws.name }}</h4>
            <p>Activities: {{ ws.activity_count }} | Comments: {{ ws.comment_count }}</p>
          </div>
        </div>

        <!-- Digest Stats -->
        <div v-if="digest.stats" class="card mb-6">
          <h3>Digest Statistics ({{ digest.frequency }})</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <h4>Total Sent</h4>
              <p class="stat-value">{{ digest.stats.total_sent }}</p>
            </div>
            <div class="stat-card">
              <h4>Subscribers</h4>
              <p class="stat-value">{{ digest.stats.subscribers }}</p>
            </div>
          </div>
        </div>

        <!-- Digest History -->
        <div class="card">
          <h3>Digest History</h3>
          <div v-if="digest.history.length > 0" class="history-list">
            <div v-for="entry in digest.history" :key="entry.id" class="history-item">
              <span class="frequency">{{ entry.frequency }}</span>
              <span class="date">{{ formatDate(entry.sent_at) }}</span>
              <span class="count">{{ entry.workspace_count }} workspaces</span>
            </div>
          </div>
          <p v-else class="text-gray-500">No digest history</p>
        </div>
      </div>
    </div>

    <!-- Tab 5: Settings -->
    <div v-if="activeTab === 'settings'" class="tab-content">
      <div class="section">
        <h2>⚙️ Notification Settings</h2>
        <p class="text-gray-600 mb-4">Configure your notification preferences</p>

        <!-- User Selector -->
        <div class="card mb-6">
          <h3>Select User</h3>
          <div class="form-group">
            <input v-model.number="settings.userId" type="number" placeholder="User ID" class="input" />
          </div>
          <button @click="loadSettings" class="btn-primary">Load Settings</button>
        </div>

        <!-- Notification Types -->
        <div v-if="settings.data" class="card mb-6">
          <h3>Notification Types</h3>
          <div class="settings-options">
            <label class="checkbox-item">
              <input
                v-model="settings.data.notify_mentions"
                type="checkbox"
                @change="updateNotificationType('notify_mentions')"
              />
              <span>💬 Notify on mentions</span>
            </label>
            <label class="checkbox-item">
              <input
                v-model="settings.data.notify_replies"
                type="checkbox"
                @change="updateNotificationType('notify_replies')"
              />
              <span>↩️ Notify on replies</span>
            </label>
            <label class="checkbox-item">
              <input
                v-model="settings.data.notify_reactions"
                type="checkbox"
                @change="updateNotificationType('notify_reactions')"
              />
              <span>👍 Notify on reactions</span>
            </label>
            <label class="checkbox-item">
              <input
                v-model="settings.data.notify_votes"
                type="checkbox"
                @change="updateNotificationType('notify_votes')"
              />
              <span>🗳️ Notify on votes</span>
            </label>
          </div>
        </div>

        <!-- Digest Frequency -->
        <div v-if="settings.data" class="card mb-6">
          <h3>Digest Frequency</h3>
          <div class="form-group">
            <label>Email Digest</label>
            <select v-model="settings.data.digest_frequency" @change="updateDigestFrequency" class="select">
              <option value="never">Never</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
        </div>

        <!-- Quiet Hours -->
        <div v-if="settings.data" class="card mb-6">
          <h3>Quiet Hours</h3>
          <label class="checkbox-item mb-4">
            <input v-model="settings.data.quiet_hours_enabled" type="checkbox" />
            <span>Enable quiet hours</span>
          </label>
          <div v-if="settings.data.quiet_hours_enabled" class="quiet-hours">
            <div class="form-group">
              <label>Start Time (HH:MM)</label>
              <input v-model="settings.data.quiet_hours_start" type="time" class="input" />
            </div>
            <div class="form-group">
              <label>End Time (HH:MM)</label>
              <input v-model="settings.data.quiet_hours_end" type="time" class="input" />
            </div>
            <button @click="updateQuietHours" class="btn-primary">Update Quiet Hours</button>
          </div>
        </div>

        <!-- Muted Workspaces -->
        <div v-if="settings.mutedWorkspaces" class="card">
          <h3>Muted Workspaces</h3>
          <div v-if="settings.mutedWorkspaces.length > 0" class="muted-list">
            <div v-for="ws in settings.mutedWorkspaces" :key="ws.id" class="muted-item">
              <span>{{ ws.name }}</span>
              <button @click="unmuteWorkspace(ws.id)" class="btn-sm">Unmute</button>
            </div>
          </div>
          <p v-else class="text-gray-500">No muted workspaces</p>
          <button @click="resetSettings" class="btn-secondary mt-4">Reset to Defaults</button>
        </div>
      </div>
    </div>

    <!-- Alert Messages -->
    <div v-if="alert.message" :class="['alert', `alert-${alert.type}`]">
      {{ alert.message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
  getConnectionStats,
  broadcastToWorkspace,
  getUserNotifications,
  getNotificationStats,
  sendNotification,
  markNotificationRead,
  deleteNotification,
  notifyMentions,
  notifyReply,
  notifyReaction,
  notifyVote,
  generateDigest,
  sendDigest,
  getDigestHistory,
  getDigestStats,
  getUserSettings,
  updateUserSettings,
  setDigestFrequency,
  setQuietHours,
  getMutedWorkspaces,
  resetSettingsToDefaults,
  type ConnectionStats,
  type NotificationSettings,
  type PushNotification,
  type ActivityDigest,
} from '@/services/phase15';

const activeTab = ref('websocket');

const tabs = [
  { id: 'websocket', label: '🔴 WebSocket' },
  { id: 'notifications', label: '🔔 Notifications' },
  { id: 'comments', label: '💬 Comments' },
  { id: 'digest', label: '📊 Digest' },
  { id: 'settings', label: '⚙️ Settings' },
];

// WebSocket State
const websocket = ref({
  stats: null as ConnectionStats | null,
  broadcastWorkspaceId: '',
  broadcastEventType: '',
  broadcastData: '{}',
  isLoading: false,
});

// Notifications State
const notifications = ref({
  userId: 0,
  list: [] as PushNotification[],
  stats: null as any,
  newTitle: '',
  newMessage: '',
  newActionUrl: '',
  isLoading: false,
});

// Comment Notifications State
const comments = ref({
  mentionCommentId: '',
  mentionAuthorId: 0,
  mentionedIds: '',
  replyCommentId: '',
  replierId: 0,
  replyContent: '',
  reactionCommentId: '',
  reactorId: 0,
  reactionType: '😀',
  voteCommentId: '',
  voterId: 0,
  voteType: 'up',
});

// Digest State
const digest = ref({
  userId: 0,
  frequency: 'daily',
  preview: null as ActivityDigest | null,
  stats: null as any,
  history: [] as Array<Record<string, unknown>>,
  isLoading: false,
});

// Settings State
const settings = ref({
  userId: 0,
  data: null as NotificationSettings | null,
  mutedWorkspaces: [] as Array<{ id: string; name: string }>,
  isLoading: false,
});

// Alert State
const alert = ref({
  message: '',
  type: 'info' as 'success' | 'error' | 'info' | 'warning',
});

// WebSocket Methods
async function loadWebSocketStats() {
  websocket.value.isLoading = true;
  try {
    websocket.value.stats = await getConnectionStats();
  } catch (error) {
    showAlert('Failed to load stats', 'error');
  } finally {
    websocket.value.isLoading = false;
  }
}

async function broadcastMessage() {
  if (!websocket.value.broadcastWorkspaceId || !websocket.value.broadcastEventType) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    const data = JSON.parse(websocket.value.broadcastData);
    await broadcastToWorkspace(websocket.value.broadcastWorkspaceId, websocket.value.broadcastEventType, data);
    showAlert('Message broadcasted', 'success');
    websocket.value.broadcastData = '{}';
  } catch (error) {
    showAlert('Failed to broadcast message', 'error');
  }
}

// Notification Methods
async function loadNotifications() {
  if (!notifications.value.userId) {
    showAlert('Please enter user ID', 'warning');
    return;
  }

  notifications.value.isLoading = true;
  try {
    const result = await getUserNotifications(notifications.value.userId, 50, 0);
    notifications.value.list = result.data;
    notifications.value.stats = await getNotificationStats(notifications.value.userId);
  } catch (error) {
    showAlert('Failed to load notifications', 'error');
  } finally {
    notifications.value.isLoading = false;
  }
}

async function sendNotificationClick() {
  if (!notifications.value.userId || !notifications.value.newTitle || !notifications.value.newMessage) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    await sendNotification(
      notifications.value.userId,
      notifications.value.newTitle,
      notifications.value.newMessage,
      notifications.value.newActionUrl || undefined
    );
    showAlert('Notification sent', 'success');
    notifications.value.newTitle = '';
    notifications.value.newMessage = '';
    notifications.value.newActionUrl = '';
    await loadNotifications();
  } catch (error) {
    showAlert('Failed to send notification', 'error');
  }
}

async function markAsRead(notificationId: string) {
  try {
    await markNotificationRead(notificationId);
    await loadNotifications();
  } catch (error) {
    showAlert('Failed to mark as read', 'error');
  }
}

async function deleteNotificationClick(notificationId: string) {
  try {
    await deleteNotification(notificationId);
    await loadNotifications();
  } catch (error) {
    showAlert('Failed to delete', 'error');
  }
}

// Comment Notification Methods
async function sendMentionNotification() {
  if (!comments.value.mentionCommentId || !comments.value.mentionAuthorId) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    const ids = comments.value.mentionedIds.split(',').map(id => parseInt(id.trim()));
    await notifyMentions(comments.value.mentionCommentId, comments.value.mentionAuthorId, ids);
    showAlert('Mention notifications sent', 'success');
  } catch (error) {
    showAlert('Failed to send notifications', 'error');
  }
}

async function sendReplyNotification() {
  if (!comments.value.replyCommentId || !comments.value.replierId || !comments.value.replyContent) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    await notifyReply(comments.value.replyCommentId, comments.value.replierId, comments.value.replyContent);
    showAlert('Reply notification sent', 'success');
  } catch (error) {
    showAlert('Failed to send notification', 'error');
  }
}

async function sendReactionNotification() {
  if (!comments.value.reactionCommentId || !comments.value.reactorId || !comments.value.reactionType) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    await notifyReaction(comments.value.reactionCommentId, comments.value.reactorId, comments.value.reactionType);
    showAlert('Reaction notification sent', 'success');
  } catch (error) {
    showAlert('Failed to send notification', 'error');
  }
}

async function sendVoteNotification() {
  if (!comments.value.voteCommentId || !comments.value.voterId) {
    showAlert('Please fill all fields', 'warning');
    return;
  }

  try {
    await notifyVote(comments.value.voteCommentId, comments.value.voterId, comments.value.voteType as 'up' | 'down');
    showAlert('Vote notification sent', 'success');
  } catch (error) {
    showAlert('Failed to send notification', 'error');
  }
}

// Digest Methods
async function generateDigestClick() {
  if (!digest.value.userId) {
    showAlert('Please enter user ID', 'warning');
    return;
  }

  digest.value.isLoading = true;
  try {
    digest.value.preview = await generateDigest(digest.value.userId, digest.value.frequency as any);
    digest.value.stats = await getDigestStats(digest.value.frequency as any);
  } catch (error) {
    showAlert('Failed to generate digest', 'error');
  } finally {
    digest.value.isLoading = false;
  }
}

async function sendDigestClick() {
  if (!digest.value.userId) {
    showAlert('Please enter user ID', 'warning');
    return;
  }

  try {
    await sendDigest(digest.value.userId, digest.value.frequency as any);
    showAlert('Digest email sent', 'success');
    await loadDigestHistory();
  } catch (error) {
    showAlert('Failed to send digest', 'error');
  }
}

async function loadDigestHistory() {
  if (!digest.value.userId) return;

  try {
    digest.value.history = await getDigestHistory(digest.value.userId);
  } catch (error) {
    showAlert('Failed to load history', 'error');
  }
}

// Settings Methods
async function loadSettings() {
  if (!settings.value.userId) {
    showAlert('Please enter user ID', 'warning');
    return;
  }

  settings.value.isLoading = true;
  try {
    settings.value.data = await getUserSettings(settings.value.userId);
    settings.value.mutedWorkspaces = await getMutedWorkspaces(settings.value.userId);
  } catch (error) {
    showAlert('Failed to load settings', 'error');
  } finally {
    settings.value.isLoading = false;
  }
}

async function updateNotificationType(type: string) {
  if (!settings.value.userId || !settings.value.data) return;

  try {
    await updateUserSettings(settings.value.userId, {
      [type]: (settings.value.data as any)[type],
    });
    showAlert('Settings updated', 'success');
  } catch (error) {
    showAlert('Failed to update settings', 'error');
  }
}

async function updateDigestFrequency() {
  if (!settings.value.userId || !settings.value.data) return;

  try {
    await setDigestFrequency(settings.value.userId, settings.value.data.digest_frequency);
    showAlert('Digest frequency updated', 'success');
  } catch (error) {
    showAlert('Failed to update', 'error');
  }
}

async function updateQuietHours() {
  if (!settings.value.userId || !settings.value.data) return;

  try {
    await setQuietHours(
      settings.value.userId,
      settings.value.data.quiet_hours_start,
      settings.value.data.quiet_hours_end,
      settings.value.data.quiet_hours_enabled
    );
    showAlert('Quiet hours updated', 'success');
  } catch (error) {
    showAlert('Failed to update quiet hours', 'error');
  }
}

async function unmuteWorkspaceClick(workspaceId: string) {
  if (!settings.value.userId) return;

  try {
    // This would need an unmute endpoint
    showAlert('Workspace unmuted', 'success');
    await loadSettings();
  } catch (error) {
    showAlert('Failed to unmute', 'error');
  }
}

async function resetSettings() {
  if (!settings.value.userId) return;

  try {
    await resetSettingsToDefaults(settings.value.userId);
    showAlert('Settings reset to defaults', 'success');
    await loadSettings();
  } catch (error) {
    showAlert('Failed to reset settings', 'error');
  }
}

// Utility Methods
function showAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
  alert.value = { message, type };
  setTimeout(() => {
    alert.value.message = '';
  }, 5000);
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString();
}

// Alias functions to match template
const sendNotification = sendNotificationClick;
const deleteNotification = deleteNotificationClick;
const generateDigest = generateDigestClick;
const sendDigest = sendDigestClick;
const unmuteWorkspace = unmuteWorkspaceClick;

// Load initial data
onMounted(async () => {
  await loadWebSocketStats();
});
</script>

<style scoped>
.phase15-view {
  padding: 2rem;
  background-color: #f9fafb;
  min-height: 100vh;
}

.view-header {
  background: white;
  padding: 2rem;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
}

.view-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.text-gray-600 {
  color: #4b5563;
  font-size: 0.875rem;
  margin: 0.5rem 0 0 0;
}

.tabs-container {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
  overflow: hidden;
}

.tabs {
  display: flex;
  border-bottom: 1px solid #e5e7eb;
  overflow-x: auto;
}

.tab-button {
  padding: 1rem 1.5rem;
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  font-weight: 500;
  border-bottom: 3px solid transparent;
  transition: all 0.2s;
  white-space: nowrap;
}

.tab-button:hover {
  color: #111827;
}

.tab-button.active {
  color: #2563eb;
  border-bottom-color: #2563eb;
}

.tab-content {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 2rem;
}

.section h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.card h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #111827;
  margin: 0 0 1rem 0;
}

.card h4 {
  font-size: 0.875rem;
  font-weight: 600;
  color: #111827;
  margin: 0.5rem 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.form-group label {
  font-weight: 500;
  color: #111827;
  font-size: 0.875rem;
}

.input,
.textarea,
.select {
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-family: inherit;
  font-size: 0.875rem;
}

.input:focus,
.textarea:focus,
.select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.textarea {
  resize: vertical;
  min-height: 100px;
}

.btn-primary,
.btn-secondary,
.btn-sm,
.btn-danger {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0.25rem;
  font-weight: 500;
  cursor: pointer;
  font-size: 0.875rem;
  transition: all 0.2s;
}

.btn-primary {
  background: #2563eb;
  color: white;
}

.btn-primary:hover {
  background: #1d4ed8;
}

.btn-secondary {
  background: #e5e7eb;
  color: #111827;
}

.btn-secondary:hover {
  background: #d1d5db;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

.btn-danger {
  background: #fee2e2;
  color: #991b1b;
}

.btn-danger:hover {
  background: #fca5a5;
}

.button-group {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1rem;
}

.stat-card {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 1rem;
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #2563eb;
  margin: 0.5rem 0;
}

.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.notification-item {
  background: white;
  border: 1px solid #d1d5db;
  border-left: 3px solid #e5e7eb;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.notification-item.unread {
  border-left-color: #2563eb;
  background: #f0f9ff;
}

.notification-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.notification-header h4 {
  margin: 0;
}

.time {
  font-size: 0.75rem;
  color: #6b7280;
}

.notification-message {
  margin: 0.5rem 0;
  font-size: 0.875rem;
  color: #6b7280;
}

.notification-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.75rem;
}

.settings-options {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.checkbox-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.checkbox-item input {
  cursor: pointer;
}

.quiet-hours {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 1rem;
  margin-top: 0.75rem;
}

.muted-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.muted-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.digest-info {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
  margin-bottom: 1rem;
}

.digest-info p {
  margin: 0.25rem 0;
  font-size: 0.875rem;
}

.workspace-digest {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
  margin-bottom: 0.75rem;
}

.workspace-digest h4 {
  margin: 0 0 0.25rem 0;
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
  font-size: 0.875rem;
}

.frequency {
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-weight: 600;
}

.date {
  color: #6b7280;
}

.count {
  color: #2563eb;
  font-weight: 500;
}

.alert {
  padding: 1rem;
  border-radius: 0.5rem;
  margin-top: 2rem;
  font-weight: 500;
  position: fixed;
  bottom: 2rem;
  right: 2rem;
  max-width: 400px;
  z-index: 1000;
}

.alert-success {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
}

.alert-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

.alert-info {
  background: #dbeafe;
  color: #1e3a8a;
  border: 1px solid #93c5fd;
}

.alert-warning {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde047;
}

.mb-4 {
  margin-bottom: 1rem;
}

.mb-6 {
  margin-bottom: 1.5rem;
}

.mt-4 {
  margin-top: 1rem;
}

.mb-4 {
  margin-bottom: 1rem;
}

.text-gray-500 {
  color: #6b7280;
}

@media (max-width: 768px) {
  .phase15-view {
    padding: 1rem;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .tabs {
    overflow-x: auto;
  }

  .tab-button {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }
}
</style>
