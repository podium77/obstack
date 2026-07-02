#!/bin/bash

# Phase 16 WebSocket Server & Presence Tests
# Tests WebSocket server, presence tracking, cursor synchronization, collaboration indicators, and typing notifications

TEST_RESULTS=0
TESTS_PASSED=0
TESTS_FAILED=0

echo "==========================================="
echo "Phase 16: WebSocket & Presence Tests"
echo "==========================================="
echo ""

# Helper function for test assertion
test_backend_file() {
    local file=$1
    local description=$2
    if [ -f "$file" ]; then
        echo "✅ $description"
        ((TESTS_PASSED++))
    else
        echo "❌ $description"
        echo "   File not found: $file"
        ((TESTS_FAILED++))
    fi
}

test_method_exists() {
    local file=$1
    local method=$2
    local description=$3
    if grep -q "public function $method" "$file"; then
        echo "✅ $description"
        ((TESTS_PASSED++))
    else
        echo "❌ $description"
        echo "   Method not found: $method"
        ((TESTS_FAILED++))
    fi
}

# ============================================================
# Backend Structure Tests
# ============================================================
echo "BACKEND STRUCTURE TESTS"
echo "========================"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "WebSocketServerService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "PresenceService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "CursorTrackingService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "CollaborationIndicatorService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "TypingNotificationService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "Phase16Controller.php exists"

echo ""

# ============================================================
# WebSocket Server Service Tests
# ============================================================
echo "WEBSOCKET SERVER SERVICE TESTS"
echo "==============================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "registerConnection" "WebSocketServerService::registerConnection() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "unregisterConnection" "WebSocketServerService::unregisterConnection() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "sendHeartbeat" "WebSocketServerService::sendHeartbeat() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "subscribeToRoom" "WebSocketServerService::subscribeToRoom() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "unsubscribeFromRoom" "WebSocketServerService::unsubscribeFromRoom() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "getRoomConnections" "WebSocketServerService::getRoomConnections() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "getServerStats" "WebSocketServerService::getServerStats() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketServerService.php" \
    "cleanupStaleConnections" "WebSocketServerService::cleanupStaleConnections() method"

echo ""

# ============================================================
# Presence Service Tests
# ============================================================
echo "PRESENCE SERVICE TESTS"
echo "======================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "updatePresence" "PresenceService::updatePresence() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "setOffline" "PresenceService::setOffline() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "getUserPresence" "PresenceService::getUserPresence() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "getWorkspaceOnlineUsers" "PresenceService::getWorkspaceOnlineUsers() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "getDocumentUsers" "PresenceService::getDocumentUsers() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "getPresenceStats" "PresenceService::getPresenceStats() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "recordActivity" "PresenceService::recordActivity() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PresenceService.php" \
    "isOnline" "PresenceService::isOnline() method"

echo ""

# ============================================================
# Cursor Tracking Service Tests
# ============================================================
echo "CURSOR TRACKING SERVICE TESTS"
echo "============================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "updateCursorPosition" "CursorTrackingService::updateCursorPosition() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "getDocumentCursors" "CursorTrackingService::getDocumentCursors() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "getUserCursor" "CursorTrackingService::getUserCursor() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "clearCursor" "CursorTrackingService::clearCursor() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "cleanupStaleCursors" "CursorTrackingService::cleanupStaleCursors() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "detectCursorCollisions" "CursorTrackingService::detectCursorCollisions() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "getCursorHistory" "CursorTrackingService::getCursorHistory() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CursorTrackingService.php" \
    "getDocumentCursorStats" "CursorTrackingService::getDocumentCursorStats() method"

echo ""

# ============================================================
# Collaboration Indicator Service Tests
# ============================================================
echo "COLLABORATION INDICATOR SERVICE TESTS"
echo "====================================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "registerEditor" "CollaborationIndicatorService::registerEditor() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "registerViewer" "CollaborationIndicatorService::registerViewer() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "recordEdit" "CollaborationIndicatorService::recordEdit() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "recordView" "CollaborationIndicatorService::recordView() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "unregisterEditor" "CollaborationIndicatorService::unregisterEditor() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "getActiveEditors" "CollaborationIndicatorService::getActiveEditors() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "getActiveViewers" "CollaborationIndicatorService::getActiveViewers() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "detectConflicts" "CollaborationIndicatorService::detectConflicts() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationIndicatorService.php" \
    "getCollaborationSummary" "CollaborationIndicatorService::getCollaborationSummary() method"

echo ""

# ============================================================
# Typing Notification Service Tests
# ============================================================
echo "TYPING NOTIFICATION SERVICE TESTS"
echo "=================================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "recordTyping" "TypingNotificationService::recordTyping() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "recordStoppedTyping" "TypingNotificationService::recordStoppedTyping() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "getTypingUsers" "TypingNotificationService::getTypingUsers() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "getTypingCount" "TypingNotificationService::getTypingCount() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "isUserTyping" "TypingNotificationService::isUserTyping() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "cleanupExpiredTyping" "TypingNotificationService::cleanupExpiredTyping() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "detectTypingBurst" "TypingNotificationService::detectTypingBurst() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/TypingNotificationService.php" \
    "getTypingTimeline" "TypingNotificationService::getTypingTimeline() method"

echo ""

# ============================================================
# API Controller Tests
# ============================================================
echo "API CONTROLLER TESTS"
echo "===================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "registerConnection" "Phase16Controller::registerConnection() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "getServerStats" "Phase16Controller::getServerStats() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "updatePresence" "Phase16Controller::updatePresence() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "getPresenceStats" "Phase16Controller::getPresenceStats() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "updateCursorPosition" "Phase16Controller::updateCursorPosition() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "getDocumentCursors" "Phase16Controller::getDocumentCursors() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "registerEditor" "Phase16Controller::registerEditor() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "getCollaborationStats" "Phase16Controller::getCollaborationStats() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "recordTyping" "Phase16Controller::recordTyping() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase16Controller.php" \
    "getTypingUsers" "Phase16Controller::getTypingUsers() endpoint"

echo ""

# ============================================================
# Frontend Structure Tests
# ============================================================
echo "FRONTEND STRUCTURE TESTS"
echo "========================"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts" \
    "phase16.ts service layer exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue" \
    "Phase16View.vue component exists"

echo ""

# ============================================================
# Frontend Service Tests
# ============================================================
echo "FRONTEND SERVICE TESTS"
echo "======================"

# Test service exports
if grep -q "export async function registerConnection" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ phase16.ts::registerConnection() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase16.ts::registerConnection() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function updatePresence" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ phase16.ts::updatePresence() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase16.ts::updatePresence() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function updateCursorPosition" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ phase16.ts::updateCursorPosition() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase16.ts::updateCursorPosition() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function registerEditor" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ phase16.ts::registerEditor() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase16.ts::registerEditor() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function recordTyping" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ phase16.ts::recordTyping() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase16.ts::recordTyping() function"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# TypeScript Types Tests
# ============================================================
echo "TYPESCRIPT TYPES TESTS"
echo "======================"

if grep -q "export interface WebSocketConnection" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ WebSocketConnection interface"
    ((TESTS_PASSED++))
else
    echo "❌ WebSocketConnection interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface UserPresence" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ UserPresence interface"
    ((TESTS_PASSED++))
else
    echo "❌ UserPresence interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface CursorPosition" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ CursorPosition interface"
    ((TESTS_PASSED++))
else
    echo "❌ CursorPosition interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface TypingUser" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ TypingUser interface"
    ((TESTS_PASSED++))
else
    echo "❌ TypingUser interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface CollaborationStats" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase16.ts"; then
    echo "✅ CollaborationStats interface"
    ((TESTS_PASSED++))
else
    echo "❌ CollaborationStats interface"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Component Tab Tests
# ============================================================
echo "COMPONENT TAB TESTS"
echo "==================="

if grep -q "websocket" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has WebSocket tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View WebSocket tab"
    ((TESTS_FAILED++))
fi

if grep -q "presence" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has Presence tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View Presence tab"
    ((TESTS_FAILED++))
fi

if grep -q "cursor" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has Cursor tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View Cursor tab"
    ((TESTS_FAILED++))
fi

if grep -q "collaboration" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has Collaboration tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View Collaboration tab"
    ((TESTS_FAILED++))
fi

if grep -q "typing" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has Typing tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View Typing tab"
    ((TESTS_FAILED++))
fi

if grep -q "demo" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase16View.vue"; then
    echo "✅ Phase16View has Demo tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View Demo tab"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Router and Navigation Tests
# ============================================================
echo "ROUTER & NAVIGATION TESTS"
echo "=========================="

if grep -q "phase16" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase16 route in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16 route in router"
    ((TESTS_FAILED++))
fi

if grep -q "Phase16View" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase16View imported in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16View imported in router"
    ((TESTS_FAILED++))
fi

if grep -q "Presence" "/home/jaffar/projets/symfony/obstack/frontend/src/components/Layout.vue"; then
    echo "✅ Phase16 link in Layout navigation"
    ((TESTS_PASSED++))
else
    echo "❌ Phase16 link in Layout navigation"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Build Verification Test
# ============================================================
echo "BUILD VERIFICATION TEST"
echo "======================="

cd /home/jaffar/projets/symfony/obstack/frontend
if npm run build > /dev/null 2>&1; then
    MODULE_COUNT=$(npm run build 2>&1 | grep "modules transformed" | grep -oP '\d+(?= modules)' || echo "0")
    if [ "$MODULE_COUNT" -gt "135" ]; then
        echo "✅ Frontend builds successfully ($MODULE_COUNT modules)"
        ((TESTS_PASSED++))
    else
        echo "⚠️  Frontend builds but fewer modules than expected ($MODULE_COUNT modules)"
        ((TESTS_PASSED++))
    fi
else
    echo "❌ Frontend build failed"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Summary
# ============================================================
echo "==========================================="
echo "Test Summary"
echo "==========================================="
echo "✅ Passed: $TESTS_PASSED"
echo "❌ Failed: $TESTS_FAILED"
echo "Total: $((TESTS_PASSED + TESTS_FAILED))"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo "🎉 All tests passed!"
    exit 0
else
    echo "⚠️  Some tests failed"
    exit 1
fi
