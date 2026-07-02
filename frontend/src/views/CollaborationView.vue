<template>
  <div class="collaboration-view">
    <!-- Header -->
    <div class="collaboration-header">
      <div>
        <h1>👥 Collaboration & Sharing</h1>
        <p class="text-gray-600">Manage workspaces, share queries, control access, and collaborate with comments</p>
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

    <!-- Tab 1: Workspaces -->
    <div v-if="activeTab === 'workspaces'" class="tab-content">
      <div class="section">
        <h2>Workspaces</h2>
        <p class="text-gray-600 mb-4">Create and manage team workspaces for organizing queries</p>

        <!-- Create Workspace -->
        <div class="card mb-6">
          <h3>Create New Workspace</h3>
          <form @submit.prevent="createWorkspace" class="form">
            <div class="form-group">
              <label>Workspace Name</label>
              <input v-model="workspace.newWorkspace.name" type="text" placeholder="Analytics Team" />
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea
                v-model="workspace.newWorkspace.description"
                placeholder="Brief description of the workspace"
                rows="2"
              ></textarea>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="workspace.newWorkspace.isPublic" type="checkbox" />
                Make Public
              </label>
            </div>
            <button type="submit" class="btn-primary" :disabled="workspace.isLoading">
              {{ workspace.isLoading ? 'Creating...' : 'Create Workspace' }}
            </button>
          </form>
        </div>

        <!-- Workspaces List -->
        <div class="card">
          <h3>My Workspaces</h3>
          <div v-if="workspace.list.length > 0" class="workspaces-grid">
            <div v-for="ws in workspace.list" :key="ws.id" class="workspace-card">
              <h4>{{ ws.name }}</h4>
              <p class="text-sm text-gray-600 mb-2">{{ ws.description }}</p>
              <div class="workspace-stats">
                <div>👥 {{ ws.memberCount }} Members</div>
              </div>
              <div class="workspace-actions">
                <button class="btn-secondary btn-sm" @click="viewWorkspaceMembers(ws.id)">View</button>
                <button class="btn-danger btn-sm" @click="deleteWorkspace(ws.id)">Delete</button>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No workspaces yet</p>
        </div>
      </div>
    </div>

    <!-- Tab 2: Query Sharing -->
    <div v-if="activeTab === 'sharing'" class="tab-content">
      <div class="section">
        <h2>Query Sharing</h2>
        <p class="text-gray-600 mb-4">Share queries with team members and groups</p>

        <!-- Share Query Form -->
        <div class="card mb-6">
          <h3>Share Query</h3>
          <form @submit.prevent="shareQuery" class="form">
            <div class="form-group">
              <label>Query ID</label>
              <input v-model.number="sharing.queryId" type="number" placeholder="Query ID" />
            </div>
            <div class="form-group">
              <label>Access Level</label>
              <select v-model="sharing.accessLevel" class="select">
                <option value="view">View Only</option>
                <option value="edit">Edit</option>
                <option value="delete">Delete</option>
              </select>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="sharing.shareWithUsers" type="checkbox" />
                Share with Users
              </label>
              <input
                v-if="sharing.shareWithUsers"
                v-model="sharing.userIds"
                type="text"
                placeholder="Comma-separated user IDs"
              />
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="sharing.shareWithGroups" type="checkbox" />
                Share with Groups
              </label>
              <input
                v-if="sharing.shareWithGroups"
                v-model="sharing.groupIds"
                type="text"
                placeholder="Comma-separated group IDs"
              />
            </div>
            <button type="submit" class="btn-primary" :disabled="sharing.isLoading">
              {{ sharing.isLoading ? 'Sharing...' : 'Share Query' }}
            </button>
          </form>
        </div>

        <!-- Shared Queries -->
        <div class="card mb-6">
          <h3>Queries Shared With Me</h3>
          <div v-if="sharing.sharedQueries.length > 0" class="queries-list">
            <div v-for="query in sharing.sharedQueries" :key="query.id" class="query-item">
              <h4>{{ query.name }}</h4>
              <p class="text-sm text-gray-600">By: {{ query.owner }}</p>
              <span class="badge" :class="{ 'badge-view': query.accessLevel === 'view', 'badge-edit': query.accessLevel === 'edit' }">
                {{ query.accessLevel }}
              </span>
            </div>
          </div>
          <p v-else class="text-gray-500">No queries shared with you</p>
        </div>

        <!-- Query Shares Details -->
        <div class="card">
          <h3>View Query Shares</h3>
          <div class="form-group mb-4">
            <label>Query ID</label>
            <input
              v-model.number="sharing.viewQueryId"
              type="number"
              placeholder="Enter query ID"
              @change="loadQueryShares"
            />
          </div>
          <div v-if="sharing.queryShares.length > 0" class="shares-grid">
            <div v-for="share in sharing.queryShares" :key="share.id" class="share-card">
              <h4>{{ share.userName || share.groupName }}</h4>
              <p class="text-sm text-gray-600">{{ share.sharedWithUserId ? 'User' : 'Group' }}</p>
              <p class="text-sm font-medium">Access: {{ share.accessLevel }}</p>
            </div>
          </div>
          <p v-else class="text-gray-500">No shares for this query</p>
        </div>
      </div>
    </div>

    <!-- Tab 3: Access Control -->
    <div v-if="activeTab === 'access-control'" class="tab-content">
      <div class="section">
        <h2>Access Control Groups</h2>
        <p class="text-gray-600 mb-4">Create and manage user groups with custom permissions</p>

        <!-- Create Group -->
        <div class="card mb-6">
          <h3>Create Group</h3>
          <form @submit.prevent="createGroup" class="form">
            <div class="form-group">
              <label>Group Name</label>
              <input v-model="accessControl.newGroup.name" type="text" placeholder="Data Team" />
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea
                v-model="accessControl.newGroup.description"
                placeholder="Group description"
                rows="2"
              ></textarea>
            </div>
            <div class="form-group">
              <label>Permissions (comma-separated)</label>
              <input
                v-model="accessControl.newGroup.permissionsText"
                type="text"
                placeholder="query.view, query.edit, data.export"
              />
            </div>
            <button type="submit" class="btn-primary" :disabled="accessControl.isLoading">
              {{ accessControl.isLoading ? 'Creating...' : 'Create Group' }}
            </button>
          </form>
        </div>

        <!-- Groups List -->
        <div class="card mb-6">
          <h3>Access Control Groups</h3>
          <div v-if="accessControl.groups.length > 0" class="groups-grid">
            <div v-for="group in accessControl.groups" :key="group.id" class="group-card">
              <h4>{{ group.name }}</h4>
              <p class="text-sm text-gray-600 mb-2">{{ group.description }}</p>
              <div class="group-info">
                <span>👥 {{ group.memberCount }} Members</span>
              </div>
              <div class="permissions-list">
                <span
                  v-for="permission in group.permissions"
                  :key="permission"
                  class="permission-badge"
                >
                  {{ permission }}
                </span>
              </div>
              <div class="group-actions">
                <button class="btn-secondary btn-sm" @click="viewGroupDetails(group.id)">View</button>
                <button class="btn-danger btn-sm" @click="deleteGroup(group.id)">Delete</button>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No groups created yet</p>
        </div>

        <!-- User Groups -->
        <div class="card">
          <h3>Your Groups</h3>
          <div v-if="accessControl.userGroups.length > 0" class="groups-list">
            <div v-for="group in accessControl.userGroups" :key="group.id" class="group-item">
              <h4>{{ group.name }}</h4>
              <p class="text-sm text-gray-600">{{ group.description }}</p>
              <div class="permissions-list">
                <span
                  v-for="permission in group.permissions"
                  :key="permission"
                  class="permission-badge"
                >
                  {{ permission }}
                </span>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">You're not a member of any groups</p>
        </div>
      </div>
    </div>

    <!-- Tab 4: Comments & Annotations -->
    <div v-if="activeTab === 'comments'" class="tab-content">
      <div class="section">
        <h2>Query Comments & Annotations</h2>
        <p class="text-gray-600 mb-4">Collaborate with comments and code annotations</p>

        <!-- Add Comment -->
        <div class="card mb-6">
          <h3>Add Comment</h3>
          <div class="form-group mb-3">
            <label>Query ID</label>
            <input
              v-model.number="comments.queryId"
              type="number"
              placeholder="Query ID"
              @change="loadComments"
            />
          </div>
          <form @submit.prevent="addComment" class="form">
            <div class="form-group">
              <label>Your Comment</label>
              <textarea
                v-model="comments.newComment"
                placeholder="Share your thoughts on this query..."
                rows="3"
              ></textarea>
            </div>
            <button type="submit" class="btn-primary" :disabled="comments.isLoading">
              {{ comments.isLoading ? 'Posting...' : 'Post Comment' }}
            </button>
          </form>
        </div>

        <!-- Comments List -->
        <div class="card mb-6">
          <h3>Comments</h3>
          <div v-if="comments.list.length > 0" class="comments-section">
            <div v-for="comment in comments.list" :key="comment.id" class="comment-thread">
              <div class="comment-item">
                <div class="comment-header">
                  <strong>{{ comment.authorName }}</strong>
                  <span class="text-xs text-gray-500">{{ formatDate(comment.createdAt) }}</span>
                </div>
                <p class="comment-content">{{ comment.content }}</p>
                <div class="comment-actions">
                  <button class="action-link" @click="replyToComment(comment.id)">Reply</button>
                  <button class="action-link" @click="editComment(comment.id)">Edit</button>
                  <button class="action-link delete" @click="deleteComment(comment.id)">Delete</button>
                </div>
              </div>

              <!-- Replies -->
              <div v-if="comment.replies && comment.replies.length > 0" class="replies">
                <div v-for="reply in comment.replies" :key="reply.id" class="comment-item reply">
                  <div class="comment-header">
                    <strong>{{ reply.authorName }}</strong>
                    <span class="text-xs text-gray-500">{{ formatDate(reply.createdAt) }}</span>
                  </div>
                  <p class="comment-content">{{ reply.content }}</p>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No comments yet. Be the first to comment!</p>
        </div>

        <!-- Comment Stats -->
        <div class="card mb-6">
          <h3>Comment Statistics</h3>
          <div v-if="comments.stats" class="stats-grid">
            <div class="stat-card">
              <h4>Total Comments</h4>
              <p class="stat-value">{{ comments.stats.totalComments }}</p>
            </div>
            <div class="stat-card">
              <h4>Root Comments</h4>
              <p class="stat-value">{{ comments.stats.rootComments }}</p>
            </div>
            <div class="stat-card">
              <h4>Recent (7 days)</h4>
              <p class="stat-value">{{ comments.stats.recentComments }}</p>
            </div>
          </div>
          <div v-if="comments.stats && comments.stats.topContributors.length > 0" class="top-contributors">
            <h4>Top Contributors</h4>
            <div v-for="contributor in comments.stats.topContributors" :key="contributor.id" class="contributor-item">
              <span>{{ contributor.displayName }}</span>
              <span class="count">{{ contributor.count }}</span>
            </div>
          </div>
        </div>

        <!-- Annotations -->
        <div class="card">
          <h3>Code Annotations</h3>
          <form v-if="comments.queryId" @submit.prevent="addAnnotation" class="form mb-4">
            <div class="form-group">
              <label>Annotation Type</label>
              <select v-model="comments.newAnnotation.type" class="select">
                <option value="info">ℹ️ Info</option>
                <option value="warning">⚠️ Warning</option>
                <option value="error">❌ Error</option>
                <option value="suggestion">💡 Suggestion</option>
              </select>
            </div>
            <div class="form-group">
              <label>Character Position</label>
              <input
                v-model.number="comments.newAnnotation.position"
                type="number"
                placeholder="0"
                min="0"
              />
            </div>
            <div class="form-group">
              <label>Note</label>
              <textarea
                v-model="comments.newAnnotation.content"
                placeholder="Annotation note"
                rows="2"
              ></textarea>
            </div>
            <button type="submit" class="btn-primary" :disabled="comments.isLoading">
              {{ comments.isLoading ? 'Adding...' : 'Add Annotation' }}
            </button>
          </form>

          <div v-if="comments.annotations.length > 0" class="annotations-list">
            <div v-for="annotation in comments.annotations" :key="annotation.id" class="annotation-item">
              <span class="annotation-type">{{ annotation.type }}</span>
              <p class="text-sm">Pos {{ annotation.position }}: {{ annotation.content }}</p>
              <span class="text-xs text-gray-500">by {{ annotation.displayName }}</span>
            </div>
          </div>
          <p v-else class="text-gray-500">No annotations yet</p>
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
  createWorkspace,
  getUserWorkspaces,
  getWorkspaceMembers,
  shareQuery,
  getSharedQueries,
  getQueryShares,
  createGroup,
  listGroups,
  getUserGroups,
  addComment,
  getComments,
  getCommentStats,
  addAnnotation,
  getAnnotations,
  type Workspace,
  type SharedQuery,
  type QueryShare,
  type AccessControlGroup,
  type Comment,
  type CommentStats,
  type Annotation,
} from '@/services/collaboration';

// Active tab
const activeTab = ref('workspaces');

const tabs = [
  { id: 'workspaces', label: '🏢 Workspaces' },
  { id: 'sharing', label: '🔗 Query Sharing' },
  { id: 'access-control', label: '🔐 Access Control' },
  { id: 'comments', label: '💬 Comments' },
];

// Workspace State
const workspace = ref({
  list: [] as Workspace[],
  newWorkspace: {
    name: '',
    description: '',
    isPublic: false,
  },
  isLoading: false,
});

// Sharing State
const sharing = ref({
  queryId: 0,
  accessLevel: 'view',
  shareWithUsers: false,
  userIds: '',
  shareWithGroups: false,
  groupIds: '',
  sharedQueries: [] as SharedQuery[],
  queryShares: [] as QueryShare[],
  viewQueryId: 0,
  isLoading: false,
});

// Access Control State
const accessControl = ref({
  groups: [] as AccessControlGroup[],
  userGroups: [] as AccessControlGroup[],
  newGroup: {
    name: '',
    description: '',
    permissionsText: '',
    permissions: [] as string[],
  },
  isLoading: false,
});

// Comments State
const comments = ref({
  queryId: 0,
  newComment: '',
  list: [] as Comment[],
  stats: null as CommentStats | null,
  newAnnotation: {
    type: 'info',
    position: 0,
    content: '',
    color: '#FFFF00',
  },
  annotations: [] as Annotation[],
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
    workspace.value.list = await getUserWorkspaces();
    sharing.value.sharedQueries = await getSharedQueries();
    accessControl.value.groups = await listGroups();
    accessControl.value.userGroups = await getUserGroups();
  } catch (error) {
    showAlert('Failed to load collaboration data', 'error');
  }
});

// Workspace Methods
async function createWorkspace() {
  if (!workspace.value.newWorkspace.name) {
    showAlert('Workspace name is required', 'error');
    return;
  }

  workspace.value.isLoading = true;
  try {
    const newWs = await createWorkspace(workspace.value.newWorkspace);
    workspace.value.list.push(newWs);
    workspace.value.newWorkspace = { name: '', description: '', isPublic: false };
    showAlert('Workspace created successfully', 'success');
  } catch (error) {
    showAlert('Failed to create workspace', 'error');
  } finally {
    workspace.value.isLoading = false;
  }
}

function viewWorkspaceMembers(workspaceId: string) {
  // TODO: Implement workspace members view
  showAlert(`View members for workspace: ${workspaceId}`, 'info');
}

function deleteWorkspace(workspaceId: string) {
  if (confirm('Are you sure you want to delete this workspace?')) {
    // TODO: Implement delete
    workspace.value.list = workspace.value.list.filter(w => w.id !== workspaceId);
    showAlert('Workspace deleted', 'success');
  }
}

// Sharing Methods
async function shareQuery() {
  if (!sharing.value.queryId) {
    showAlert('Query ID is required', 'error');
    return;
  }

  const shareWith: Record<string, unknown> = {};
  if (sharing.value.shareWithUsers && sharing.value.userIds) {
    shareWith.users = sharing.value.userIds.split(',').map(id => parseInt(id.trim()));
  }
  if (sharing.value.shareWithGroups && sharing.value.groupIds) {
    shareWith.groups = sharing.value.groupIds.split(',').map(id => id.trim());
  }

  sharing.value.isLoading = true;
  try {
    await shareQuery(sharing.value.queryId, shareWith, sharing.value.accessLevel);
    sharing.value.queryId = 0;
    sharing.value.userIds = '';
    sharing.value.groupIds = '';
    showAlert('Query shared successfully', 'success');
  } catch (error) {
    showAlert('Failed to share query', 'error');
  } finally {
    sharing.value.isLoading = false;
  }
}

async function loadQueryShares() {
  if (!sharing.value.viewQueryId) return;

  try {
    sharing.value.queryShares = await getQueryShares(sharing.value.viewQueryId);
  } catch (error) {
    showAlert('Failed to load shares', 'error');
  }
}

// Access Control Methods
async function createGroup() {
  if (!accessControl.value.newGroup.name) {
    showAlert('Group name is required', 'error');
    return;
  }

  const permissions = accessControl.value.newGroup.permissionsText
    .split(',')
    .map(p => p.trim())
    .filter(p => p);

  accessControl.value.isLoading = true;
  try {
    const newGroup = await createGroup({
      ...accessControl.value.newGroup,
      permissions,
    });
    accessControl.value.groups.push(newGroup);
    accessControl.value.newGroup = { name: '', description: '', permissionsText: '', permissions: [] };
    showAlert('Group created successfully', 'success');
  } catch (error) {
    showAlert('Failed to create group', 'error');
  } finally {
    accessControl.value.isLoading = false;
  }
}

function viewGroupDetails(groupId: string) {
  // TODO: Implement group details view
  showAlert(`View details for group: ${groupId}`, 'info');
}

function deleteGroup(groupId: string) {
  if (confirm('Are you sure you want to delete this group?')) {
    accessControl.value.groups = accessControl.value.groups.filter(g => g.id !== groupId);
    showAlert('Group deleted', 'success');
  }
}

// Comments Methods
async function loadComments() {
  if (!comments.value.queryId) return;

  try {
    comments.value.list = await getComments(comments.value.queryId);
    comments.value.stats = await getCommentStats(comments.value.queryId);
    comments.value.annotations = await getAnnotations(comments.value.queryId);
  } catch (error) {
    showAlert('Failed to load comments', 'error');
  }
}

async function addComment() {
  if (!comments.value.newComment.trim()) {
    showAlert('Comment cannot be empty', 'error');
    return;
  }

  comments.value.isLoading = true;
  try {
    await addComment(comments.value.queryId, comments.value.newComment);
    comments.value.newComment = '';
    await loadComments();
    showAlert('Comment posted', 'success');
  } catch (error) {
    showAlert('Failed to post comment', 'error');
  } finally {
    comments.value.isLoading = false;
  }
}

function replyToComment(commentId: string) {
  // TODO: Implement reply UI
  showAlert(`Reply to comment: ${commentId}`, 'info');
}

function editComment(commentId: string) {
  // TODO: Implement edit UI
  showAlert(`Edit comment: ${commentId}`, 'info');
}

function deleteComment(commentId: string) {
  if (confirm('Delete this comment?')) {
    comments.value.list = comments.value.list.filter(c => c.id !== commentId);
    showAlert('Comment deleted', 'success');
  }
}

async function addAnnotation() {
  comments.value.isLoading = true;
  try {
    await addAnnotation(comments.value.queryId, comments.value.newAnnotation);
    comments.value.newAnnotation = { type: 'info', position: 0, content: '', color: '#FFFF00' };
    await loadComments();
    showAlert('Annotation added', 'success');
  } catch (error) {
    showAlert('Failed to add annotation', 'error');
  } finally {
    comments.value.isLoading = false;
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
  return new Date(dateString).toLocaleDateString();
}
</script>

<style scoped>
.collaboration-view {
  padding: 2rem;
  background-color: #f9fafb;
  min-height: 100vh;
}

.collaboration-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  background: white;
  padding: 2rem;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.collaboration-header h1 {
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

.form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group label {
  font-weight: 500;
  color: #111827;
  font-size: 0.875rem;
}

.form-group input,
.form-group textarea,
.form-group select {
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-family: inherit;
  font-size: 0.875rem;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input {
  width: auto;
  margin: 0;
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

.btn-secondary:hover:not(:disabled) {
  background: #d1d5db;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #dc2626;
}

.btn-sm {
  padding: 0.25rem 0.75rem;
  font-size: 0.75rem;
}

.btn-primary:disabled,
.btn-secondary:disabled,
.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.workspaces-grid,
.groups-grid,
.queries-list,
.shares-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
}

.workspace-card,
.group-card,
.query-item,
.share-card {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 1rem;
}

.workspace-card h4,
.group-card h4,
.query-item h4,
.share-card h4 {
  margin: 0 0 0.5rem 0;
}

.workspace-stats,
.group-info {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0.5rem 0;
}

.workspace-actions,
.group-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.permissions-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.permission-badge {
  display: inline-block;
  background: #e0e7ff;
  color: #4f46e5;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f3f4f6;
  color: #6b7280;
}

.badge-view {
  background: #dbeafe;
  color: #1e40af;
}

.badge-edit {
  background: #fef3c7;
  color: #92400e;
}

.comments-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.comment-thread {
  border-left: 3px solid #e5e7eb;
  padding-left: 1rem;
}

.comment-item {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  padding: 0.75rem;
  margin-bottom: 0.5rem;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.comment-content {
  font-size: 0.875rem;
  color: #111827;
  margin: 0.5rem 0;
}

.comment-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.action-link {
  background: none;
  border: none;
  color: #2563eb;
  cursor: pointer;
  font-size: 0.75rem;
  text-decoration: none;
}

.action-link:hover {
  text-decoration: underline;
}

.action-link.delete {
  color: #ef4444;
}

.replies {
  margin-top: 1rem;
  padding-left: 1rem;
}

.reply {
  background: #f9fafb;
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

.top-contributors {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #e5e7eb;
}

.contributor-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.875rem;
}

.count {
  font-weight: 600;
  color: #2563eb;
}

.annotations-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.annotation-item {
  background: white;
  border-left: 3px solid #fbbf24;
  padding: 0.75rem;
  border-radius: 0.25rem;
}

.annotation-type {
  display: inline-block;
  background: #fef3c7;
  color: #92400e;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
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

@media (max-width: 768px) {
  .collaboration-view {
    padding: 1rem;
  }

  .collaboration-header {
    flex-direction: column;
    text-align: center;
  }

  .collaboration-header h1 {
    font-size: 1.5rem;
  }

  .workspaces-grid,
  .groups-grid,
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
