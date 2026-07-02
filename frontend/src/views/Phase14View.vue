<template>
  <div class="phase14-view">
    <!-- Header -->
    <div class="view-header">
      <div>
        <h1>⚡ Advanced Collaboration (Phase 14)</h1>
        <p class="text-gray-600">Real-time updates, activity feeds, advanced search, reactions & audit logs</p>
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

    <!-- Tab 1: Real-time Updates -->
    <div v-if="activeTab === 'realtime'" class="tab-content">
      <div class="section">
        <h2>🔴 Real-time Collaboration</h2>
        <p class="text-gray-600 mb-4">See who's active in your workspaces right now</p>

        <!-- Workspace Selector -->
        <div class="card mb-6">
          <h3>Select Workspace</h3>
          <div class="form-group">
            <select v-model="realtime.selectedWorkspace" @change="loadActiveUsers" class="select">
              <option value="">-- Choose a workspace --</option>
              <option value="workspace-1">Workspace 1</option>
              <option value="workspace-2">Workspace 2</option>
            </select>
          </div>
        </div>

        <!-- Active Users -->
        <div v-if="realtime.selectedWorkspace" class="card">
          <h3>Active Users ({{ realtime.activeUsers.length }})</h3>
          <div v-if="realtime.activeUsers.length > 0" class="active-users-grid">
            <div v-for="user in realtime.activeUsers" :key="user.user_id" class="user-card active">
              <div class="user-avatar">
                {{ user.displayName.charAt(0).toUpperCase() }}
              </div>
              <h4>{{ user.displayName }}</h4>
              <p class="text-sm text-gray-600">{{ user.email }}</p>
              <p class="text-xs text-gray-500">{{ user.connection_count }} connection(s)</p>
              <p class="text-xs text-green-600">🟢 Active</p>
            </div>
          </div>
          <p v-else class="text-gray-500">No active users in this workspace</p>
        </div>
      </div>
    </div>

    <!-- Tab 2: Activity Feeds -->
    <div v-if="activeTab === 'activity'" class="tab-content">
      <div class="section">
        <h2>📊 Activity Feeds</h2>
        <p class="text-gray-600 mb-4">Track workspace and query activity in real-time</p>

        <!-- Activity Type Selector -->
        <div class="card mb-6">
          <h3>Activity Type</h3>
          <div class="button-group">
            <button
              v-for="type in ['Workspace', 'User', 'Query']"
              :key="type"
              :class="['btn-secondary', { active: activity.selectedType === type }]"
              @click="activity.selectedType = type; loadActivity()"
            >
              {{ type }}
            </button>
          </div>
        </div>

        <!-- Activity Stats -->
        <div v-if="activity.stats" class="card mb-6">
          <h3>Activity Statistics</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <h4>Total Events</h4>
              <p class="stat-value">{{ activity.stats.totalEvents }}</p>
            </div>
            <div class="stat-card">
              <h4>Recent Events (24h)</h4>
              <p class="stat-value">{{ activity.stats.recentEvents }}</p>
            </div>
          </div>
          <div v-if="activity.stats.topContributors.length > 0" class="contributors">
            <h4>Top Contributors</h4>
            <div v-for="contributor in activity.stats.topContributors" :key="contributor.user_id" class="contributor-item">
              <span>{{ contributor.displayName }}</span>
              <span class="count">{{ contributor.count }}</span>
            </div>
          </div>
        </div>

        <!-- Activity Feed -->
        <div class="card">
          <h3>Activity Timeline</h3>
          <div v-if="activity.feed.length > 0" class="activity-timeline">
            <div v-for="event in activity.feed" :key="event.id" class="activity-item">
              <div class="activity-time">{{ formatDate(event.created_at) }}</div>
              <div class="activity-content">
                <h4>{{ event.event_type }}</h4>
                <p>{{ event.description }}</p>
                <p v-if="event.display_name" class="text-xs text-gray-500">by {{ event.display_name }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No activity recorded</p>
        </div>
      </div>
    </div>

    <!-- Tab 3: Advanced Search -->
    <div v-if="activeTab === 'search'" class="tab-content">
      <div class="section">
        <h2>🔍 Advanced Search</h2>
        <p class="text-gray-600 mb-4">Search queries, comments, and users with advanced filters</p>

        <!-- Search Bar -->
        <div class="card mb-6">
          <h3>Search Queries</h3>
          <div class="search-box">
            <input
              v-model="search.query"
              type="text"
              placeholder="Search queries..."
              @keyup.enter="performSearch"
            />
            <button @click="performSearch" class="btn-primary">Search</button>
          </div>

          <!-- Search Filters -->
          <div class="filters mt-4">
            <select v-model="search.sortBy" class="select">
              <option value="relevance">Sort by Relevance</option>
              <option value="recent">Sort by Recent</option>
              <option value="oldest">Sort by Oldest</option>
              <option value="name">Sort by Name</option>
            </select>
          </div>
        </div>

        <!-- Search Results -->
        <div class="card">
          <h3>Search Results ({{ search.results.count }}/{{ search.results.total }})</h3>
          <div v-if="search.results.data.length > 0" class="search-results">
            <div v-for="result in search.results.data" :key="result.id" class="result-item">
              <h4>{{ result.name }}</h4>
              <p class="text-sm text-gray-600">{{ result.description }}</p>
              <div class="result-meta">
                <span>Owner: {{ result.owner_name }}</span>
                <span v-if="result.relevance_score" class="relevance">Relevance: {{ result.relevance_score }}</span>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">{{ search.query ? 'No results found' : 'Enter a search term' }}</p>
        </div>

        <!-- Search Suggestions -->
        <div v-if="search.suggestions.queries.length > 0" class="card mt-6">
          <h3>Popular Queries</h3>
          <div class="suggestions">
            <button
              v-for="suggestion in search.suggestions.queries.slice(0, 5)"
              :key="suggestion"
              class="suggestion-pill"
              @click="search.query = suggestion; performSearch()"
            >
              {{ suggestion }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 4: Comments & Reactions -->
    <div v-if="activeTab === 'reactions'" class="tab-content">
      <div class="section">
        <h2>👍 Comments & Reactions</h2>
        <p class="text-gray-600 mb-4">View popular comments and voting statistics</p>

        <!-- Query Selector -->
        <div class="card mb-6">
          <h3>Select Query</h3>
          <div class="form-group">
            <input
              v-model.number="reactions.queryId"
              type="number"
              placeholder="Query ID"
              @change="loadReactionStats"
            />
          </div>
        </div>

        <!-- Most Reacted Comments -->
        <div v-if="reactions.queryId" class="card mb-6">
          <h3>Most Reacted Comments</h3>
          <div v-if="reactions.mostReacted.length > 0" class="comments-list">
            <div v-for="comment in reactions.mostReacted" :key="comment.id" class="reaction-comment">
              <p class="comment-text">{{ comment.content }}</p>
              <div class="comment-reactions">
                <span class="reaction-count">😀 {{ comment.reaction_count }}</span>
              </div>
              <p class="text-xs text-gray-500">by {{ comment.displayName }}</p>
            </div>
          </div>
          <p v-else class="text-gray-500">No comments yet</p>
        </div>

        <!-- Most Voted Comments -->
        <div v-if="reactions.queryId" class="card">
          <h3>Most Voted Comments</h3>
          <div v-if="reactions.mostVoted.length > 0" class="comments-list">
            <div v-for="comment in reactions.mostVoted" :key="comment.id" class="voted-comment">
              <p class="comment-text">{{ comment.content }}</p>
              <div class="vote-stats">
                <span class="upvotes">👍 {{ comment.upvotes || 0 }}</span>
                <span class="downvotes">👎 {{ comment.downvotes || 0 }}</span>
                <span class="score">Score: {{ comment.score || 0 }}</span>
              </div>
              <p class="text-xs text-gray-500">by {{ comment.displayName }}</p>
            </div>
          </div>
          <p v-else class="text-gray-500">No voted comments</p>
        </div>
      </div>
    </div>

    <!-- Tab 5: Audit Logs -->
    <div v-if="activeTab === 'audit'" class="tab-content">
      <div class="section">
        <h2>📋 Collaboration Audit Logs</h2>
        <p class="text-gray-600 mb-4">Track all collaboration activities and user actions</p>

        <!-- Audit Stats -->
        <div v-if="audit.stats" class="card mb-6">
          <h3>Audit Statistics</h3>
          <div class="stats-grid">
            <div class="stat-card">
              <h4>Total Events</h4>
              <p class="stat-value">{{ audit.stats.totalEvents }}</p>
            </div>
            <div class="stat-card">
              <h4>Recent (24h)</h4>
              <p class="stat-value">{{ audit.stats.recent24h }}</p>
            </div>
          </div>

          <!-- Events by Type -->
          <div class="audit-breakdown">
            <h4>Events by Action</h4>
            <div v-for="action in audit.stats.eventsByAction" :key="action.action" class="breakdown-item">
              <span>{{ action.action }}</span>
              <span class="count">{{ action.count }}</span>
            </div>
          </div>
        </div>

        <!-- Audit Logs -->
        <div class="card">
          <h3>Audit Log Entries</h3>
          <div v-if="audit.logs.length > 0" class="audit-logs">
            <div v-for="log in audit.logs" :key="log.id" class="audit-entry">
              <div class="entry-header">
                <span class="action-badge" :class="`action-${log.action}`">{{ log.action }}</span>
                <span class="timestamp">{{ formatDate(log.created_at) }}</span>
              </div>
              <div class="entry-details">
                <p><strong>User:</strong> {{ log.display_name }}</p>
                <p><strong>Entity:</strong> {{ log.entity_type }} ({{ log.entity_id || 'N/A' }})</p>
                <p v-if="Object.keys(log.changes).length > 0"><strong>Changes:</strong> {{ JSON.stringify(log.changes) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No audit logs available</p>
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
  getActiveUsers,
  getWorkspaceActivity,
  getActivityStats,
  searchQueries,
  getMostReactedComments,
  getMostVotedComments,
  getWorkspaceAuditLogs,
  getAuditStats,
  getSearchSuggestions,
  type Activity,
  type ActivityStats,
  type AuditLog,
  type AuditStats,
} from '@/services/phase14';

const activeTab = ref('realtime');

const tabs = [
  { id: 'realtime', label: '🔴 Real-time' },
  { id: 'activity', label: '📊 Activity' },
  { id: 'search', label: '🔍 Search' },
  { id: 'reactions', label: '👍 Reactions' },
  { id: 'audit', label: '📋 Audit' },
];

// Real-time State
const realtime = ref({
  selectedWorkspace: '',
  activeUsers: [] as any[],
  isLoading: false,
});

// Activity State
const activity = ref({
  selectedType: 'Workspace',
  feed: [] as Activity[],
  stats: null as ActivityStats | null,
  isLoading: false,
});

// Search State
const search = ref({
  query: '',
  sortBy: 'relevance',
  results: {
    data: [] as any[],
    total: 0,
    count: 0,
  },
  suggestions: {
    queries: [] as string[],
    workspaces: [] as string[],
  },
  isLoading: false,
});

// Reactions State
const reactions = ref({
  queryId: 0,
  mostReacted: [] as any[],
  mostVoted: [] as any[],
  isLoading: false,
});

// Audit State
const audit = ref({
  logs: [] as AuditLog[],
  stats: null as AuditStats | null,
  isLoading: false,
});

// Alert State
const alert = ref({
  message: '',
  type: 'info' as 'success' | 'error' | 'info' | 'warning',
});

// Load initial data
onMounted(async () => {
  try {
    // Load default stats
    if (realtime.value.selectedWorkspace) {
      await loadActiveUsers();
    }
  } catch (error) {
    showAlert('Failed to load data', 'error');
  }
});

// Real-time Methods
async function loadActiveUsers() {
  if (!realtime.value.selectedWorkspace) return;

  realtime.value.isLoading = true;
  try {
    realtime.value.activeUsers = await getActiveUsers(realtime.value.selectedWorkspace);
  } catch (error) {
    showAlert('Failed to load active users', 'error');
  } finally {
    realtime.value.isLoading = false;
  }
}

// Activity Methods
async function loadActivity() {
  activity.value.isLoading = true;
  try {
    if (activity.value.selectedType === 'Workspace') {
      activity.value.feed = await getWorkspaceActivity('workspace-1', 50);
      activity.value.stats = await getActivityStats('workspace-1');
    }
    // TODO: Implement User and Query activity loading
  } catch (error) {
    showAlert('Failed to load activity', 'error');
  } finally {
    activity.value.isLoading = false;
  }
}

// Search Methods
async function performSearch() {
  if (!search.value.query.trim()) {
    showAlert('Enter a search term', 'warning');
    return;
  }

  search.value.isLoading = true;
  try {
    const result = await searchQueries(search.value.query, undefined, search.value.sortBy);
    search.value.results = result;
  } catch (error) {
    showAlert('Search failed', 'error');
  } finally {
    search.value.isLoading = false;
  }
}

async function loadSearchSuggestions() {
  if (!search.value.query) return;

  try {
    search.value.suggestions = await getSearchSuggestions(search.value.query);
  } catch (error) {
    // Silently fail for suggestions
  }
}

// Reaction Methods
async function loadReactionStats() {
  if (!reactions.value.queryId) return;

  reactions.value.isLoading = true;
  try {
    reactions.value.mostReacted = await getMostReactedComments(reactions.value.queryId);
    reactions.value.mostVoted = await getMostVotedComments(reactions.value.queryId);
  } catch (error) {
    showAlert('Failed to load reactions', 'error');
  } finally {
    reactions.value.isLoading = false;
  }
}

// Audit Methods
async function loadAuditLogs() {
  audit.value.isLoading = true;
  try {
    audit.value.logs = await getWorkspaceAuditLogs('workspace-1', 50);
    audit.value.stats = await getAuditStats('workspace-1');
  } catch (error) {
    showAlert('Failed to load audit logs', 'error');
  } finally {
    audit.value.isLoading = false;
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
</script>

<style scoped>
.phase14-view {
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
}

.tabs-container {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 2rem;
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

.form-group input,
.form-group select {
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-family: inherit;
  font-size: 0.875rem;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.select {
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-family: inherit;
  font-size: 0.875rem;
}

.btn-primary,
.btn-secondary,
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

.btn-primary:hover:not(:disabled) {
  background: #1d4ed8;
}

.btn-secondary {
  background: #e5e7eb;
  color: #111827;
}

.btn-secondary:hover:not(:disabled),
.btn-secondary.active {
  background: #d1d5db;
}

.btn-secondary.active {
  background: #2563eb;
  color: white;
}

.button-group {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.active-users-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1rem;
}

.user-card {
  background: white;
  border: 2px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 1rem;
  text-align: center;
}

.user-card.active {
  border-color: #10b981;
  background: #f0fdf4;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: #2563eb;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  margin: 0 auto 0.5rem;
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

.contributors,
.audit-breakdown {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.contributor-item,
.breakdown-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  border-bottom: 1px solid #e5e7eb;
}

.contributor-item:last-child,
.breakdown-item:last-child {
  border-bottom: none;
}

.count {
  font-weight: 600;
  color: #2563eb;
}

.activity-timeline {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.activity-item {
  display: flex;
  gap: 1rem;
  padding: 0.75rem;
  background: white;
  border-left: 3px solid #2563eb;
  border-radius: 0.25rem;
}

.activity-time {
  font-size: 0.75rem;
  color: #6b7280;
  min-width: 100px;
}

.activity-content {
  flex: 1;
}

.activity-content h4 {
  margin: 0 0 0.25rem 0;
  font-size: 0.875rem;
}

.activity-content p {
  margin: 0.25rem 0;
  font-size: 0.875rem;
  color: #6b7280;
}

.search-box {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.search-box input {
  flex: 1;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.filters {
  display: flex;
  gap: 0.5rem;
}

.search-results {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.result-item {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.result-item h4 {
  margin: 0 0 0.25rem 0;
  font-size: 0.875rem;
}

.result-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 0.5rem;
}

.relevance {
  color: #10b981;
  font-weight: 600;
}

.suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.suggestion-pill {
  background: #e0e7ff;
  color: #4f46e5;
  border: none;
  border-radius: 9999px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s;
}

.suggestion-pill:hover {
  background: #4f46e5;
  color: white;
}

.comments-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.reaction-comment,
.voted-comment {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.comment-text {
  margin: 0 0 0.5rem 0;
  font-size: 0.875rem;
  color: #111827;
}

.comment-reactions,
.vote-stats {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  margin: 0.5rem 0;
}

.reaction-count,
.upvotes,
.downvotes,
.score {
  display: inline-block;
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.score {
  font-weight: 600;
  color: #2563eb;
}

.audit-logs {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.audit-entry {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
}

.entry-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.action-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  background: #e5e7eb;
  color: #111827;
}

.action-badge.action-create {
  background: #dcfce7;
  color: #166534;
}

.action-badge.action-update {
  background: #fef3c7;
  color: #92400e;
}

.action-badge.action-delete {
  background: #fee2e2;
  color: #991b1b;
}

.timestamp {
  font-size: 0.75rem;
  color: #6b7280;
}

.entry-details {
  font-size: 0.875rem;
  color: #6b7280;
}

.entry-details p {
  margin: 0.25rem 0;
}

.entry-details strong {
  color: #111827;
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

.mt-4 {
  margin-top: 1rem;
}

@media (max-width: 768px) {
  .phase14-view {
    padding: 1rem;
  }

  .active-users-grid,
  .stats-grid,
  .search-results {
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
