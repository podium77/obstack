<template>
  <div class="security-settings-view">
    <!-- Header -->
    <div class="settings-header">
      <div>
        <h1>🔐 Advanced Security Settings</h1>
        <p class="text-gray-600">Manage row-level security, multi-factor authentication, and audit logs</p>
      </div>
      <!-- Quick Access Buttons (Admin Only) -->
      <div v-if="authStore.user?.isGlobalAdmin" class="quick-access">
        <!-- Server is stopped -->
        <button 
          v-if="!adminConsoleStatus.running && !adminConsoleStatus.loading"
          class="btn-console btn-start"
          @click="startAdminConsole"
          title="Start Admin Console Server"
        >
          ▶️ Start Admin Console
        </button>
        
        <!-- Server is starting -->
        <button 
          v-if="adminConsoleStatus.loading"
          class="btn-console btn-loading"
          disabled
          title="Starting Admin Console..."
        >
          ⟳ Starting...
        </button>
        
        <!-- Server is running -->
        <template v-if="adminConsoleStatus.running && !adminConsoleStatus.loading">
          <button 
            class="btn-console btn-open"
            @click="openAdminConsole"
            title="Open Admin Console in new window"
          >
            🚀 Open Admin Console
          </button>
          <button 
            class="btn-console btn-stop"
            @click="stopAdminConsole"
            title="Stop Admin Console Server"
          >
            ⏹️ Stop
          </button>
        </template>
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

    <!-- Tab 1: Row-Level Security -->
    <div v-if="activeTab === 'rls'" class="tab-content">
      <div class="section">
        <h2>Row-Level Security Policies</h2>
        <p class="text-gray-600 mb-4">Control data access at the row level based on user roles and attributes</p>

        <!-- RLS Policies List -->
        <div class="card mb-6">
          <h3>Active Policies</h3>
          <div v-if="rls.policies.length > 0" class="policies-grid">
            <div v-for="policy in rls.policies" :key="policy.id" class="policy-card">
              <div class="policy-header">
                <h4>{{ policy.name }}</h4>
                <span class="badge" :class="{ 'badge-active': policy.active }">
                  {{ policy.active ? 'Active' : 'Inactive' }}
                </span>
              </div>
              <p class="text-sm text-gray-600 mb-2">Table: <code>{{ policy.tableName }}</code></p>
              <p class="text-sm text-gray-600 mb-3">Expression: <code class="code-block">{{ policy.expression }}</code></p>
              <div class="policy-actions">
                <button class="btn-secondary btn-sm" @click="editPolicy(policy)">Edit</button>
                <button class="btn-danger btn-sm" @click="deletePolicy(policy.id)">Delete</button>
              </div>
            </div>
          </div>
          <p v-else class="text-gray-500">No policies defined yet</p>
        </div>

        <!-- Create New Policy -->
        <div class="card">
          <h3>Create New Policy</h3>
          <form @submit.prevent="createPolicy" class="form">
            <div class="form-group">
              <label>Policy Name</label>
              <input v-model="rls.newPolicy.name" type="text" placeholder="Users can see their own data" />
            </div>
            <div class="form-group">
              <label>Table Name</label>
              <input v-model="rls.newPolicy.tableName" type="text" placeholder="users" />
            </div>
            <div class="form-group">
              <label>Policy Expression</label>
              <textarea
                v-model="rls.newPolicy.expression"
                placeholder="user_id = current_user_id()"
                rows="3"
              ></textarea>
            </div>
            <button type="submit" class="btn-primary" :disabled="rls.isLoading">
              {{ rls.isLoading ? 'Creating...' : 'Create Policy' }}
            </button>
          </form>
        </div>

        <!-- User Access Rules -->
        <div class="card mt-6">
          <h3>Your Access Rules</h3>
          <div v-if="rls.userAccess" class="access-rules">
            <p class="text-sm mb-3">Role: <strong>{{ rls.userAccess.userRole }}</strong></p>
            <div class="tables-grid">
              <div v-for="(access, tableName) in rls.userAccess.tables" :key="tableName" class="access-card">
                <h4>{{ tableName }}</h4>
                <div class="access-permissions">
                  <div class="permission" :class="{ allowed: access.canRead }">
                    <span class="icon">{{ access.canRead ? '✓' : '✗' }}</span>
                    <span>Read</span>
                  </div>
                  <div class="permission" :class="{ allowed: access.canWrite }">
                    <span class="icon">{{ access.canWrite ? '✓' : '✗' }}</span>
                    <span>Write</span>
                  </div>
                  <div class="permission" :class="{ allowed: access.canDelete }">
                    <span class="icon">{{ access.canDelete ? '✓' : '✗' }}</span>
                    <span>Delete</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: Multi-Factor Authentication -->
    <div v-if="activeTab === 'mfa'" class="tab-content">
      <div class="section">
        <h2>Multi-Factor Authentication</h2>
        <p class="text-gray-600 mb-4">Enhance security with two-factor authentication</p>

        <!-- MFA Status -->
        <div class="card mb-6">
          <h3>MFA Status</h3>
          <div v-if="mfa.status" class="status-info">
            <div class="status-item">
              <span class="label">Status:</span>
              <span class="value" :class="{ enabled: mfa.status.enabled }">
                {{ mfa.status.enabled ? '🟢 Enabled' : '🔴 Disabled' }}
              </span>
            </div>
            <div class="status-item">
              <span class="label">Method:</span>
              <span class="value">{{ mfa.status.method.toUpperCase() }}</span>
            </div>
            <div class="status-item">
              <span class="label">Configured:</span>
              <span class="value">{{ mfa.status.configured ? 'Yes' : 'No' }}</span>
            </div>
            <div class="status-item">
              <span class="label">Backup Codes Remaining:</span>
              <span class="value">{{ mfa.status.backupCodesRemaining }}/10</span>
            </div>
          </div>
        </div>

        <!-- TOTP Setup -->
        <div class="card mb-6">
          <h3>Time-based One-Time Password (TOTP)</h3>
          <p class="text-sm text-gray-600 mb-4">Use an authenticator app like Google Authenticator or Authy</p>

          <!-- Setup Steps -->
          <div v-if="!mfa.totpSetup" class="setup-steps">
            <button class="btn-primary" @click="generateTotpSecret" :disabled="mfa.isLoading">
              {{ mfa.isLoading ? 'Generating...' : 'Generate TOTP Secret' }}
            </button>
          </div>

          <!-- Show Secret -->
          <div v-else-if="mfa.totpSetup && !mfa.totpVerified" class="setup-section">
            <div class="step">
              <h4>Step 1: Scan QR Code</h4>
              <p class="text-sm text-gray-600 mb-3">Scan this QR code with your authenticator app:</p>
              <img v-if="mfa.totpSetup.qrCode" :src="mfa.totpSetup.qrCode" alt="QR Code" class="qr-code" />
            </div>

            <div class="step">
              <h4>Step 2: Save Secret</h4>
              <p class="text-sm text-gray-600 mb-3">Or enter this secret manually:</p>
              <code class="secret-code">{{ mfa.totpSetup.secret }}</code>
            </div>

            <div class="step">
              <h4>Step 3: Save Backup Codes</h4>
              <p class="text-sm text-gray-600 mb-2">Save these codes in a secure location:</p>
              <div class="backup-codes">
                <div v-for="code in mfa.totpSetup.backupCodes" :key="code" class="backup-code">
                  {{ code }}
                </div>
              </div>
            </div>

            <div class="step">
              <h4>Step 4: Verify Code</h4>
              <input
                v-model="mfa.verifyCode"
                type="text"
                placeholder="000000"
                maxlength="6"
                class="code-input"
              />
              <button
                class="btn-primary"
                @click="verifyTotpCode"
                :disabled="mfa.isLoading || !mfa.verifyCode"
              >
                {{ mfa.isLoading ? 'Verifying...' : 'Verify Code' }}
              </button>
            </div>
          </div>

          <!-- TOTP Verified -->
          <div v-else-if="mfa.totpVerified" class="alert alert-success">
            ✓ TOTP authentication is configured and active
          </div>
        </div>

        <!-- Email MFA -->
        <div class="card">
          <h3>Email-based Authentication</h3>
          <p class="text-sm text-gray-600 mb-4">Receive authentication codes via email</p>
          <button class="btn-primary" @click="sendMfaCode" :disabled="mfa.isLoading">
            {{ mfa.isLoading ? 'Sending...' : 'Send Test Code' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tab 3: Audit & Archive -->
    <div v-if="activeTab === 'audit'" class="tab-content">
      <div class="section">
        <h2>Audit Logs & Archival</h2>
        <p class="text-gray-600 mb-4">Manage audit logs, archival, and retention policies</p>

        <!-- Archive Statistics -->
        <div class="card mb-6">
          <h3>Archive Statistics</h3>
          <div v-if="audit.stats" class="stats-grid">
            <div class="stat-card">
              <h4>Active Logs</h4>
              <p class="stat-value">{{ audit.stats.mainLogs.toLocaleString() }}</p>
              <p class="stat-size">{{ formatBytes(audit.stats.mainSize) }}</p>
            </div>
            <div class="stat-card">
              <h4>Archived Logs</h4>
              <p class="stat-value">{{ audit.stats.archivedLogs.toLocaleString() }}</p>
              <p class="stat-size">{{ formatBytes(audit.stats.archiveSize) }}</p>
            </div>
            <div class="stat-card">
              <h4>Total Logs</h4>
              <p class="stat-value">{{ audit.stats.totalLogs.toLocaleString() }}</p>
            </div>
          </div>
        </div>

        <!-- Retention Policy -->
        <div class="card mb-6">
          <h3>Retention Policy</h3>
          <form @submit.prevent="updateRetentionPolicy" class="form">
            <div class="form-group">
              <label>Retention Period (Days)</label>
              <input v-model.number="audit.policy.retentionDays" type="number" min="1" max="3650" />
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="audit.policy.archiveEnabled" type="checkbox" />
                Enable Archival
              </label>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="audit.policy.archiveCompression" type="checkbox" />
                Compress Archives
              </label>
            </div>
            <div class="form-group">
              <label class="checkbox-label">
                <input v-model="audit.policy.notifyBeforeDelete" type="checkbox" />
                Notify Before Delete
              </label>
            </div>
            <div v-if="audit.policy.notifyBeforeDelete" class="form-group">
              <label>Notify Days Before</label>
              <input v-model.number="audit.policy.notifyDays" type="number" min="1" max="30" />
            </div>
            <button type="submit" class="btn-primary" :disabled="audit.isLoading">
              {{ audit.isLoading ? 'Saving...' : 'Save Policy' }}
            </button>
          </form>
        </div>

        <!-- Archive Logs -->
        <div class="card mb-6">
          <h3>Archive Audit Logs</h3>
          <p class="text-sm text-gray-600 mb-4">Move old logs to archive table</p>
          <button class="btn-primary" @click="archiveLogs" :disabled="audit.isLoading">
            {{ audit.isLoading ? 'Archiving...' : 'Archive Logs Now' }}
          </button>
        </div>

        <!-- Export Logs -->
        <div class="card">
          <h3>Export Audit Logs</h3>
          <div class="form-group">
            <label>Format</label>
            <select v-model="audit.exportFormat" class="select">
              <option value="csv">CSV</option>
              <option value="json">JSON</option>
            </select>
          </div>
          <button class="btn-primary" @click="exportLogs" :disabled="audit.isLoading">
            {{ audit.isLoading ? 'Exporting...' : 'Export Logs' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Tab 4: Encryption -->
    <div v-if="activeTab === 'encryption'" class="tab-content">
      <div class="section">
        <h2>Field-Level Encryption</h2>
        <p class="text-gray-600 mb-4">Configure encryption for sensitive data fields</p>

        <!-- Encryption Status -->
        <div class="card mb-6">
          <h3>Encryption Metadata</h3>
          <div v-if="encryption.metadata" class="metadata-grid">
            <div class="metadata-item">
              <span class="label">Algorithm:</span>
              <code>{{ encryption.metadata.algorithm }}</code>
            </div>
            <div class="metadata-item">
              <span class="label">Key Length:</span>
              <code>{{ encryption.metadata.keyLength }} bytes</code>
            </div>
            <div class="metadata-item">
              <span class="label">IV Length:</span>
              <code>{{ encryption.metadata.ivLength }} bytes</code>
            </div>
            <div class="metadata-item">
              <span class="label">Encoding:</span>
              <code>{{ encryption.metadata.encoding }}</code>
            </div>
          </div>
        </div>

        <!-- Encrypted Fields -->
        <div class="card mb-6">
          <h3>Encrypted Fields Configuration</h3>
          <p class="text-sm text-gray-600 mb-4">Configure which fields should be encrypted</p>
          <div class="form-group">
            <label>Table Name</label>
            <input v-model="encryption.tableName" type="text" placeholder="users" />
          </div>
          <div class="form-group">
            <label>Fields to Encrypt (comma-separated)</label>
            <input
              v-model="encryption.fieldsToEncrypt"
              type="text"
              placeholder="ssn, credit_card, phone"
            />
          </div>
          <button class="btn-primary" @click="configureEncryption" :disabled="encryption.isLoading">
            {{ encryption.isLoading ? 'Configuring...' : 'Save Configuration' }}
          </button>
        </div>

        <!-- Key Rotation -->
        <div class="card">
          <h3>Key Rotation</h3>
          <p class="text-sm text-gray-600 mb-4">Rotate encryption keys for security</p>
          <button class="btn-warning" @click="rotateKeys" :disabled="encryption.isLoading">
            {{ encryption.isLoading ? 'Rotating...' : 'Rotate Keys' }}
          </button>
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
import { useAuthStore } from '@/stores/auth';
import {
  listRlsPolicies,
  createRlsPolicy,
  deleteRlsPolicy,
  getUserAccessRules,
  generateTotpSecret,
  verifyTotpCode,
  sendMfaCode,
  getArchiveStats,
  getRetentionPolicy,
  setRetentionPolicy,
  archiveAuditLogs,
  exportAuditLogs,
  getEncryptionMetadata,
  type RLSPolicy,
  type UserAccessRules,
  type TotpSetup,
  type ArchiveStats,
  type RetentionPolicy,
  type EncryptionMetadata,
} from '@/services/security';

// Auth store for permission checks
const authStore = useAuthStore();

// Admin Console State (declare first!)
const adminConsoleStatus = ref({
  running: false,
  loading: false,
  url: 'http://localhost:5173'
});

// Active tab
const activeTab = ref('rls');

const tabs = [
  { id: 'rls', label: '🔒 Row-Level Security' },
  { id: 'mfa', label: '🔐 MFA' },
  { id: 'audit', label: '📋 Audit & Archive' },
  { id: 'encryption', label: '🔑 Encryption' },
];

// RLS State
const rls = ref({
  policies: [] as RLSPolicy[],
  userAccess: null as UserAccessRules | null,
  newPolicy: {
    name: '',
    tableName: '',
    expression: '',
  },
  isLoading: false,
});

// MFA State
const mfa = ref({
  status: null,
  totpSetup: null as TotpSetup | null,
  totpVerified: false,
  verifyCode: '',
  isLoading: false,
});

// Audit State
const audit = ref({
  stats: null as ArchiveStats | null,
  policy: {
    retentionDays: 90,
    archiveEnabled: true,
    archiveCompression: false,
    notifyBeforeDelete: true,
    notifyDays: 7,
  } as RetentionPolicy,
  exportFormat: 'csv',
  isLoading: false,
});

// Encryption State
const encryption = ref({
  metadata: null as EncryptionMetadata | null,
  tableName: '',
  fieldsToEncrypt: '',
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
    // Load RLS policies
    rls.value.policies = await listRlsPolicies();
    rls.value.userAccess = await getUserAccessRules();

    // Load MFA status
    // mfa.value.status = await getMfaStatus(); // Would need to implement

    // Load audit stats
    audit.value.stats = await getArchiveStats();
    audit.value.policy = await getRetentionPolicy();

    // Load encryption metadata
    encryption.value.metadata = await getEncryptionMetadata();
    
    // Check Admin Console status
    await checkAdminConsoleStatus();
  } catch (error) {
    showAlert('Failed to load security settings', 'error');
  }
});

// RLS Methods
async function createPolicy() {
  if (!rls.value.newPolicy.name || !rls.value.newPolicy.tableName || !rls.value.newPolicy.expression) {
    showAlert('Please fill in all fields', 'error');
    return;
  }

  rls.value.isLoading = true;
  try {
    const policy = await createRlsPolicy(rls.value.newPolicy);
    rls.value.policies.push(policy);
    rls.value.newPolicy = { name: '', tableName: '', expression: '' };
    showAlert('Policy created successfully', 'success');
  } catch (error) {
    showAlert('Failed to create policy', 'error');
  } finally {
    rls.value.isLoading = false;
  }
}

function editPolicy(policy: RLSPolicy) {
  // TODO: Implement edit modal
  console.log('Edit policy', policy);
}

async function deletePolicy(id: string) {
  if (!confirm('Are you sure you want to delete this policy?')) return;

  rls.value.isLoading = true;
  try {
    await deleteRlsPolicy(id);
    rls.value.policies = rls.value.policies.filter(p => p.id !== id);
    showAlert('Policy deleted successfully', 'success');
  } catch (error) {
    showAlert('Failed to delete policy', 'error');
  } finally {
    rls.value.isLoading = false;
  }
}

// MFA Methods
async function generateTotpSecret() {
  mfa.value.isLoading = true;
  try {
    const setup = await generateTotpSecret();
    mfa.value.totpSetup = setup;
    mfa.value.verifyCode = '';
    showAlert('TOTP secret generated', 'success');
  } catch (error) {
    showAlert('Failed to generate TOTP secret', 'error');
  } finally {
    mfa.value.isLoading = false;
  }
}

async function verifyTotpCode() {
  if (!mfa.value.totpSetup) return;

  mfa.value.isLoading = true;
  try {
    await verifyTotpCode(mfa.value.totpSetup.secret, mfa.value.verifyCode);
    mfa.value.totpVerified = true;
    showAlert('TOTP verified successfully', 'success');
  } catch (error) {
    showAlert('Invalid TOTP code', 'error');
  } finally {
    mfa.value.isLoading = false;
  }
}

async function sendMfaCode() {
  mfa.value.isLoading = true;
  try {
    await sendMfaCode('email');
    showAlert('Code sent to your email', 'success');
  } catch (error) {
    showAlert('Failed to send code', 'error');
  } finally {
    mfa.value.isLoading = false;
  }
}

// Audit Methods
async function updateRetentionPolicy() {
  audit.value.isLoading = true;
  try {
    await setRetentionPolicy(audit.value.policy);
    showAlert('Retention policy updated', 'success');
  } catch (error) {
    showAlert('Failed to update policy', 'error');
  } finally {
    audit.value.isLoading = false;
  }
}

async function archiveLogs() {
  audit.value.isLoading = true;
  try {
    await archiveAuditLogs(audit.value.policy.retentionDays);
    // Reload stats
    audit.value.stats = await getArchiveStats();
    showAlert('Logs archived successfully', 'success');
  } catch (error) {
    showAlert('Failed to archive logs', 'error');
  } finally {
    audit.value.isLoading = false;
  }
}

async function exportLogs() {
  audit.value.isLoading = true;
  try {
    const result = await exportAuditLogs(audit.value.exportFormat as 'csv' | 'json');
    // Trigger download
    const blob = new Blob([JSON.stringify(result.data)], { type: 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit-logs.${audit.value.exportFormat}`;
    a.click();
    showAlert('Logs exported successfully', 'success');
  } catch (error) {
    showAlert('Failed to export logs', 'error');
  } finally {
    audit.value.isLoading = false;
  }
}

// Encryption Methods
function configureEncryption() {
  if (!encryption.value.tableName || !encryption.value.fieldsToEncrypt) {
    showAlert('Please fill in all fields', 'error');
    return;
  }
  // TODO: Call API to configure encryption
  showAlert('Encryption configuration saved', 'success');
}

function rotateKeys() {
  // TODO: Call API to rotate keys
  showAlert('Key rotation initiated', 'success');
}

// Admin Console Methods
async function checkAdminConsoleStatus() {
  try {
    const token = authStore.token;
    const response = await fetch('http://localhost:8000/api/admin-console/status', {
      headers: {
        'Authorization': `Bearer ${token}`,
      }
    });
    if (response.ok) {
      const data = await response.json();
      adminConsoleStatus.value = {
        running: data.running,
        loading: false,
        url: data.url
      };
    }
  } catch (error) {
    adminConsoleStatus.value.running = false;
  }
}

async function startAdminConsole() {
  adminConsoleStatus.value.loading = true;
  try {
    const token = authStore.token;
    const response = await fetch('http://localhost:8000/api/admin-console/start', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      }
    });
    const data = await response.json();
    if (data.success) {
      adminConsoleStatus.value.running = true;
      showAlert('Admin Console started successfully', 'success');
      await new Promise(resolve => setTimeout(resolve, 1000));
      openAdminConsole();
    } else {
      showAlert(data.message || 'Failed to start Admin Console', 'error');
    }
  } catch (error) {
    showAlert('Error starting Admin Console: ' + (error as Error).message, 'error');
  } finally {
    adminConsoleStatus.value.loading = false;
  }
}

async function stopAdminConsole() {
  if (!confirm('Are you sure you want to stop the Admin Console server?')) {
    return;
  }
  
  try {
    const token = authStore.token;
    const response = await fetch('http://localhost:8000/api/admin-console/stop', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
      }
    });
    const data = await response.json();
    if (data.success) {
      adminConsoleStatus.value.running = false;
      showAlert('Admin Console stopped successfully', 'success');
    } else {
      showAlert(data.message || 'Failed to stop Admin Console', 'error');
    }
  } catch (error) {
    showAlert('Error stopping Admin Console: ' + (error as Error).message, 'error');
  }
}

function openAdminConsole() {
  if (!adminConsoleStatus.value.running) {
    showAlert('Admin Console is not running. Start it first.', 'warning');
    return;
  }
  window.open(adminConsoleStatus.value.url, '_blank');
}

// Utility Methods
function showAlert(message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info') {
  alert.value = { message, type };
  setTimeout(() => {
    alert.value.message = '';
  }, 5000);
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}
</script>

<style scoped>
.security-settings-view {
  padding: 2rem;
  background-color: #f9fafb;
  min-height: 100vh;
}

.settings-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  background: white;
  padding: 2rem;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.settings-header h1 {
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

.policies-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.policy-card {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 1rem;
}

.policy-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.policy-header h4 {
  margin: 0;
}

.badge {
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  background: #f3f4f6;
  color: #6b7280;
}

.badge-active {
  background: #dcfce7;
  color: #166534;
}

.code-block {
  display: block;
  background: #111827;
  color: #f3f4f6;
  padding: 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.75rem;
  overflow-x: auto;
  margin: 0.5rem 0;
}

.policy-actions {
  display: flex;
  gap: 0.5rem;
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

.form-group textarea {
  font-family: monospace;
  resize: vertical;
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
.btn-warning,
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

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover:not(:disabled) {
  background: #d97706;
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
.btn-warning:disabled,
.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.access-rules {
  padding: 1rem;
}

.tables-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.access-card {
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 1rem;
}

.access-card h4 {
  margin: 0 0 1rem 0;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.access-permissions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.permission {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 0.25rem;
  color: #6b7280;
  font-size: 0.875rem;
}

.permission.allowed {
  background: #dcfce7;
  color: #166534;
}

.permission .icon {
  font-weight: bold;
}

.status-info {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.status-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.status-item .label {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.status-item .value {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.status-item .value.enabled {
  color: #059669;
}

.setup-steps,
.setup-section {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.step {
  padding: 1rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.step h4 {
  margin-top: 0;
}

.qr-code {
  width: 200px;
  height: 200px;
  border: 2px solid #d1d5db;
  border-radius: 0.25rem;
}

.secret-code {
  display: block;
  background: #111827;
  color: #10b981;
  padding: 1rem;
  border-radius: 0.25rem;
  font-family: monospace;
  word-break: break-all;
  user-select: all;
}

.backup-codes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.backup-code {
  padding: 0.5rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.75rem;
  text-align: center;
}

.code-input {
  font-size: 2rem;
  letter-spacing: 0.5rem;
  text-align: center;
  font-family: monospace;
  font-weight: 600;
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

.stat-card h4 {
  font-size: 0.875rem;
  margin: 0 0 0.5rem 0;
  color: #6b7280;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  margin: 0.5rem 0;
}

.stat-size {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0;
}

.metadata-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.metadata-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  background: white;
  border: 1px solid #d1d5db;
  border-radius: 0.25rem;
}

.metadata-item .label {
  font-size: 0.75rem;
  color: #6b7280;
  font-weight: 500;
}

.metadata-item code {
  font-family: monospace;
  background: #111827;
  color: #10b981;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
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
  border: 1px solid #fcd34d;
}

/* Quick Access Section */
.quick-access {
  display: flex;
  gap: 1rem;
}

.btn-console {
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 0.375rem;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px rgba(102, 126, 234, 0.4);
  white-space: nowrap;
}

.btn-console:hover {
  background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
  transform: translateY(-2px);
  box-shadow: 0 6px 12px rgba(102, 126, 234, 0.6);
}

.btn-console:active {
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.4);
}

/* Button variants */
.btn-console.btn-start {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  box-shadow: 0 4px 6px rgba(16, 185, 129, 0.4);
}

.btn-console.btn-start:hover {
  background: linear-gradient(135deg, #059669 0%, #10b981 100%);
  box-shadow: 0 6px 12px rgba(16, 185, 129, 0.6);
}

.btn-console.btn-open {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  box-shadow: 0 4px 6px rgba(102, 126, 234, 0.4);
}

.btn-console.btn-open:hover {
  background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
  box-shadow: 0 6px 12px rgba(102, 126, 234, 0.6);
}

.btn-console.btn-stop {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  box-shadow: 0 4px 6px rgba(239, 68, 68, 0.4);
}

.btn-console.btn-stop:hover {
  background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
  box-shadow: 0 6px 12px rgba(239, 68, 68, 0.6);
}

.btn-console.btn-loading {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-console:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-console:disabled:hover {
  transform: none;
}

@media (max-width: 768px) {
  .security-settings-view {
    padding: 1rem;
  }

  .settings-header {
    flex-direction: column;
    text-align: center;
    gap: 1rem;
  }

  .quick-access {
    width: 100%;
    justify-content: center;
  }

  .btn-console {
    width: 100%;
  }

  .settings-header h1 {
    font-size: 1.5rem;
  }

  .policies-grid,
  .tables-grid,
  .stats-grid,
  .metadata-grid,
  .status-info {
    grid-template-columns: 1fr;
  }

  .tabs {
    overflow-x: auto;
  }

  .tab-button {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
  }

  .alert {
    max-width: calc(100% - 2rem);
    right: 1rem;
    bottom: 1rem;
  }
}
</style>
