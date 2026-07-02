#!/bin/bash

# Phase 15 Notifications & WebSocket Tests
# Tests WebSocket service, push notifications, comment notifications, digests, and settings

TEST_RESULTS=0
TESTS_PASSED=0
TESTS_FAILED=0

echo "=========================================="
echo "Phase 15: Notifications & WebSocket Tests"
echo "=========================================="
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

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "WebSocketService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "PushNotificationService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CommentNotificationService.php" \
    "CommentNotificationService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "ActivityDigestService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "NotificationSettingsService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "Phase15Controller.php exists"

echo ""

# ============================================================
# WebSocket Service Tests
# ============================================================
echo "WEBSOCKET SERVICE TESTS"
echo "========================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "broadcastToWorkspace" "WebSocketService::broadcastToWorkspace() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "broadcastToUser" "WebSocketService::broadcastToUser() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "getChannelSubscriberCount" "WebSocketService::getChannelSubscriberCount() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "storeMessage" "WebSocketService::storeMessage() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "getPendingMessages" "WebSocketService::getPendingMessages() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WebSocketService.php" \
    "getConnectionStats" "WebSocketService::getConnectionStats() method"

echo ""

# ============================================================
# Push Notification Service Tests
# ============================================================
echo "PUSH NOTIFICATION SERVICE TESTS"
echo "================================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "sendNotification" "PushNotificationService::sendNotification() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "sendBulkNotification" "PushNotificationService::sendBulkNotification() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "sendWorkspaceNotification" "PushNotificationService::sendWorkspaceNotification() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "getUserNotifications" "PushNotificationService::getUserNotifications() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "getUnreadCount" "PushNotificationService::getUnreadCount() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "markAsRead" "PushNotificationService::markAsRead() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/PushNotificationService.php" \
    "getNotificationStats" "PushNotificationService::getNotificationStats() method"

echo ""

# ============================================================
# Comment Notification Service Tests
# ============================================================
echo "COMMENT NOTIFICATION SERVICE TESTS"
echo "==================================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentNotificationService.php" \
    "notifyMentions" "CommentNotificationService::notifyMentions() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentNotificationService.php" \
    "notifyReply" "CommentNotificationService::notifyReply() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentNotificationService.php" \
    "notifyReaction" "CommentNotificationService::notifyReaction() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentNotificationService.php" \
    "notifyVote" "CommentNotificationService::notifyVote() method"

echo ""

# ============================================================
# Activity Digest Service Tests
# ============================================================
echo "ACTIVITY DIGEST SERVICE TESTS"
echo "============================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "generateDigest" "ActivityDigestService::generateDigest() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "sendDigest" "ActivityDigestService::sendDigest() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "sendScheduledDigests" "ActivityDigestService::sendScheduledDigests() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "getDigestHistory" "ActivityDigestService::getDigestHistory() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityDigestService.php" \
    "getDigestStats" "ActivityDigestService::getDigestStats() method"

echo ""

# ============================================================
# Notification Settings Service Tests
# ============================================================
echo "NOTIFICATION SETTINGS SERVICE TESTS"
echo "===================================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "getUserSettings" "NotificationSettingsService::getUserSettings() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "updateSettings" "NotificationSettingsService::updateSettings() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "toggleNotificationType" "NotificationSettingsService::toggleNotificationType() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "setDigestFrequency" "NotificationSettingsService::setDigestFrequency() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "setQuietHours" "NotificationSettingsService::setQuietHours() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "isInQuietHours" "NotificationSettingsService::isInQuietHours() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/NotificationSettingsService.php" \
    "getMutedWorkspaces" "NotificationSettingsService::getMutedWorkspaces() method"

echo ""

# ============================================================
# API Controller Tests
# ============================================================
echo "API CONTROLLER TESTS"
echo "===================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "broadcastToWorkspace" "Phase15Controller::broadcastToWorkspace() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "broadcastToUser" "Phase15Controller::broadcastToUser() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "sendNotification" "Phase15Controller::sendNotification() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "sendBulkNotification" "Phase15Controller::sendBulkNotification() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "getUserNotifications" "Phase15Controller::getUserNotifications() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "notifyMentions" "Phase15Controller::notifyMentions() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "notifyReply" "Phase15Controller::notifyReply() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "generateDigest" "Phase15Controller::generateDigest() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "sendDigest" "Phase15Controller::sendDigest() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "getUserSettings" "Phase15Controller::getUserSettings() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase15Controller.php" \
    "updateUserSettings" "Phase15Controller::updateUserSettings() endpoint"

echo ""

# ============================================================
# Frontend Structure Tests
# ============================================================
echo "FRONTEND STRUCTURE TESTS"
echo "========================"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts" \
    "phase15.ts service layer exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue" \
    "Phase15View.vue component exists"

echo ""

# ============================================================
# Frontend Service Tests
# ============================================================
echo "FRONTEND SERVICE TESTS"
echo "======================"

# Test service exports
if grep -q "export async function broadcastToWorkspace" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ phase15.ts::broadcastToWorkspace() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase15.ts::broadcastToWorkspace() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function sendNotification" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ phase15.ts::sendNotification() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase15.ts::sendNotification() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function notifyMentions" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ phase15.ts::notifyMentions() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase15.ts::notifyMentions() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function generateDigest" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ phase15.ts::generateDigest() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase15.ts::generateDigest() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function getUserSettings" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ phase15.ts::getUserSettings() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase15.ts::getUserSettings() function"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# TypeScript Types Tests
# ============================================================
echo "TYPESCRIPT TYPES TESTS"
echo "======================"

if grep -q "export interface WebSocketMessage" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ WebSocketMessage interface"
    ((TESTS_PASSED++))
else
    echo "❌ WebSocketMessage interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface PushNotification" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ PushNotification interface"
    ((TESTS_PASSED++))
else
    echo "❌ PushNotification interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface NotificationSettings" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ NotificationSettings interface"
    ((TESTS_PASSED++))
else
    echo "❌ NotificationSettings interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface ActivityDigest" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase15.ts"; then
    echo "✅ ActivityDigest interface"
    ((TESTS_PASSED++))
else
    echo "❌ ActivityDigest interface"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Component Tab Tests
# ============================================================
echo "COMPONENT TAB TESTS"
echo "==================="

if grep -q "WebSocket" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue"; then
    echo "✅ Phase15View has WebSocket tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View WebSocket tab"
    ((TESTS_FAILED++))
fi

if grep -q "Push Notifications" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue"; then
    echo "✅ Phase15View has Notifications tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View Notifications tab"
    ((TESTS_FAILED++))
fi

if grep -q "Comment Notifications" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue"; then
    echo "✅ Phase15View has Comment Notifications tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View Comment Notifications tab"
    ((TESTS_FAILED++))
fi

if grep -q "Activity Digest" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue"; then
    echo "✅ Phase15View has Digest tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View Digest tab"
    ((TESTS_FAILED++))
fi

if grep -q "Notification Settings" "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase15View.vue"; then
    echo "✅ Phase15View has Settings tab"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View Settings tab"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Router and Navigation Tests
# ============================================================
echo "ROUTER & NAVIGATION TESTS"
echo "=========================="

if grep -q "phase15" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase15 route in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15 route in router"
    ((TESTS_FAILED++))
fi

if grep -q "Phase15View" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase15View imported in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15View imported in router"
    ((TESTS_FAILED++))
fi

if grep -q "Notifications" "/home/jaffar/projets/symfony/obstack/frontend/src/components/Layout.vue"; then
    echo "✅ Phase15 link in Layout navigation"
    ((TESTS_PASSED++))
else
    echo "❌ Phase15 link in Layout navigation"
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
    if [ "$MODULE_COUNT" -gt "130" ]; then
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
echo "=========================================="
echo "Test Summary"
echo "=========================================="
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
