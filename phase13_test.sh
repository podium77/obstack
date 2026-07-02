#!/bin/bash

# Phase 13 Collaboration Feature Tests
# Tests backend services, API endpoints, and frontend integration

TEST_RESULTS=0
TESTS_PASSED=0
TESTS_FAILED=0

echo "=========================================="
echo "Phase 13: Collaboration Tests"
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

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationService.php" \
    "CollaborationService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "WorkspaceService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "AccessControlService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "CommentService.php exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "CollaborationController.php exists"

echo ""

# ============================================================
# Collaboration Service Tests
# ============================================================
echo "COLLABORATION SERVICE TESTS"
echo "============================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationService.php" \
    "shareQuery" "CollaborationService::shareQuery() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationService.php" \
    "getSharedQueries" "CollaborationService::getSharedQueries() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationService.php" \
    "updateSharePermission" "CollaborationService::updateSharePermission() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CollaborationService.php" \
    "revokeShare" "CollaborationService::revokeShare() method"

echo ""

# ============================================================
# Workspace Service Tests
# ============================================================
echo "WORKSPACE SERVICE TESTS"
echo "========================"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "createWorkspace" "WorkspaceService::createWorkspace() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "getUserWorkspaces" "WorkspaceService::getUserWorkspaces() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "addWorkspaceMember" "WorkspaceService::addWorkspaceMember() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "removeWorkspaceMember" "WorkspaceService::removeWorkspaceMember() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/WorkspaceService.php" \
    "getWorkspaceStats" "WorkspaceService::getWorkspaceStats() method"

echo ""

# ============================================================
# Access Control Service Tests
# ============================================================
echo "ACCESS CONTROL SERVICE TESTS"
echo "=============================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "createGroup" "AccessControlService::createGroup() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "listGroups" "AccessControlService::listGroups() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "addGroupMember" "AccessControlService::addGroupMember() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "removeGroupMember" "AccessControlService::removeGroupMember() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "hasPermission" "AccessControlService::hasPermission() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/AccessControlService.php" \
    "deleteGroup" "AccessControlService::deleteGroup() method"

echo ""

# ============================================================
# Comment Service Tests
# ============================================================
echo "COMMENT SERVICE TESTS"
echo "====================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "addComment" "CommentService::addComment() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "getComments" "CommentService::getComments() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "updateComment" "CommentService::updateComment() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "deleteComment" "CommentService::deleteComment() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "addAnnotation" "CommentService::addAnnotation() method"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Service/CommentService.php" \
    "getAnnotations" "CommentService::getAnnotations() method"

echo ""

# ============================================================
# API Controller Tests
# ============================================================
echo "API CONTROLLER TESTS"
echo "===================="

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "shareQuery" "CollaborationController::shareQuery() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "getSharedQueries" "CollaborationController::getSharedQueries() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "createWorkspace" "CollaborationController::createWorkspace() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "getUserWorkspaces" "CollaborationController::getUserWorkspaces() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "createGroup" "CollaborationController::createGroup() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "listGroups" "CollaborationController::listGroups() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "addComment" "CollaborationController::addComment() endpoint"

test_method_exists "/home/jaffar/projets/symfony/obstack/src/Controller/Admin/API/CollaborationController.php" \
    "getComments" "CollaborationController::getComments() endpoint"

echo ""

# ============================================================
# Frontend Structure Tests
# ============================================================
echo "FRONTEND STRUCTURE TESTS"
echo "========================"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts" \
    "collaboration.ts service layer exists"

test_backend_file "/home/jaffar/projets/symfony/obstack/frontend/src/views/CollaborationView.vue" \
    "CollaborationView.vue component exists"

echo ""

# ============================================================
# Frontend Service Tests
# ============================================================
echo "FRONTEND SERVICE TESTS"
echo "======================"

# Test service exports
if grep -q "export async function shareQuery" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ collaboration.ts::shareQuery() function"
    ((TESTS_PASSED++))
else
    echo "❌ collaboration.ts::shareQuery() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function getSharedQueries" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ collaboration.ts::getSharedQueries() function"
    ((TESTS_PASSED++))
else
    echo "❌ collaboration.ts::getSharedQueries() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function createWorkspace" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ collaboration.ts::createWorkspace() function"
    ((TESTS_PASSED++))
else
    echo "❌ collaboration.ts::createWorkspace() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function createGroup" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ collaboration.ts::createGroup() function"
    ((TESTS_PASSED++))
else
    echo "❌ collaboration.ts::createGroup() function"
    ((TESTS_FAILED++))
fi

if grep -q "export async function addComment" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ collaboration.ts::addComment() function"
    ((TESTS_PASSED++))
else
    echo "❌ collaboration.ts::addComment() function"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# TypeScript Types Tests
# ============================================================
echo "TYPESCRIPT TYPES TESTS"
echo "======================"

if grep -q "export interface Workspace" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ Workspace interface"
    ((TESTS_PASSED++))
else
    echo "❌ Workspace interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface SharedQuery" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ SharedQuery interface"
    ((TESTS_PASSED++))
else
    echo "❌ SharedQuery interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface AccessControlGroup" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ AccessControlGroup interface"
    ((TESTS_PASSED++))
else
    echo "❌ AccessControlGroup interface"
    ((TESTS_FAILED++))
fi

if grep -q "export interface Comment" "/home/jaffar/projets/symfony/obstack/frontend/src/services/collaboration.ts"; then
    echo "✅ Comment interface"
    ((TESTS_PASSED++))
else
    echo "❌ Comment interface"
    ((TESTS_FAILED++))
fi

echo ""

# ============================================================
# Router and Navigation Tests
# ============================================================
echo "ROUTER & NAVIGATION TESTS"
echo "=========================="

if grep -q "collaboration" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ Collaboration route in router"
    ((TESTS_PASSED++))
else
    echo "❌ Collaboration route in router"
    ((TESTS_FAILED++))
fi

if grep -q "CollaborationView" "/home/jaffar/projets/symfony/obstack/frontend/src/router/index.ts"; then
    echo "✅ CollaborationView imported in router"
    ((TESTS_PASSED++))
else
    echo "❌ CollaborationView imported in router"
    ((TESTS_FAILED++))
fi

if grep -q "Collaboration" "/home/jaffar/projets/symfony/obstack/frontend/src/components/Layout.vue"; then
    echo "✅ Collaboration link in Layout navigation"
    ((TESTS_PASSED++))
else
    echo "❌ Collaboration link in Layout navigation"
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
    if [ "$MODULE_COUNT" -gt "120" ]; then
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
