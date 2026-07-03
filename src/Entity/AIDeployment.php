<?php

namespace App\Entity;

use App\Repository\AIDeploymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AIDeployment Entity
 * 
 * Stocke les informations sur les déploiements Mode 2 (automatisés)
 * d'infrastructure IA sur hyperviseurs (Proxmox, VMware, Nutanix)
 */
#[ORM\Entity(repositoryClass: AIDeploymentRepository::class)]
#[ORM\Table(name: 'ai_deployment')]
#[ORM\Index(columns: ['company_id', 'status'])]
#[ORM\Index(columns: ['hypervisor_provider', 'created_at'])]
class AIDeployment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['proxmox', 'vmware', 'nutanix'])]
    private string $hypervisorProvider;

    #[ORM\Column(type: 'json')]
    private array $hypervisorConfig = []; // Credentials + config hyperviseur

    #[ORM\Column(type: 'json')]
    private array $vmConfig = []; // VM: CPU, RAM, Disk, etc.

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['qwen3:4b', 'qwen3:8b'])]
    private string $aiModel;

    #[ORM\Column(type: 'json')]
    private array $aiConfig = []; // Configuration AI (timeout, etc.)

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['pending', 'deploying', 'configuring', 'deployed', 'failed', 'stopping', 'stopped'])]
    private string $status = 'pending';

    #[ORM\Column(nullable: true)]
    private ?int $vmId = null; // ID de la VM créée

    #[ORM\Column(nullable: true, length: 50)]
    private ?string $vmIp = null; // IP de la VM

    #[ORM\Column(type: 'json')]
    private array $deploymentLog = []; // Logs de déploiement

    #[ORM\Column(nullable: true, type: 'text')]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true, type: 'datetime_immutable')]
    private ?\DateTimeImmutable $deployedAt = null;

    #[ORM\Column(nullable: true, type: 'datetime_immutable')]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Company $company = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private LocalUser $createdBy;

    public function __construct(
        string $name,
        string $hypervisorProvider,
        array $hypervisorConfig,
        array $vmConfig,
        string $aiModel,
        LocalUser $createdBy,
        ?Company $company = null
    ) {
        $this->name = $name;
        $this->hypervisorProvider = $hypervisorProvider;
        $this->hypervisorConfig = $hypervisorConfig;
        $this->vmConfig = $vmConfig;
        $this->aiModel = $aiModel;
        $this->createdBy = $createdBy;
        $this->company = $company;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters & Setters

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
        return $this;
    }

    public function getHypervisorProvider(): string
    {
        return $this->hypervisorProvider;
    }

    public function getHypervisorConfig(): array
    {
        return $this->hypervisorConfig;
    }

    public function setHypervisorConfig(array $config): self
    {
        $this->hypervisorConfig = $config;
        return $this;
    }

    public function getVmConfig(): array
    {
        return $this->vmConfig;
    }

    public function setVmConfig(array $config): self
    {
        $this->vmConfig = $config;
        return $this;
    }

    public function getAiModel(): string
    {
        return $this->aiModel;
    }

    public function getAiConfig(): array
    {
        return $this->aiConfig;
    }

    public function setAiConfig(array $config): self
    {
        $this->aiConfig = $config;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $valid = ['pending', 'deploying', 'configuring', 'deployed', 'failed', 'stopping', 'stopped'];
        if (!in_array($status, $valid)) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $this->status = $status;
        return $this;
    }

    public function getVmId(): ?int
    {
        return $this->vmId;
    }

    public function setVmId(?int $vmId): self
    {
        $this->vmId = $vmId;
        return $this;
    }

    public function getVmIp(): ?string
    {
        return $this->vmIp;
    }

    public function setVmIp(?string $ip): self
    {
        $this->vmIp = $ip;
        return $this;
    }

    public function getDeploymentLog(): array
    {
        return $this->deploymentLog;
    }

    public function addDeploymentLog(string $message): self
    {
        $this->deploymentLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
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

    public function getDeployedAt(): ?\DateTimeImmutable
    {
        return $this->deployedAt;
    }

    public function setDeployedAt(?\DateTimeImmutable $deployedAt): self
    {
        $this->deployedAt = $deployedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getCreatedBy(): LocalUser
    {
        return $this->createdBy;
    }
}
