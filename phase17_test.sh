#!/bin/bash

# Phase 17: Real-time Messaging & WebSocket Server Test Script
# Tests all backend services, API controllers, and frontend integration

WORKSPACE="/home/jaffar/projets/symfony/obstack"
BIN="$WORKSPACE/bin"
PROJECT_DIR="$WORKSPACE"

echo "=========================================="
echo "Phase 17 Test Suite"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Test function
run_test() {
    local test_name=$1
    local test_command=$2
    
    if eval "$test_command"; then
        echo -e "${GREEN}✅ $test_name${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ $test_name${NC}"
        ((TESTS_FAILED++))
    fi
}

echo "=== BACKEND STRUCTURE TESTS ==="

# Test 1: RealtimeMessagingService exists
run_test "RealtimeMessagingService exists" \
    "test -f $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 2: ConflictResolutionService exists
run_test "ConflictResolutionService exists" \
    "test -f $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 3: MessageQueueService exists
run_test "MessageQueueService exists" \
    "test -f $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 4: WebSocketGatewayService exists
run_test "WebSocketGatewayService exists" \
    "test -f $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 5: Phase17Controller exists
run_test "Phase17Controller exists" \
    "test -f $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

echo ""
echo "=== REALTIMEMESSAGINGSERVICE TESTS ==="

# Test 6: sendMessage method exists
run_test "sendMessage method exists" \
    "grep -q 'public function sendMessage' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 7: broadcastMessage method exists
run_test "broadcastMessage method exists" \
    "grep -q 'public function broadcastMessage' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 8: getPendingMessages method exists
run_test "getPendingMessages method exists" \
    "grep -q 'public function getPendingMessages' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 9: getMessageHistory method exists
run_test "getMessageHistory method exists" \
    "grep -q 'public function getMessageHistory' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 10: markDelivered method exists
run_test "markDelivered method exists" \
    "grep -q 'public function markDelivered' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 11: markAcknowledged method exists
run_test "markAcknowledged method exists" \
    "grep -q 'public function markAcknowledged' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 12: getDeliveryStats method exists
run_test "getDeliveryStats method exists" \
    "grep -q 'public function getDeliveryStats' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

# Test 13: cleanupExpiredMessages method exists
run_test "cleanupExpiredMessages method exists" \
    "grep -q 'public function cleanupExpiredMessages' $PROJECT_DIR/src/Service/RealtimeMessagingService.php"

echo ""
echo "=== CONFLICTRESOLUTIONSERVICE TESTS ==="

# Test 14: recordOperation method exists
run_test "recordOperation method exists" \
    "grep -q 'public function recordOperation' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 15: detectConflicts method exists
run_test "detectConflicts method exists" \
    "grep -q 'public function detectConflicts' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 16: transformOperation method exists
run_test "transformOperation method exists" \
    "grep -q 'public function transformOperation' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 17: resolveConflict method exists
run_test "resolveConflict method exists" \
    "grep -q 'public function resolveConflict' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 18: getOperationHistory method exists
run_test "getOperationHistory method exists" \
    "grep -q 'public function getOperationHistory' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 19: getConflictHistory method exists
run_test "getConflictHistory method exists" \
    "grep -q 'public function getConflictHistory' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

# Test 20: getConflictStats method exists
run_test "getConflictStats method exists" \
    "grep -q 'public function getConflictStats' $PROJECT_DIR/src/Service/ConflictResolutionService.php"

echo ""
echo "=== MESSAGEQUEUESERVICE TESTS ==="

# Test 21: enqueue method exists
run_test "enqueue method exists" \
    "grep -q 'public function enqueue' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 22: dequeue method exists
run_test "dequeue method exists" \
    "grep -q 'public function dequeue' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 23: markProcessed method exists
run_test "markProcessed method exists" \
    "grep -q 'public function markProcessed' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 24: markFailed method exists
run_test "markFailed method exists" \
    "grep -q 'public function markFailed' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 25: getQueueStats method exists
run_test "getQueueStats method exists" \
    "grep -q 'public function getQueueStats' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 26: processQueue method exists
run_test "processQueue method exists" \
    "grep -q 'public function processQueue' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 27: getQueueByStatus method exists
run_test "getQueueByStatus method exists" \
    "grep -q 'public function getQueueByStatus' $PROJECT_DIR/src/Service/MessageQueueService.php"

# Test 28: getFailedMessages method exists
run_test "getFailedMessages method exists" \
    "grep -q 'public function getFailedMessages' $PROJECT_DIR/src/Service/MessageQueueService.php"

echo ""
echo "=== WEBSOCKETGATEWAYSERVICE TESTS ==="

# Test 29: registerEventListener method exists
run_test "registerEventListener method exists" \
    "grep -q 'public function registerEventListener' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 30: emitEvent method exists
run_test "emitEvent method exists" \
    "grep -q 'public function emitEvent' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 31: getEventListeners method exists
run_test "getEventListeners method exists" \
    "grep -q 'public function getEventListeners' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 32: registerNamespace method exists
run_test "registerNamespace method exists" \
    "grep -q 'public function registerNamespace' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 33: createRoom method exists
run_test "createRoom method exists" \
    "grep -q 'public function createRoom' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 34: getGatewayStats method exists
run_test "getGatewayStats method exists" \
    "grep -q 'public function getGatewayStats' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

# Test 35: getConnectionThroughput method exists
run_test "getConnectionThroughput method exists" \
    "grep -q 'public function getConnectionThroughput' $PROJECT_DIR/src/Service/WebSocketGatewayService.php"

echo ""
echo "=== PHASE17CONTROLLER TESTS ==="

# Test 36: sendMessage endpoint exists
run_test "sendMessage endpoint exists" \
    "grep -q 'messaging_send' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 37: broadcastMessage endpoint exists
run_test "broadcastMessage endpoint exists" \
    "grep -q 'messaging_broadcast' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 38: detectConflicts endpoint exists
run_test "detectConflicts endpoint exists" \
    "grep -q 'conflicts_detect' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 39: transformOperation endpoint exists
run_test "transformOperation endpoint exists" \
    "grep -q 'conflicts_transform' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 40: enqueueMessage endpoint exists
run_test "enqueueMessage endpoint exists" \
    "grep -q 'queue_enqueue' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 41: processQueue endpoint exists
run_test "processQueue endpoint exists" \
    "grep -q 'queue_process' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 42: registerEventListener endpoint exists
run_test "registerEventListener endpoint exists" \
    "grep -q 'gateway_register_listener' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

# Test 43: emitEvent endpoint exists
run_test "emitEvent endpoint exists" \
    "grep -q 'gateway_emit_event' $PROJECT_DIR/src/Controller/Admin/API/Phase17Controller.php"

echo ""
echo "=== FRONTEND STRUCTURE TESTS ==="

# Test 44: phase17.ts service exists
run_test "phase17.ts service exists" \
    "test -f $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 45: Phase17View.vue component exists
run_test "Phase17View.vue component exists" \
    "test -f $PROJECT_DIR/frontend/src/views/Phase17View.vue"

echo ""
echo "=== FRONTEND SERVICE TESTS ==="

# Test 46: sendMessage function exists
run_test "sendMessage function exists" \
    "grep -q 'export async function sendMessage' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 47: broadcastMessage function exists
run_test "broadcastMessage function exists" \
    "grep -q 'export async function broadcastMessage' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 48: detectConflicts function exists
run_test "detectConflicts function exists" \
    "grep -q 'export async function detectConflicts' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 49: enqueueMessage function exists
run_test "enqueueMessage function exists" \
    "grep -q 'export async function enqueueMessage' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 50: emitEvent function exists
run_test "emitEvent function exists" \
    "grep -q 'export async function emitEvent' $PROJECT_DIR/frontend/src/services/phase17.ts"

echo ""
echo "=== TYPESCRIPT TYPES TESTS ==="

# Test 51: RealtimeMessage interface defined
run_test "RealtimeMessage interface defined" \
    "grep -q 'export interface RealtimeMessage' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 52: EditOperation interface defined
run_test "EditOperation interface defined" \
    "grep -q 'export interface EditOperation' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 53: ConflictDetection interface defined
run_test "ConflictDetection interface defined" \
    "grep -q 'export interface ConflictDetection' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 54: QueueMessage interface defined
run_test "QueueMessage interface defined" \
    "grep -q 'export interface QueueMessage' $PROJECT_DIR/frontend/src/services/phase17.ts"

# Test 55: WebSocketGatewayStats interface defined
run_test "WebSocketGatewayStats interface defined" \
    "grep -q 'export interface WebSocketGatewayStats' $PROJECT_DIR/frontend/src/services/phase17.ts"

echo ""
echo "=== COMPONENT TAB TESTS ==="

# Test 56: Messaging tab exists
run_test "Messaging tab exists" \
    "grep -q \"activeTab === 'messaging'\" $PROJECT_DIR/frontend/src/views/Phase17View.vue"

# Test 57: Conflicts tab exists
run_test "Conflicts tab exists" \
    "grep -q \"activeTab === 'conflicts'\" $PROJECT_DIR/frontend/src/views/Phase17View.vue"

# Test 58: Queue tab exists
run_test "Queue tab exists" \
    "grep -q \"activeTab === 'queue'\" $PROJECT_DIR/frontend/src/views/Phase17View.vue"

# Test 59: Gateway tab exists
run_test "Gateway tab exists" \
    "grep -q \"activeTab === 'gateway'\" $PROJECT_DIR/frontend/src/views/Phase17View.vue"

# Test 60: Demo tab exists
run_test "Demo tab exists" \
    "grep -q \"activeTab === 'demo'\" $PROJECT_DIR/frontend/src/views/Phase17View.vue"

echo ""
echo "=== ROUTER & NAVIGATION TESTS ==="

# Test 61: Phase17 route in router
run_test "Phase17 route in router" \
    "grep -q \"path: 'phase17'\" $PROJECT_DIR/frontend/src/router/index.ts"

# Test 62: Phase17 component in router
run_test "Phase17 component in router" \
    "grep -q \"Phase17View.vue\" $PROJECT_DIR/frontend/src/router/index.ts"

# Test 63: Phase17 navigation link in Layout
run_test "Phase17 navigation link in Layout" \
    "grep -q \"to=\\\"/phase17\\\"\" $PROJECT_DIR/frontend/src/components/Layout.vue"

echo ""
echo "=== BUILD VERIFICATION TEST ==="

# Test 64: Frontend build check (verify node_modules exists)
run_test "Frontend node_modules exists" \
    "test -d $PROJECT_DIR/frontend/node_modules"

echo ""
echo "=========================================="
echo "SUMMARY"
echo "=========================================="
TOTAL=$((TESTS_PASSED + TESTS_FAILED))
echo "Total Tests: $TOTAL"
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo "─────────────────────────────────────────"
PERCENTAGE=$((TESTS_PASSED * 100 / TOTAL))
echo "Success Rate: $PERCENTAGE%"
echo "=========================================="

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All Phase 17 tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed.${NC}"
    exit 1
fi
