<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Journal d'audit de toutes les opérations sensibles du système.
 *
 * Enregistre:
 *  - Qui a effectué l'action
 *  - Quand (date/heure)
 *  - D'où (adresse IP)
 *  - Quoi (type d'opération)
 *  - Où (quelle ressource)
 *  - Avant/Après (anciennes/nouvelles valeurs)
 *  - Succès/Échec
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(
    name: 'audit_logs',
    indexes: [
        new ORM\Index(name: 'idx_audit_user', columns: ['user_id']),
        new ORM\Index(name: 'idx_audit_action', columns: ['action']),
        new ORM\Index(name: 'idx_audit_date', columns: ['created_at']),
    ]
)]
class AuditLog
{
    // Actions d'audit
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_LOGIN = 'login';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PERMISSION_CHANGE = 'permission_change';
    const ACTION_DATABASE_QUERY = 'database_query';
    const ACTION_DATABASE_CONNECTION_TEST = 'database_connection_test';
    const ACTION_BACKUP = 'backup';
    const ACTION_RESTORE = 'restore';
    const ACTION_ACCESS_DENIED = 'access_denied';

    // Statuts
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_PARTIAL = 'partial';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Utilisateur ayant effectué l'action (peut être null pour les actions système) */
    #[ORM\ManyToOne(targetEntity: LocalUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LocalUser $user = null;

    /** Action effectuée */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $action = '';

    /** Type de ressource affectée */
    #[ORM\Column(length: 100)]
    private string $resourceType = '';

    /** ID de la ressource affectée */
    #[ORM\Column(nullable: true)]
    private ?int $resourceId = null;

    /** Description textuelle de l'action */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Adresse IP de l'utilisateur */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    /** User-Agent du navigateur */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    /** Méthode HTTP (GET, POST, DELETE, etc.) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $httpMethod = null;

    /** Endpoint/URL appelé */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $endpoint = null;

    /** Ancienne valeur (JSON) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $oldValues = null;

    /** Nouvelle valeur (JSON) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $newValues = null;

    /** Statut: success | failure | partial */
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_SUCCESS;

    /** Message d'erreur le cas échéant */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /** Données additionnelles (JSON) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    /** Temps d'exécution en millisecondes */
    #[ORM\Column(nullable: true)]
    private ?int $executionTime = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?LocalUser
    {
        return $this->user;
    }

    public function setUser(?LocalUser $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function setResourceType(string $resourceType): self
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function setResourceId(?int $resourceId): self
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(?string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function setOldValues(?array $oldValues): self
    {
        $this->oldValues = $oldValues;
        return $this;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function setNewValues(?array $newValues): self
    {
        $this->newValues = $newValues;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getExecutionTime(): ?int
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?int $executionTime): self
    {
        $this->executionTime = $executionTime;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
