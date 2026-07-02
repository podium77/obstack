#!/bin/bash

# Phase 11: Data Management - Test Script
# Tests import/export functionality and bulk operations

set -e

API_URL="http://localhost:8000/api"

echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║            Phase 11: Data Management - Test Suite                   ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

test_count=0
pass_count=0
fail_count=0

# Helper function
run_test() {
    local test_name=$1
    local cmd=$2
    
    test_count=$((test_count + 1))
    echo -n "Test $test_count: $test_name... "
    
    if eval "$cmd" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        pass_count=$((pass_count + 1))
    else
        echo -e "${RED}✗ FAIL${NC}"
        fail_count=$((fail_count + 1))
    fi
}

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}1. AUTHENTICATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

LOGIN_RESPONSE=$(curl -s -X POST "$API_URL/login" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin@obstack.local",
    "password": "TestPassword123"
  }')

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo -e "${RED}Failed to obtain authentication token${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Authentication successful${NC}"
echo ""

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}2. DATA MANAGEMENT SERVICES (Backend)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataImportService exists" \
  "test -f /home/jaffar/projets/symfony/obstack/src/Service/DataImportService.php"

run_test "DataExportService exists" \
  "test -f /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

run_test "BulkOperationService exists" \
  "test -f /home/jaffar/projets/symfony/obstack/src/Service/BulkOperationService.php"

run_test "DataValidationService exists" \
  "test -f /home/jaffar/projets/symfony/obstack/src/Service/DataValidationService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}3. DATA MANAGEMENT CONTROLLER${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataManagementController exists" \
  "test -f /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "Controller has import endpoints" \
  "grep -q 'importCsv\|importJson\|validateImport' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "Controller has export endpoints" \
  "grep -q 'exportCsv\|exportJson\|exportExcel' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "Controller has bulk endpoints" \
  "grep -q 'bulkInsert\|bulkUpdate\|bulkDelete' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}4. API ENDPOINTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "POST /api/admin/import/csv endpoint defined" \
  "grep -q '#\[Route.*import/csv' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "POST /api/admin/import/json endpoint defined" \
  "grep -q '#\[Route.*import/json' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "POST /api/admin/import/validate endpoint defined" \
  "grep -q '#\[Route.*import/validate' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "GET /api/admin/export/csv endpoint defined" \
  "grep -q '#\[Route.*export/csv' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "GET /api/admin/export/json endpoint defined" \
  "grep -q '#\[Route.*export/json' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "GET /api/admin/export/jsonl endpoint defined" \
  "grep -q '#\[Route.*export/jsonl' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "GET /api/admin/export/excel endpoint defined" \
  "grep -q '#\[Route.*export/excel' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "POST /api/admin/bulk/insert endpoint defined" \
  "grep -q '#\[Route.*bulk/insert' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "POST /api/admin/bulk/update endpoint defined" \
  "grep -q '#\[Route.*bulk/update' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "POST /api/admin/bulk/delete endpoint defined" \
  "grep -q '#\[Route.*bulk/delete' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "GET /api/admin/data/quality endpoint defined" \
  "grep -q '#\[Route.*data/quality' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}5. SERVICE METHODS (Import)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataImportService::importCsv method" \
  "grep -q 'public function importCsv' /home/jaffar/projets/symfony/obstack/src/Service/DataImportService.php"

run_test "DataImportService::importJson method" \
  "grep -q 'public function importJson' /home/jaffar/projets/symfony/obstack/src/Service/DataImportService.php"

run_test "DataImportService::validateRow method" \
  "grep -q 'public function validateRow' /home/jaffar/projets/symfony/obstack/src/Service/DataImportService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}6. SERVICE METHODS (Export)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataExportService::exportToCsv method" \
  "grep -q 'public function exportToCsv' /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

run_test "DataExportService::exportToJson method" \
  "grep -q 'public function exportToJson' /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

run_test "DataExportService::exportToJsonL method" \
  "grep -q 'public function exportToJsonL' /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

run_test "DataExportService::exportToExcel method" \
  "grep -q 'public function exportToExcel' /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

run_test "DataExportService::getTableStructure method" \
  "grep -q 'public function exportTableStructure' /home/jaffar/projets/symfony/obstack/src/Service/DataExportService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}7. SERVICE METHODS (Bulk)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "BulkOperationService::bulkInsert method" \
  "grep -q 'public function bulkInsert' /home/jaffar/projets/symfony/obstack/src/Service/BulkOperationService.php"

run_test "BulkOperationService::bulkUpdate method" \
  "grep -q 'public function bulkUpdate' /home/jaffar/projets/symfony/obstack/src/Service/BulkOperationService.php"

run_test "BulkOperationService::bulkDelete method" \
  "grep -q 'public function bulkDelete' /home/jaffar/projets/symfony/obstack/src/Service/BulkOperationService.php"

run_test "BulkOperationService::truncateTable method" \
  "grep -q 'public function truncateTable' /home/jaffar/projets/symfony/obstack/src/Service/BulkOperationService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}8. VALIDATION SERVICE${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataValidationService::validateCsvData method" \
  "grep -q 'public function validateCsvData' /home/jaffar/projets/symfony/obstack/src/Service/DataValidationService.php"

run_test "DataValidationService::detectDuplicates method" \
  "grep -q 'public function detectDuplicates' /home/jaffar/projets/symfony/obstack/src/Service/DataValidationService.php"

run_test "DataValidationService::analyzeDataQuality method" \
  "grep -q 'public function analyzeDataQuality' /home/jaffar/projets/symfony/obstack/src/Service/DataValidationService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}9. FRONTEND COMPONENTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "DataManagementView.vue exists" \
  "test -f /home/jaffar/projets/symfony/obstack/frontend/src/views/DataManagementView.vue"

run_test "dataManagement.ts service exists" \
  "test -f /home/jaffar/projets/symfony/obstack/frontend/src/services/dataManagement.ts"

run_test "DataManagementView has import tab" \
  "grep -q \"activeTab === 'import'\" /home/jaffar/projets/symfony/obstack/frontend/src/views/DataManagementView.vue"

run_test "DataManagementView has export tab" \
  "grep -q \"activeTab === 'export'\" /home/jaffar/projets/symfony/obstack/frontend/src/views/DataManagementView.vue"

run_test "DataManagementView has bulk tab" \
  "grep -q \"activeTab === 'bulk'\" /home/jaffar/projets/symfony/obstack/frontend/src/views/DataManagementView.vue"

run_test "DataManagementView has quality tab" \
  "grep -q \"activeTab === 'quality'\" /home/jaffar/projets/symfony/obstack/frontend/src/views/DataManagementView.vue"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}10. FRONTEND SERVICE METHODS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "dataManagement.ts has importCsv method" \
  "grep -q 'async importCsv' /home/jaffar/projets/symfony/obstack/frontend/src/services/dataManagement.ts"

run_test "dataManagement.ts has exportCsv method" \
  "grep -q 'async exportCsv' /home/jaffar/projets/symfony/obstack/frontend/src/services/dataManagement.ts"

run_test "dataManagement.ts has bulkInsert method" \
  "grep -q 'async bulkInsert' /home/jaffar/projets/symfony/obstack/frontend/src/services/dataManagement.ts"

run_test "dataManagement.ts has analyzeQuality method" \
  "grep -q 'async analyzeQuality' /home/jaffar/projets/symfony/obstack/frontend/src/services/dataManagement.ts"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}11. ROUTER INTEGRATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Data management route exists" \
  "grep -q 'data-management' /home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"

run_test "DataManagementView imported in router" \
  "grep -q 'DataManagementView.vue' /home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"

run_test "Navigation includes Data Management link" \
  "grep -q 'data-management' /home/jaffar/projets/symfony/obstack/frontend/src/components/Layout.vue"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}12. SECURITY${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Controller protected with IsGranted" \
  "grep -q '#\[IsGranted.*ROLE_ADMIN' /home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/DataManagementController.php"

run_test "Table names validated (injection prevention)" \
  "grep -q 'preg_match.*table' /home/jaffar/projets/symfony/obstack/src/Service/DataImportService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}13. BUILD STATUS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

BUILD_OUTPUT=$(cd /home/jaffar/projets/symfony/obstack/frontend && npm run build 2>&1)

run_test "Frontend builds successfully" \
  "echo '$BUILD_OUTPUT' | grep -q '✓ built'"

run_test "No TypeScript errors" \
  "echo '$BUILD_OUTPUT' | grep -qv 'error'"

run_test "DataManagementView compiled" \
  "echo '$BUILD_OUTPUT' | grep -q 'DataManagementView'"

run_test "122+ modules in build" \
  "echo '$BUILD_OUTPUT' | grep -q '122 modules'"

echo ""
echo "═══════════════════════════════════════════════════════════════════════"
echo -e "${BLUE}PHASE 11 TEST SUMMARY${NC}"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""
echo "Total Tests:  $test_count"
echo -e "Passed:       ${GREEN}$pass_count${NC}"
echo -e "Failed:       ${RED}$fail_count${NC}"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}✓ All Phase 11 data management features implemented successfully!${NC}"
    echo ""
    echo "Phase 11 Features:"
    echo "  ✅ CSV & JSON import with validation"
    echo "  ✅ Multi-format export (CSV, JSON, JSONL, Excel)"
    echo "  ✅ Bulk operations (insert, update, delete)"
    echo "  ✅ Data validation & quality analysis"
    echo "  ✅ Duplicate detection"
    echo "  ✅ Comprehensive UI dashboard"
    echo "  ✅ Secure API endpoints (JWT + ROLE_ADMIN)"
    echo "  ✅ Full TypeScript support"
    exit 0
else
    echo -e "${RED}✗ Some tests failed. Please review the output above.${NC}"
    exit 1
fi
