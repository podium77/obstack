<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\LocalUser;
use App\EventListener\RequestContextListener;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Service d'audit pour logger toutes les opérations sensibles.
 * 
 * Enregistre:
 *  - Qui (utilisateur)
 *  - Quand (date/heure)
 *  - D'où (adresse IP)
 *  - Quoi (type d'opération)
 *  - Où (quelle ressource)
 *  - Avant/Après (valeurs)
 *  - Succès/Échec
 */
class AuditService
{
    private ?Request $request;
    private ?LocalUser $currentUser;

    public function __construct(
        private AuditLogRepository $auditRepository,
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
    ) {
        $this->request = $this->requestStack->getCurrentRequest();
        
        // Get current user from token storage
        $token = $this->tokenStorage->getToken();
        $this->currentUser = $token && $token->getUser() instanceof LocalUser
            ? $token->getUser()
            : null;
    }

    /**
     * Logger une opération.
     * 
     * @param string $action Type d'action
     * @param string $resourceType Type de ressource
     * @param int|null $resourceId ID de la ressource
     * @param string|null $description Description textuelle
     * @param string $status Statut (success|failure|partial)
     * @param array|null $oldValues Anciennes valeurs
     * @param array|null $newValues Nouvelles valeurs
     * @param string|null $errorMessage Message d'erreur si applicable
     * @param array|null $metadata Données additionnelles
     */
    public function log(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?string $description = null,
        string $status = AuditLog::STATUS_SUCCESS,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $errorMessage = null,
        ?array $metadata = null,
    ): AuditLog {
        $auditLog = new AuditLog();
        $auditLog->setUser($this->currentUser);
        $auditLog->setAction($action);
        $auditLog->setResourceType($resourceType);
        $auditLog->setResourceId($resourceId);
        $auditLog->setDescription($description);
        $auditLog->setStatus($status);
        $auditLog->setOldValues($oldValues);
        $auditLog->setNewValues($newValues);
        $auditLog->setErrorMessage($errorMessage);
        $auditLog->setMetadata($metadata);

        // Capturer le contexte HTTP
        if ($this->request) {
            $auditLog->setIpAddress($this->getClientIp());
            $auditLog->setUserAgent($this->request->headers->get('User-Agent'));
            $auditLog->setHttpMethod($this->request->getMethod());
            $auditLog->setEndpoint($this->request->getPathInfo());
        } else {
            // Fallback sur le contexte statique du RequestContextListener
            $auditLog->setIpAddress(RequestContextListener::getClientIpFromContext());
            $auditLog->setUserAgent(RequestContextListener::getUserAgentFromContext());
            $auditLog->setHttpMethod(RequestContextListener::getMethodFromContext());
            $auditLog->setEndpoint(RequestContextListener::getEndpointFromContext());
        }

        $this->em->persist($auditLog);
        $this->em->flush();

        return $auditLog;
    }

    /**
     * Logger la création d'une ressource.
     */
    public function logCreate(
        string $resourceType,
        int $resourceId,
        array $data,
        ?string $description = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_CREATE,
            $resourceType,
            $resourceId,
            $description ?? "Création de $resourceType",
            AuditLog::STATUS_SUCCESS,
            null,
            $data,
        );
    }

    /**
     * Logger la mise à jour d'une ressource.
     */
    public function logUpdate(
        string $resourceType,
        int $resourceId,
        array $oldValues,
        array $newValues,
        ?string $description = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_UPDATE,
            $resourceType,
            $resourceId,
            $description ?? "Modification de $resourceType",
            AuditLog::STATUS_SUCCESS,
            $oldValues,
            $newValues,
        );
    }

    /**
     * Logger la suppression d'une ressource.
     */
    public function logDelete(
        string $resourceType,
        int $resourceId,
        array $deletedData,
        ?string $description = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_DELETE,
            $resourceType,
            $resourceId,
            $description ?? "Suppression de $resourceType",
            AuditLog::STATUS_SUCCESS,
            $deletedData,
            null,
        );
    }

    /**
     * Logger un accès refusé.
     */
    public function logAccessDenied(
        string $resourceType,
        string $reason = '',
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_ACCESS_DENIED,
            $resourceType,
            null,
            "Accès refusé: $reason",
            AuditLog::STATUS_FAILURE,
        );
    }

    /**
     * Logger une connexion.
     */
    public function logLogin(LocalUser $user, bool $success = true): AuditLog
    {
        $status = $success ? AuditLog::STATUS_SUCCESS : AuditLog::STATUS_FAILURE;
        $action = $success ? AuditLog::ACTION_LOGIN : AuditLog::ACTION_LOGIN_FAILED;

        // Créer un audit log sans être connecté
        $auditLog = new AuditLog();
        $auditLog->setUser($success ? $user : null);
        $auditLog->setAction($action);
        $auditLog->setResourceType('LocalUser');
        $auditLog->setResourceId($user->getId());
        $auditLog->setStatus($status);
        $auditLog->setDescription($success ? "Connexion réussie" : "Tentative de connexion échouée");

        if ($this->request) {
            $auditLog->setIpAddress($this->getClientIp());
            $auditLog->setUserAgent($this->request->headers->get('User-Agent'));
            $auditLog->setHttpMethod($this->request->getMethod());
            $auditLog->setEndpoint($this->request->getPathInfo());
        }

        $this->em->persist($auditLog);
        $this->em->flush();

        return $auditLog;
    }

    /**
     * Logger une requête de base de données exécutée.
     */
    public function logDatabaseQuery(
        string $connectionName,
        string $dbType,
        string $query,
        array $params = [],
        bool $success = true,
        ?string $errorMessage = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_DATABASE_QUERY,
            'DatabaseConnection',
            null,
            "Requête sur $connectionName ($dbType)",
            $success ? AuditLog::STATUS_SUCCESS : AuditLog::STATUS_FAILURE,
            null,
            ['query' => $query, 'params' => $params],
            $errorMessage,
        );
    }

    /**
     * Logger un test de connexion à une base de données.
     */
    public function logDatabaseConnectionTest(
        string $connectionName,
        string $dbType,
        bool $success = true,
        ?string $errorMessage = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_DATABASE_CONNECTION_TEST,
            'DatabaseConnection',
            null,
            "Test de connexion: $connectionName ($dbType)",
            $success ? AuditLog::STATUS_SUCCESS : AuditLog::STATUS_FAILURE,
            null,
            null,
            $errorMessage,
        );
    }

    /**
     * Logger une modification de permission.
     */
    public function logPermissionChange(
        string $targetType,
        int $targetId,
        string $permissionCode,
        string $action, // added | removed
        ?string $reason = null,
    ): AuditLog {
        return $this->log(
            AuditLog::ACTION_PERMISSION_CHANGE,
            $targetType,
            $targetId,
            "Permission $action: $permissionCode" . ($reason ? " - $reason" : ""),
            AuditLog::STATUS_SUCCESS,
            ['permission' => $permissionCode, 'action' => $action],
            null,
        );
    }

    /**
     * Obtenir l'adresse IP du client.
     */
    private function getClientIp(): string
    {
        if (!$this->request) {
            return '';
        }

        if ($this->request->headers->has('X-Forwarded-For')) {
            $ips = explode(',', $this->request->headers->get('X-Forwarded-For'));
            return trim($ips[0]);
        }

        if ($this->request->headers->has('X-Real-IP')) {
            return $this->request->headers->get('X-Real-IP');
        }

        return $this->request->getClientIp() ?? '';
    }

    /**
     * Obtenir l'historique d'audit pour un utilisateur.
     */
    public function getUserHistory(LocalUser $user, int $limit = 50): array
    {
        return $this->auditRepository->findByUser($user, $limit);
    }

    /**
     * Obtenir l'historique d'une ressource.
     */
    public function getResourceHistory(string $resourceType, int $resourceId, int $limit = 50): array
    {
        $logs = $this->auditRepository->findByResourceId($resourceId, $limit);
        return array_filter($logs, fn($log) => $log->getResourceType() === $resourceType);
    }

    /**
     * Obtenir les tentatives d'accès refusé récentes.
     */
    public function getRecentAccessDeniedAttempts(int $hours = 24): array
    {
        $since = new \DateTimeImmutable("-$hours hours");
        return $this->auditRepository->findAccessDeniedAttempts($since);
    }
}
