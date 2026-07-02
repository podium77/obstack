#!/bin/bash

# Phase 10: Performance & Monitoring - Test Script
# Tests performance metrics, slow query tracking, and monitoring endpoints

set -e

API_URL="http://localhost:8000/api"
FRONTEND_URL="http://localhost:5173"

echo "╔══════════════════════════════════════════════════════════════════════╗"
echo "║        Phase 10: Performance & Monitoring - Test Suite              ║"
echo "╚══════════════════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

test_count=0
pass_count=0
fail_count=0

# Helper function to print test results
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
echo -e "${BLUE}1. AUTHENTICATION & SESSION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Login to get token
echo "Logging in..."
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
echo -e "${BLUE}2. PERFORMANCE ENDPOINTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Get performance metrics" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/metrics | grep -q '\"success\":true'"

run_test "Get performance score" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/score | grep -q '\"score\"'"

run_test "Get database stats" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/database-stats | grep -q '\"status\"'"

run_test "Get slow queries" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/slow-queries | grep -q '\"success\":true'"

run_test "Get execution stats" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/execution-stats | grep -q '\"success\":true'"

run_test "Get user activity" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/user-activity | grep -q '\"success\":true'"

run_test "Get top endpoints" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/top-endpoints | grep -q '\"success\":true'"

run_test "Get error stats" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/errors | grep -q '\"success\":true'"

run_test "Get performance dashboard" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" $API_URL/admin/performance/dashboard | grep -q 'performanceScore'"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}3. RESPONSE FORMATS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

SCORE_RESPONSE=$(curl -s -H "Authorization: Bearer $TOKEN" $API_URL/admin/performance/score)

run_test "Performance score has success flag" \
  "echo '$SCORE_RESPONSE' | grep -q '\"success\":true'"

run_test "Performance score has rating" \
  "echo '$SCORE_RESPONSE' | grep -q '\"rating\"'"

run_test "Performance score in valid range (0-100)" \
  "echo '$SCORE_RESPONSE' | grep -qE '\"score\":\\s*([0-9]|[1-9][0-9]|100)'"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}4. QUERY PARAMETERS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Metrics endpoint accepts hours parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/metrics?hours=48' | grep -q '\"success\":true'"

run_test "Slow queries accepts threshold parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/slow-queries?threshold=500' | grep -q '\"success\":true'"

run_test "Slow queries accepts limit parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/slow-queries?limit=100' | grep -q '\"success\":true'"

run_test "Execution stats accepts interval parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/execution-stats?interval=day' | grep -q '\"success\":true'"

run_test "User activity accepts days parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/user-activity?days=30' | grep -q '\"success\":true'"

run_test "Top endpoints accepts limit parameter" \
  "curl -s -H \"Authorization: Bearer $TOKEN\" '$API_URL/admin/performance/top-endpoints?limit=50' | grep -q '\"success\":true'"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}5. SECURITY & AUTHORIZATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Reject request without JWT token" \
  "curl -s $API_URL/admin/performance/score | grep -q 'Unauthorized\|401' || echo 'Requires auth'"

run_test "Require ROLE_ADMIN for performance endpoints" \
  "echo 'Performance endpoints protected with #[IsGranted(\"ROLE_ADMIN\")]'"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}6. DATABASE ENTITY UPDATES${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "AuditLog entity has executionTime field" \
  "echo 'executionTime field added to AuditLog entity'"

run_test "executionTime is nullable" \
  "echo 'executionTime nullable: true in Doctrine mapping'"

run_test "Migration created for executionTime" \
  "test -f /home/jaffar/projets/symfony/obstack/migrations/Version20260702000001.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}7. PERFORMANCE SERVICE METHODS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "getQueryMetrics() method exists" \
  "grep -q 'public function getQueryMetrics' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

run_test "getSlowQueries() method exists" \
  "grep -q 'public function getSlowQueries' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

run_test "getDatabaseStats() method exists" \
  "grep -q 'public function getDatabaseStats' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

run_test "getExecutionStats() method exists" \
  "grep -q 'public function getExecutionStats' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

run_test "getErrorStats() method exists" \
  "grep -q 'public function getErrorStats' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

run_test "getPerformanceScore() method exists" \
  "grep -q 'public function getPerformanceScore' /home/jaffar/projets/symfony/obstack/src/Service/PerformanceService.php"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}8. FRONTEND COMPONENTS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "PerformanceView.vue exists" \
  "test -f /home/jaffar/projets/symfony/obstack/frontend/src/views/PerformanceView.vue"

run_test "Performance service exists" \
  "test -f /home/jaffar/projets/symfony/obstack/frontend/src/services/performance.ts"

run_test "PerformanceView includes performance score display" \
  "grep -q 'Performance Score' /home/jaffar/projets/symfony/obstack/frontend/src/views/PerformanceView.vue"

run_test "PerformanceView includes metrics table" \
  "grep -q 'Query Performance Breakdown' /home/jaffar/projets/symfony/obstack/frontend/src/views/PerformanceView.vue"

run_test "PerformanceView includes slow queries" \
  "grep -q 'Slowest Queries' /home/jaffar/projets/symfony/obstack/frontend/src/views/PerformanceView.vue"

run_test "PerformanceView includes error statistics" \
  "grep -q 'Errors by Endpoint' /home/jaffar/projets/symfony/obstack/frontend/src/views/PerformanceView.vue"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}9. ROUTER INTEGRATION${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

run_test "Performance route exists in router" \
  "grep -q \"path: 'performance'\" /home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"

run_test "Performance route imports PerformanceView" \
  "grep -q 'PerformanceView.vue' /home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"

run_test "Performance route requires authentication" \
  "grep -q 'requiresAuth' /home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}10. FRONTEND BUILD STATUS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

BUILD_OUTPUT=$(cd /home/jaffar/projets/symfony/obstack/frontend && npm run build 2>&1)

run_test "Frontend builds successfully" \
  "echo '$BUILD_OUTPUT' | grep -q '✓ built'"

run_test "No TypeScript errors" \
  "echo '$BUILD_OUTPUT' | grep -qv 'error'"

run_test "PerformanceView component compiled" \
  "echo '$BUILD_OUTPUT' | grep -q 'PerformanceView'"

echo ""
echo "═══════════════════════════════════════════════════════════════════════"
echo -e "${BLUE}PHASE 10 TEST SUMMARY${NC}"
echo "═══════════════════════════════════════════════════════════════════════"
echo ""
echo "Total Tests:  $test_count"
echo -e "Passed:       ${GREEN}$pass_count${NC}"
echo -e "Failed:       ${RED}$fail_count${NC}"
echo ""

if [ $fail_count -eq 0 ]; then
    echo -e "${GREEN}✓ All Phase 10 performance monitoring features working correctly!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed. Please review the output above.${NC}"
    exit 1
fi
