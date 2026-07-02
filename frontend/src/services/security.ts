import { apiClient } from './api';

// Types for Security features

export interface RLSPolicy {
  id: string;
  name: string;
  tableName: string;
  expression: string;
  active: boolean;
  createdAt: string;
}

export interface RLSToggleRequest {
  tableName: string;
  enabled: boolean;
}

export interface UserAccessRules {
  userId: string;
  userRole: string;
  tables: Record<string, {
    canRead: boolean;
    canWrite: boolean;
    canDelete: boolean;
    filters?: Record<string, unknown>;
  }>;
}

export interface TotpSetup {
  secret: string;
  encoded: string;
  qrCode: string;
  backupCodes: string[];
}

export interface MfaStatus {
  enabled: boolean;
  method: string;
  configured: boolean;
  lastUsed: string;
  backupCodesRemaining: number;
}

export interface ArchiveStats {
  mainLogs: number;
  archivedLogs: number;
  mainSize: number;
  archiveSize: number;
  totalLogs: number;
}

export interface RetentionPolicy {
  retentionDays: number;
  archiveEnabled: boolean;
  archiveCompression: boolean;
  notifyBeforeDelete: boolean;
  notifyDays: number;
}

export interface AuditStats {
  actionBreakdown: Array<{ action: string; count: number }>;
  topUsers: Array<{ user_id: number; count: number }>;
  entityBreakdown: Array<{ entity: string; count: number }>;
  logsInPastDay: number;
  logsInPastWeek: number;
  logsInPastMonth: number;
}

export interface EncryptionMetadata {
  algorithm: string;
  keyLength: number;
  ivLength: number;
  encoding: string;
}

// RLS Service Methods

export async function listRlsPolicies(): Promise<RLSPolicy[]> {
  const response = await apiClient.get('/api/admin/security/rls/policies');
  return response.data.data || [];
}

export async function createRlsPolicy(policy: Omit<RLSPolicy, 'id' | 'createdAt'>): Promise<RLSPolicy> {
  const response = await apiClient.post('/api/admin/security/rls/policies', policy);
  return response.data.data;
}

export async function updateRlsPolicy(id: string, updates: Partial<RLSPolicy>): Promise<RLSPolicy> {
  const response = await apiClient.put(`/api/admin/security/rls/policies/${id}`, updates);
  return response.data.data;
}

export async function deleteRlsPolicy(id: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.delete(`/api/admin/security/rls/policies/${id}`);
  return response.data;
}

export async function toggleRls(tableName: string, enabled: boolean): Promise<{ success: boolean; data: RLSToggleRequest & { message: string } }> {
  const response = await apiClient.post('/api/admin/security/rls/toggle', {
    tableName,
    enabled,
  });
  return response.data;
}

export async function getUserAccessRules(): Promise<UserAccessRules> {
  const response = await apiClient.get('/api/admin/security/rls/access');
  return response.data.data;
}

// MFA Service Methods

export async function generateTotpSecret(): Promise<TotpSetup> {
  const response = await apiClient.post('/api/admin/security/mfa/totp/generate', {});
  return response.data.data;
}

export async function verifyTotpCode(secret: string, code: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/security/mfa/totp/verify', {
    secret,
    code,
  });
  return response.data;
}

export async function sendMfaCode(method: 'email' | 'sms' = 'email'): Promise<{ success: boolean; message: string; expiresIn: number }> {
  const response = await apiClient.post('/api/admin/security/mfa/send', {
    method,
  });
  return response.data;
}

export async function enableMfa(method: 'totp' | 'email' | 'sms', secret?: string): Promise<{ success: boolean; message: string }> {
  const response = await apiClient.post('/api/admin/security/mfa/enable', {
    method,
    secret,
  });
  return response.data;
}

export async function getMfaStatus(): Promise<MfaStatus> {
  const response = await apiClient.get('/api/admin/security/mfa/status');
  return response.data.data;
}

// Audit Archive Service Methods

export async function archiveAuditLogs(retentionDays: number = 90): Promise<{ success: boolean; message: string; archived: number }> {
  const response = await apiClient.post('/api/admin/security/audit/archive', {
    retentionDays,
  });
  return response.data;
}

export async function getArchiveStats(): Promise<ArchiveStats> {
  const response = await apiClient.get('/api/admin/security/audit/archive/stats');
  return response.data.data;
}

export async function setRetentionPolicy(policy: RetentionPolicy): Promise<{ success: boolean; message: string; data: RetentionPolicy }> {
  const response = await apiClient.post('/api/admin/security/audit/retention-policy', policy);
  return response.data;
}

export async function getRetentionPolicy(): Promise<RetentionPolicy> {
  const response = await apiClient.get('/api/admin/security/audit/retention-policy');
  return response.data.data;
}

export async function exportAuditLogs(format: 'csv' | 'json' = 'csv', fromDate?: Date, toDate?: Date): Promise<{ success: boolean; format: string; data: unknown }> {
  const params = new URLSearchParams();
  params.append('format', format);
  if (fromDate) params.append('from', fromDate.toISOString());
  if (toDate) params.append('to', toDate.toISOString());

  const response = await apiClient.get(`/api/admin/security/audit/export?${params}`);
  return response.data;
}

export async function getAuditStats(): Promise<AuditStats> {
  const response = await apiClient.get('/api/admin/security/audit/stats');
  return response.data.data;
}

// Encryption Service Methods

export async function getEncryptionMetadata(): Promise<EncryptionMetadata> {
  const response = await apiClient.get('/api/admin/security/encryption/metadata');
  return response.data.data;
}
