<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Entity\LocalUser;
use App\Service\AuditArchiveService;
use App\Service\EncryptionService;
use App\Service\MFAService;
use App\Service\RowLevelSecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/security', name: 'api_admin_security')]
#[IsGranted('ROLE_ADMIN')]
class SecurityController extends AbstractController
{
    public function __construct(
        private RowLevelSecurityService $rlsService,
        private EncryptionService $encryptionService,
        private MFAService $mfaService,
        private AuditArchiveService $archiveService,
    ) {}

    /**
     * Get RLS policies
     */
    #[Route('/rls/policies', name: 'api_rls_list', methods: ['GET'])]
    public function listRlsPolicies(): JsonResponse
    {
        $result = $this->rlsService->listPolicies();
        return $this->json($result);
    }

    /**
     * Create RLS policy
     */
    #[Route('/rls/policies', name: 'api_rls_create', methods: ['POST'])]
    public function createRlsPolicy(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->rlsService->createPolicy($data);
        
        $statusCode = $result['success'] ? 201 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Update RLS policy
     */
    #[Route('/rls/policies/{id}', name: 'api_rls_update', methods: ['PUT'])]
    public function updateRlsPolicy(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->rlsService->updatePolicy($id, $data);
        
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Delete RLS policy
     */
    #[Route('/rls/policies/{id}', name: 'api_rls_delete', methods: ['DELETE'])]
    public function deleteRlsPolicy(string $id): JsonResponse
    {
        $result = $this->rlsService->deletePolicy($id);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Toggle RLS for table
     */
    #[Route('/rls/toggle', name: 'api_rls_toggle', methods: ['POST'])]
    public function toggleRls(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        if (!isset($data['tableName'], $data['enabled'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400);
        }

        $result = $this->rlsService->toggleRls($data['tableName'], (bool)$data['enabled']);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get user access rules
     */
    #[Route('/rls/access', name: 'api_rls_access', methods: ['GET'])]
    public function getUserAccess(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();
        
        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $rules = $this->rlsService->getUserAccessRules($user);
        return $this->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Generate TOTP secret
     */
    #[Route('/mfa/totp/generate', name: 'api_mfa_generate', methods: ['POST'])]
    public function generateTotpSecret(): JsonResponse
    {
        $result = $this->mfaService->generateTotpSecret();
        return $this->json($result);
    }

    /**
     * Verify TOTP code
     */
    #[Route('/mfa/totp/verify', name: 'api_mfa_verify', methods: ['POST'])]
    public function verifyTotpCode(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        
        if (!isset($data['secret'], $data['code'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400);
        }

        $result = $this->mfaService->verifyTotpCode($data['secret'], $data['code']);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Send MFA code
     */
    #[Route('/mfa/send', name: 'api_mfa_send', methods: ['POST'])]
    public function sendMfaCode(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();
        
        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $method = $data['method'] ?? 'email';
        
        $result = $this->mfaService->sendMfaCode($user, $method);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Enable MFA
     */
    #[Route('/mfa/enable', name: 'api_mfa_enable', methods: ['POST'])]
    public function enableMfa(Request $request): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();
        
        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        
        if (!isset($data['method'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing method field',
            ], 400);
        }

        $result = $this->mfaService->enableMfa(
            $user,
            $data['method'],
            $data['secret'] ?? ''
        );
        
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get MFA status
     */
    #[Route('/mfa/status', name: 'api_mfa_status', methods: ['GET'])]
    public function getMfaStatus(): JsonResponse
    {
        /** @var LocalUser $user */
        $user = $this->getUser();
        
        if (!$user instanceof LocalUser) {
            return $this->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $status = $this->mfaService->getMfaStatus($user);
        return $this->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Archive audit logs
     */
    #[Route('/audit/archive', name: 'api_audit_archive', methods: ['POST'])]
    public function archiveAuditLogs(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $retentionDays = (int)($data['retentionDays'] ?? 90);
        
        $result = $this->archiveService->archiveOldLogs($retentionDays);
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get archive statistics
     */
    #[Route('/audit/archive/stats', name: 'api_audit_archive_stats', methods: ['GET'])]
    public function getArchiveStats(): JsonResponse
    {
        $result = $this->archiveService->getArchiveStats();
        return $this->json($result);
    }

    /**
     * Set retention policy
     */
    #[Route('/audit/retention-policy', name: 'api_retention_policy_set', methods: ['POST'])]
    public function setRetentionPolicy(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $result = $this->archiveService->setRetentionPolicy($data);
        
        $statusCode = $result['success'] ? 200 : 400;
        return $this->json($result, $statusCode);
    }

    /**
     * Get retention policy
     */
    #[Route('/audit/retention-policy', name: 'api_retention_policy_get', methods: ['GET'])]
    public function getRetentionPolicy(): JsonResponse
    {
        $result = $this->archiveService->getRetentionPolicy();
        return $this->json($result);
    }

    /**
     * Export audit logs
     */
    #[Route('/audit/export', name: 'api_audit_export', methods: ['GET'])]
    public function exportAuditLogs(Request $request): JsonResponse
    {
        $format = $request->query->get('format', 'csv');
        $fromDate = $request->query->get('from') ? new \DateTime($request->query->get('from')) : null;
        $toDate = $request->query->get('to') ? new \DateTime($request->query->get('to')) : null;

        $result = $this->archiveService->exportLogs($format, $fromDate, $toDate);
        return $this->json($result);
    }

    /**
     * Get audit statistics
     */
    #[Route('/audit/stats', name: 'api_audit_stats', methods: ['GET'])]
    public function getAuditStats(): JsonResponse
    {
        $result = $this->archiveService->getAuditStats();
        return $this->json($result);
    }

    /**
     * Get encryption metadata
     */
    #[Route('/encryption/metadata', name: 'api_encryption_metadata', methods: ['GET'])]
    public function getEncryptionMetadata(): JsonResponse
    {
        $metadata = $this->encryptionService->getMetadata();
        return $this->json([
            'success' => true,
            'data' => $metadata,
        ]);
    }
}
