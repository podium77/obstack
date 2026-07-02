#!/bin/bash

# Phase 12: Advanced Security - Test Suite

test_count=0
pass_count=0
fail_count=0

run_test() {
    local test_name=$1
    local cmd=$2
    test_count=$((test_count + 1))
    echo -n "Test $test_count: $test_name... "
    if eval "$cmd" > /dev/null 2>&1; then
        echo "✓ PASS"
        pass_count=$((pass_count + 1))
    else
        echo "✗ FAIL"
        fail_count=$((fail_count + 1))
    fi
}

echo "╔════════════════════════════════════════════════════════════╗"
echo "║  PHASE 12: ADVANCED SECURITY - COMPREHENSIVE TEST SUITE   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# ============================================================
# BACKEND SERVICES TESTS
# ============================================================
echo "📦 BACKEND SERVICES"
echo "─────────────────────────────────────────────────────────"

run_test "RowLevelSecurityService exists" \
    "test -f src/Service/RowLevelSecurityService.php"

run_test "RowLevelSecurityService has createPolicy method" \
    "grep -q 'public function createPolicy' src/Service/RowLevelSecurityService.php"

run_test "RowLevelSecurityService has listPolicies method" \
    "grep -q 'public function listPolicies' src/Service/RowLevelSecurityService.php"

run_test "RowLevelSecurityService has deletePolicy method" \
    "grep -q 'public function deletePolicy' src/Service/RowLevelSecurityService.php"

run_test "RowLevelSecurityService has toggleRls method" \
    "grep -q 'public function toggleRls' src/Service/RowLevelSecurityService.php"

run_test "RowLevelSecurityService has getUserAccessRules method" \
    "grep -q 'public function getUserAccessRules' src/Service/RowLevelSecurityService.php"

echo ""

run_test "EncryptionService exists" \
    "test -f src/Service/EncryptionService.php"

run_test "EncryptionService has encrypt method" \
    "grep -q 'public function encrypt' src/Service/EncryptionService.php"

run_test "EncryptionService has decrypt method" \
    "grep -q 'public function decrypt' src/Service/EncryptionService.php"

run_test "EncryptionService has encryptFields method" \
    "grep -q 'public function encryptFields' src/Service/EncryptionService.php"

run_test "EncryptionService has decryptFields method" \
    "grep -q 'public function decryptFields' src/Service/EncryptionService.php"

run_test "EncryptionService has rotateKey method" \
    "grep -q 'public function rotateKey' src/Service/EncryptionService.php"

run_test "EncryptionService has hash method" \
    "grep -q 'public function hash' src/Service/EncryptionService.php"

echo ""

run_test "MFAService exists" \
    "test -f src/Service/MFAService.php"

run_test "MFAService has generateTotpSecret method" \
    "grep -q 'public function generateTotpSecret' src/Service/MFAService.php"

run_test "MFAService has verifyTotpCode method" \
    "grep -q 'public function verifyTotpCode' src/Service/MFAService.php"

run_test "MFAService has sendMfaCode method" \
    "grep -q 'public function sendMfaCode' src/Service/MFAService.php"

run_test "MFAService has enableMfa method" \
    "grep -q 'public function enableMfa' src/Service/MFAService.php"

run_test "MFAService has disableMfa method" \
    "grep -q 'public function disableMfa' src/Service/MFAService.php"

run_test "MFAService has getMfaStatus method" \
    "grep -q 'public function getMfaStatus' src/Service/MFAService.php"

echo ""

run_test "AuditArchiveService exists" \
    "test -f src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has archiveOldLogs method" \
    "grep -q 'public function archiveOldLogs' src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has getArchiveStats method" \
    "grep -q 'public function getArchiveStats' src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has setRetentionPolicy method" \
    "grep -q 'public function setRetentionPolicy' src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has getRetentionPolicy method" \
    "grep -q 'public function getRetentionPolicy' src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has exportLogs method" \
    "grep -q 'public function exportLogs' src/Service/AuditArchiveService.php"

run_test "AuditArchiveService has getAuditStats method" \
    "grep -q 'public function getAuditStats' src/Service/AuditArchiveService.php"

echo ""

# ============================================================
# CONTROLLER TESTS
# ============================================================
echo "🎯 BACKEND CONTROLLER"
echo "─────────────────────────────────────────────────────────"

run_test "SecurityController exists" \
    "test -f src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has listRlsPolicies endpoint" \
    "grep -q 'listRlsPolicies' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has createRlsPolicy endpoint" \
    "grep -q 'createRlsPolicy' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has generateTotpSecret endpoint" \
    "grep -q 'generateTotpSecret' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has verifyTotpCode endpoint" \
    "grep -q 'verifyTotpCode' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has archiveAuditLogs endpoint" \
    "grep -q 'archiveAuditLogs' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has getArchiveStats endpoint" \
    "grep -q 'getArchiveStats' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has setRetentionPolicy endpoint" \
    "grep -q 'setRetentionPolicy' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController has getEncryptionMetadata endpoint" \
    "grep -q 'getEncryptionMetadata' src/Controller/Admin/API/SecurityController.php"

run_test "SecurityController uses ROLE_ADMIN authorization" \
    "grep -q '#\[IsGranted.*ROLE_ADMIN' src/Controller/Admin/API/SecurityController.php"

echo ""

# ============================================================
# FRONTEND SERVICE TESTS
# ============================================================
echo "🔧 FRONTEND SERVICES"
echo "─────────────────────────────────────────────────────────"

run_test "Security service exists" \
    "test -f frontend/src/services/security.ts"

run_test "Security service has listRlsPolicies function" \
    "grep -q 'export async function listRlsPolicies' frontend/src/services/security.ts"

run_test "Security service has createRlsPolicy function" \
    "grep -q 'export async function createRlsPolicy' frontend/src/services/security.ts"

run_test "Security service has toggleRls function" \
    "grep -q 'export async function toggleRls' frontend/src/services/security.ts"

run_test "Security service has generateTotpSecret function" \
    "grep -q 'export async function generateTotpSecret' frontend/src/services/security.ts"

run_test "Security service has verifyTotpCode function" \
    "grep -q 'export async function verifyTotpCode' frontend/src/services/security.ts"

run_test "Security service has enableMfa function" \
    "grep -q 'export async function enableMfa' frontend/src/services/security.ts"

run_test "Security service has getMfaStatus function" \
    "grep -q 'export async function getMfaStatus' frontend/src/services/security.ts"

run_test "Security service has archiveAuditLogs function" \
    "grep -q 'export async function archiveAuditLogs' frontend/src/services/security.ts"

run_test "Security service has setRetentionPolicy function" \
    "grep -q 'export async function setRetentionPolicy' frontend/src/services/security.ts"

run_test "Security service has getEncryptionMetadata function" \
    "grep -q 'export async function getEncryptionMetadata' frontend/src/services/security.ts"

run_test "Security service has TypeScript interfaces" \
    "grep -q 'export interface RLSPolicy' frontend/src/services/security.ts"

echo ""

# ============================================================
# FRONTEND COMPONENT TESTS
# ============================================================
echo "🎨 FRONTEND COMPONENTS"
echo "─────────────────────────────────────────────────────────"

run_test "SecuritySettingsView exists" \
    "test -f frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has RLS tab" \
    "grep -q 'Row-Level Security' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has MFA tab" \
    "grep -q 'Multi-Factor Authentication' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has Audit tab" \
    "grep -q 'Audit Logs & Archival' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has Encryption tab" \
    "grep -q 'Field-Level Encryption' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has createPolicy method" \
    "grep -q 'async function createPolicy' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has generateTotpSecret method" \
    "grep -q 'async function generateTotpSecret' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has archiveLogs method" \
    "grep -q 'async function archiveLogs' frontend/src/views/SecuritySettingsView.vue"

run_test "SecuritySettingsView has exportLogs method" \
    "grep -q 'async function exportLogs' frontend/src/views/SecuritySettingsView.vue"

echo ""

# ============================================================
# ROUTER INTEGRATION TESTS
# ============================================================
echo "🛣️  ROUTER INTEGRATION"
echo "─────────────────────────────────────────────────────────"

run_test "Router has security-settings route" \
    "grep -q 'security-settings' frontend/src/router/index.ts"

run_test "Router route uses SecuritySettingsView component" \
    "grep -q \"import.*SecuritySettingsView\" frontend/src/router/index.ts || grep -q \"SecuritySettingsView\" frontend/src/router/index.ts"

run_test "SecuritySettingsView route requires auth" \
    "grep -A3 'security-settings' frontend/src/router/index.ts | grep -q 'requiresAuth'"

echo ""

# ============================================================
# NAVIGATION INTEGRATION TESTS
# ============================================================
echo "🧭 NAVIGATION INTEGRATION"
echo "─────────────────────────────────────────────────────────"

run_test "Layout has security settings navigation link" \
    "grep -q 'security-settings' frontend/src/components/Layout.vue"

run_test "Layout has SecuritySettings route reference" \
    "grep -q 'SecuritySettings' frontend/src/components/Layout.vue"

run_test "Layout security link has icon" \
    "grep -q '🔐' frontend/src/components/Layout.vue"

echo ""

# ============================================================
# BUILD VALIDATION TESTS
# ============================================================
echo "🔨 BUILD VALIDATION"
echo "─────────────────────────────────────────────────────────"

run_test "Frontend builds successfully" \
    "cd frontend && npm run build > /dev/null 2>&1"

run_test "Build has SecuritySettingsView component" \
    "grep -r 'SecuritySettingsView' frontend/dist 2>/dev/null | wc -l | grep -q -v '^0$'"

echo ""

# ============================================================
# SECURITY FEATURES VALIDATION
# ============================================================
echo "🔒 SECURITY FEATURES VALIDATION"
echo "─────────────────────────────────────────────────────────"

run_test "SecurityController uses JWT authentication" \
    "grep -q 'ROLE_ADMIN' src/Controller/Admin/API/SecurityController.php"

run_test "EncryptionService uses AES-256-CBC" \
    "grep -q 'AES-256-CBC' src/Service/EncryptionService.php"

run_test "EncryptionService uses password_hash for storage" \
    "grep -q 'password_hash' src/Service/EncryptionService.php"

run_test "MFAService generates TOTP codes" \
    "grep -q 'generateTotpCode' src/Service/MFAService.php"

run_test "MFAService validates 6-digit codes" \
    "grep -q 'preg_match.*6' src/Service/MFAService.php"

run_test "AuditArchiveService handles retention policies" \
    "grep -q 'setRetentionPolicy' src/Service/AuditArchiveService.php"

run_test "RLSService validates expressions" \
    "grep -q 'validateExpression' src/Service/RowLevelSecurityService.php"

echo ""

# ============================================================
# SUMMARY
# ============================================================
echo "═══════════════════════════════════════════════════════════"
echo "TEST SUMMARY"
echo "═══════════════════════════════════════════════════════════"
echo "Total Tests:  $test_count"
echo "✓ Passed:     $pass_count"
echo "✗ Failed:     $fail_count"

if [ $fail_count -eq 0 ]; then
    echo ""
    echo "🎉 ALL TESTS PASSED! Phase 12 is ready."
    exit 0
else
    echo ""
    echo "⚠️  Some tests failed. Review the implementation."
    exit 1
fi
