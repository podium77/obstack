<?php

namespace App\Entity;

use App\Repository\AIClusterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AICluster Entity
 * 
 * Représente un cluster IA (Ollama) associé à une Application
 * Peut être une instance existante (Mode 1) ou un déploiement nouveau (Mode 2)
 */
#[ORM\Entity(repositoryClass: AIClusterRepository::class)]
#[ORM\Table(name: 'ai_clusters')]
#[ORM\Index(columns: ['application_id', 'status'])]
class AICluster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['none', 'existing', 'deployed'])]
    private string $mode = 'none'; // none|existing|deployed

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['qwen3:4b', 'qwen3:8b'])]
    private ?string $aiModel = null; // Modèle explicite choisi

    // Lorsque mode=existing : référence à une AIInfrastructure existante
    #[ORM\ManyToOne(targetEntity: AIInfrastructure::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AIInfrastructure $existingInfrastructure = null;

    // Lorsque mode=deployed : référence au déploiement
    #[ORM\ManyToOne(targetEntity: AIDeployment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AIDeployment $deployment = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['pending', 'deploying', 'active', 'failed'])]
    private string $status = 'pending'; // pending|deploying|active|failed

    #[ORM\Column(nullable: true, type: 'text')]
    private ?string $apiUrl = null; // URL de l'API Ollama

    #[ORM\Column(nullable: true, type: 'text')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'json')]
    private array $deploymentConfig = []; // Config pour Mode 2 (VM params, hypervisor, etc.)

    #[ORM\Column(type: 'json')]
    private array $logs = []; // Logs de déploiement/configuration

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true, type: 'datetime_immutable')]
    private ?\DateTimeImmutable $activeAt = null;

    public function __construct(Application $application)
    {
        $this->application = $application;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters & Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApplication(): Application
    {
        return $this->application;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): self
    {
        $valid = ['none', 'existing', 'deployed'];
        if (!in_array($mode, $valid)) {
            throw new \InvalidArgumentException("Invalid mode: $mode");
        }
        $this->mode = $mode;
        return $this;
    }

    public function getAiModel(): ?string
    {
        return $this->aiModel;
    }

    public function setAiModel(?string $model): self
    {
        if ($model && !in_array($model, ['qwen3:4b', 'qwen3:8b'])) {
            throw new \InvalidArgumentException("Unsupported model: $model");
        }
        $this->aiModel = $model;
        return $this;
    }

    public function getExistingInfrastructure(): ?AIInfrastructure
    {
        return $this->existingInfrastructure;
    }

    public function setExistingInfrastructure(?AIInfrastructure $infrastructure): self
    {
        $this->existingInfrastructure = $infrastructure;
        return $this;
    }

    public function getDeployment(): ?AIDeployment
    {
        return $this->deployment;
    }

    public function setDeployment(?AIDeployment $deployment): self
    {
        $this->deployment = $deployment;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $valid = ['pending', 'deploying', 'active', 'failed'];
        if (!in_array($status, $valid)) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $this->status = $status;
        return $this;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(?string $url): self
    {
        $this->apiUrl = $url;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $message): self
    {
        $this->errorMessage = $message;
        return $this;
    }

    public function getDeploymentConfig(): array
    {
        return $this->deploymentConfig;
    }

    public function setDeploymentConfig(array $config): self
    {
        $this->deploymentConfig = $config;
        return $this;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function addLog(string $message): self
    {
        $this->logs[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getActiveAt(): ?\DateTimeImmutable
    {
        return $this->activeAt;
    }

    public function setActiveAt(?\DateTimeImmutable $activeAt): self
    {
        $this->activeAt = $activeAt;
        return $this;
    }
}
