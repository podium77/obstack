<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

/**
 * AIInfrastructure — Stocke les configurations d'infrastructure IA centralisée
 *
 * Principes :
 * - L'IA est JAMAIS déployée sur les agents ou hôtes surveillés
 * - Configuration manuelle uniquement (pas d'autodétection)
 * - Deux modes : existante ou déployée automatiquement
 * - Fournisseurs supportés : Ollama (extensible à d'autres via interface)
 * - Modèles supportés : qwen3:4b, qwen3:8b (choix explicite de l'admin)
 *
 * @ORM\Entity(repositoryClass="App\Repository\AIInfrastructureRepository")
 * @ORM\Table(name="ai_infrastructure")
 */
#[ORM\Entity]
#[ORM\Table(name: 'ai_infrastructure')]
class AIInfrastructure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Nom descriptif (ex: "Ollama Production", "vLLM Dev")
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    /**
     * Mode d'intégration IA
     * - 'existing' : utiliser une IA existante (Mode 1)
     * - 'deployed' : IA déployée via Obstack (Mode 2)
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $mode; // 'existing' ou 'deployed'

    /**
     * Fournisseur IA
     * - 'ollama' : Ollama (pour les deux modes)
     * - Extensible : 'vllm', 'external-api', etc.
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $provider; // 'ollama'

    /**
     * URL de l'API IA (ex: http://ollama.company.local:11434)
     */
    #[ORM\Column(type: Types::STRING, length: 512)]
    private string $apiUrl;

    /**
     * Clé API pour authentification (optionnelle)
     * Stockée chiffrée en base de données
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $apiKey = null;

    /**
     * Modèle LLM sélectionné
     * Choix EXPLICITE de l'admin :
     * - 'qwen3:4b' (4 milliards de paramètres, léger)
     * - 'qwen3:8b' (8 milliards de paramètres, précision accrue)
     *
     * Aucun choix automatique autorisé.
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $model; // 'qwen3:4b' ou 'qwen3:8b'

    /**
     * Statut de l'infrastructure IA
     * - 'active' : fonctionnelle et testée
     * - 'deploying' : déploiement en cours (Mode 2)
     * - 'failed' : erreur lors du déploiement ou test de connexion échoué
     * - 'inactive' : désactivée manuellement
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $status; // 'active', 'deploying', 'failed', 'inactive'

    /**
     * Timeout (en secondes) pour les appels API
     * Défaut : 30 secondes
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $timeoutSeconds = 30;

    /**
     * Port de l'infrastructure IA
     * Par défaut pour Ollama : 11434
     * Extensible pour d'autres fournisseurs
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $port = 11434;

    /**
     * Dernière date de vérification de la connexion
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastHealthCheckAt = null;

    /**
     * Message d'erreur si applicable
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    /**
     * Configuration supplémentaire (JSON)
     * Pour Mode 2 : informations de déploiement (hyperviseur, VM ID, etc.)
     * Format : array (sérialisé en JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $deploymentConfig = null;

    /**
     * Date de déploiement (Mode 2 seulement)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deployedAt = null;

    /**
     * Entreprise propriétaire (relation multi-tenant)
     * Pour Mode 1 : optionnel si IA partagée globalement
     * Pour Mode 2 : défini pour l'entreprise qui a demandé le déploiement
     */
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true)]
    private ?Company $company = null;

    /**
     * Administrateur qui a créé la configuration
     */
    #[ORM\ManyToOne(targetEntity: LocalUser::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true)]
    private ?LocalUser $createdBy = null;

    /**
     * Date de création
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * Active/Inactive
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    // =========================================================================
    // GETTERS / SETTERS
    // =========================================================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        if (!in_array($mode, ['existing', 'deployed'])) {
            throw new \InvalidArgumentException('Mode must be "existing" or "deployed"');
        }
        $this->mode = $mode;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(string $apiUrl): self
    {
        $this->apiUrl = $apiUrl;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): self
    {
        $this->apiKey = $apiKey;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $supportedModels = ['qwen3:4b', 'qwen3:8b'];
        if (!in_array($model, $supportedModels)) {
            throw new \InvalidArgumentException(
                'Model must be one of: ' . implode(', ', $supportedModels)
            );
        }
        $this->model = $model;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $validStatuses = ['active', 'deploying', 'failed', 'inactive'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException(
                'Status must be one of: ' . implode(', ', $validStatuses)
            );
        }
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(int $timeoutSeconds): self
    {
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than 0');
        }
        $this->timeoutSeconds = $timeoutSeconds;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        if ($port <= 0 || $port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }
        $this->port = $port;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastHealthCheckAt(): ?\DateTimeImmutable
    {
        return $this->lastHealthCheckAt;
    }

    public function setLastHealthCheckAt(?\DateTimeImmutable $lastHealthCheckAt): self
    {
        $this->lastHealthCheckAt = $lastHealthCheckAt;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeploymentConfig(): ?array
    {
        return $this->deploymentConfig;
    }

    public function setDeploymentConfig(?array $deploymentConfig): self
    {
        $this->deploymentConfig = $deploymentConfig;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeployedAt(): ?\DateTimeImmutable
    {
        return $this->deployedAt;
    }

    public function setDeployedAt(?\DateTimeImmutable $deployedAt): self
    {
        $this->deployedAt = $deployedAt;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedBy(): ?LocalUser
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?LocalUser $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    // =========================================================================
    // CONSTRUCTEUR
    // =========================================================================

    public function __construct(
        string $name,
        string $mode,
        string $provider,
        string $apiUrl,
        string $model,
        string $status = 'inactive'
    ) {
        $this->name = $name;
        $this->setMode($mode);
        $this->provider = $provider;
        $this->apiUrl = $apiUrl;
        $this->setModel($model);
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
}
