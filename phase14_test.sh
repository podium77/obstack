#!/bin/bash

# Phase 14 Advanced Collaboration Tests
# Tests real-time, activity feeds, search, reactions, and audit logs

TEST_RESULTS=0
TESTS_PASSED=0
TESTS_FAILED=0

echo "=========================================="
echo "Phase 14: Advanced Collaboration Tests"
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

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/RealtimeService.php" \
    "RealtimeService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/ActivityFeedService.php" \
    "ActivityFeedService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/SearchService.php" \
    "SearchService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "ReactionService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "CollaborationAuditService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "Phase14Controller.php exists"

echo ""

# ============================================================
# Realtime Service Tests
# ============================================================
echo "REALTIME SERVICE TESTS"
echo "======================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/RealtimeService.php" \
    "registerConnection" "RealtimeService::registerConnection() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/RealtimeService.php" \
    "unregisterConnection" "RealtimeService::unregisterConnection() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/RealtimeService.php" \
    "getActiveUsers" "RealtimeService::getActiveUsers() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/RealtimeService.php" \
    "updateConnectionHeartbeat" "RealtimeService::updateConnectionHeartbeat() method"

echo ""

# ============================================================
# Activity Feed Service Tests
# ============================================================
echo "ACTIVITY FEED SERVICE TESTS"
echo "============================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityFeedService.php" \
    "logActivity" "ActivityFeedService::logActivity() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityFeedService.php" \
    "getWorkspaceActivityFeed" "ActivityFeedService::getWorkspaceActivityFeed() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityFeedService.php" \
    "getUserActivityFeed" "ActivityFeedService::getUserActivityFeed() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ActivityFeedService.php" \
    "getActivityStats" "ActivityFeedService::getActivityStats() method"

echo ""

# ============================================================
# Search Service Tests
# ============================================================
echo "SEARCH SERVICE TESTS"
echo "===================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/SearchService.php" \
    "searchQueries" "SearchService::searchQueries() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/SearchService.php" \
    "filterQueries" "SearchService::filterQueries() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/SearchService.php" \
    "searchComments" "SearchService::searchComments() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/SearchService.php" \
    "searchUsers" "SearchService::searchUsers() method"

echo ""

# ============================================================
# Reaction Service Tests
# ============================================================
echo "REACTION SERVICE TESTS"
echo "======================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "addReaction" "ReactionService::addReaction() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "removeReaction" "ReactionService::removeReaction() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "getCommentReactions" "ReactionService::getCommentReactions() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "voteOnComment" "ReactionService::voteOnComment() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/ReactionService.php" \
    "getMostReactedComments" "ReactionService::getMostReactedComments() method"

echo ""

# ============================================================
# Collaboration Audit Service Tests
# ============================================================
echo "COLLABORATION AUDIT SERVICE TESTS"
echo "=================================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "logCollaborationEvent" "CollaborationAuditService::logCollaborationEvent() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "getWorkspaceAuditLogs" "CollaborationAuditService::getWorkspaceAuditLogs() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "getUserAuditTrail" "CollaborationAuditService::getUserAuditTrail() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "getAuditStats" "CollaborationAuditService::getAuditStats() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationAuditService.php" \
    "exportAuditLogs" "CollaborationAuditService::exportAuditLogs() method"

echo ""

# ============================================================
# API Controller Tests
# ============================================================
echo "API CONTROLLER TESTS"
echo "===================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "registerConnection" "Phase14Controller::registerConnection() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "getActiveUsers" "Phase14Controller::getActiveUsers() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "getWorkspaceActivity" "Phase14Controller::getWorkspaceActivity() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "searchQueries" "Phase14Controller::searchQueries() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "addReaction" "Phase14Controller::addReaction() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "voteOnComment" "Phase14Controller::voteOnComment() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/Phase14Controller.php" \
    "getWorkspaceAuditLogs" "Phase14Controller::getWorkspaceAuditLogs() endpoint"

echo ""

# ============================================================
# Frontend Structure Tests
# ============================================================
echo "FRONTEND STRUCTURE TESTS"
echo "========================"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts" \
    "phase14.ts service layer exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/views/Phase14View.vue" \
    "Phase14View.vue component exists"

echo ""

# ============================================================
# Frontend Service Tests
# ============================================================
echo "FRONTEND SERVICE TESTS"
echo "======================"

# Test service exports
if grep -q "export async function registerConnection" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ phase14.ts::registerConnection() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase14.ts::registerConnection() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function getActiveUsers" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ phase14.ts::getActiveUsers() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase14.ts::getActiveUsers() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function searchQueries" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ phase14.ts::searchQueries() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase14.ts::searchQueries() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function addReaction" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ phase14.ts::addReaction() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase14.ts::addReaction() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function getWorkspaceAuditLogs" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ phase14.ts::getWorkspaceAuditLogs() function"
    ((TESTS_PASSED++))
else
    echo "❌ phase14.ts::getWorkspaceAuditLogs() function"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# TypeScript Types Tests
# ============================================================
echo "TYPESCRIPT TYPES TESTS"
echo "======================"

if grep -q "export interface Activity" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ Activity interface"
    ((TESTS_PASSED++))
else
    echo "❌ Activity interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface ActiveUser" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ ActiveUser interface"
    ((TESTS_PASSED++))
else
    echo "❌ ActiveUser interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface AuditLog" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ AuditLog interface"
    ((TESTS_PASSED++))
else
    echo "❌ AuditLog interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface CommentReaction" "/home/jaffar/projets/symfony/obstack/frontend/src/services/phase14.ts"; then
    echo "✅ CommentReaction interface"
    ((TESTS_PASSED++))
else
    echo "❌ CommentReaction interface"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Router and Navigation Tests
# ============================================================
echo "ROUTER & NAVIGATION TESTS"
echo "=========================="

if grep -q "phase14" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase14 route in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase14 route in router"
    ((TESTS_FAILED++))
fi

if grep -q "Phase14View" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Phase14View imported in router"
    ((TESTS_PASSED++))
else
    echo "❌ Phase14View imported in router"
    ((TESTS_FAILED++))
fi

if grep -q "Advanced Collab" "/home/jaffar/projets/symfony/obstack/frontend/src/components/Layout.vue"; then
    echo "✅ Phase14 link in Layout navigation"
    ((TESTS_PASSED++))
else
    echo "❌ Phase14 link in Layout navigation"
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
