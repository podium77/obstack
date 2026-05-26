<?php
namespace App\Entity;

use App\Enum\NodeRole;
use App\Repository\KubernetesNodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Node Kubernetes supervisé (master ou worker).
 * Découvert automatiquement par l'agent ou via l'API Kubernetes.
 */
#[ORM\Entity(repositoryClass: KubernetesNodeRepository::class)]
#[ORM\Table(name: 'kubernetes_nodes')]
#[ORM\HasLifecycleCallbacks]
class KubernetesNode
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Environment::class, inversedBy: 'kubernetesNodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Environment $environment = null;

    /** Application (stack) correspondant à ce node (optionnel) */
    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Application $application = null;

    #[ORM\Column(length: 255)]
    private string $nodeName = '';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $internalIp = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $externalIp = null;

    /** Rôle du node: master | worker | etcd | ingress */
    #[ORM\Column(length: 20, enumType: NodeRole::class)]
    private NodeRole $role = NodeRole::WORKER;

    /** Labels Kubernetes du node */
    #[ORM\Column(type: 'json')]
    private array $labels = [];

    /** Taints du node */
    #[ORM\Column(type: 'json')]
    private array $taints = [];

    /** Annotations du node */
    #[ORM\Column(type: 'json')]
    private array $annotations = [];

    /** Conditions du node (Ready, MemoryPressure, DiskPressure...) */
    #[ORM\Column(type: 'json')]
    private array $conditions = [];

    /** Capacités (cpu, memory, pods, etc.) */
    #[ORM\Column(type: 'json')]
    private array $capacity = [];

    /** Ressources allouées */
    #[ORM\Column(type: 'json')]
    private array $allocatable = [];

    /** Version Kubernetes */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $kubernetesVersion = null;

    /** Container runtime (containerd, docker, cri-o) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $containerRuntime = null;

    /** OS de la machine */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $osImage = null;

    /** Architecture (amd64, arm64) */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $architecture = null;

    /** Statut du node */
    #[ORM\Column(length: 20)]
    private string $status = 'unknown'; // Ready | NotReady | Unknown

    /** Pods déployés sur ce node */
    #[ORM\OneToMany(targetEntity: KubernetesPod::class, mappedBy: 'node', cascade: ['all'], orphanRemoval: true)]
    private Collection $pods;

    /** Métriques actuelles du node */
    #[ORM\Column(type: 'json')]
    private array $currentMetrics = [
        'cpu_usage_percent'    => null,
        'memory_usage_percent' => null,
        'disk_usage_percent'   => null,
        'pod_count'            => null,
        'pod_capacity'         => null,
    ];

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    public function __construct()
    {
        $this->pods        = new ArrayCollection();
        $this->firstSeenAt = new \DateTimeImmutable();
    }

    public function isMaster(): bool { return $this->role === NodeRole::MASTER; }
    public function isWorker(): bool { return $this->role === NodeRole::WORKER; }
    public function isReady(): bool  { return $this->status === 'Ready'; }

    public function getPodCount(): int { return $this->pods->count(); }

    public function getPodCapacity(): ?int
    {
        return $this->capacity['pods'] ?? null;
    }

    public function getCpuCapacity(): ?string
    {
        return $this->capacity['cpu'] ?? null;
    }

    public function getMemoryCapacityGb(): ?float
    {
        $mem = $this->capacity['memory'] ?? null;
        if (!$mem) return null;
        // Convertir Ki → GB
        if (str_ends_with($mem, 'Ki')) {
            return round((int)rtrim($mem, 'Ki') / 1024 / 1024, 1);
        }
        return null;
    }

    public function isConditionOk(string $condition): bool
    {
        foreach ($this->conditions as $c) {
            if ($c['type'] === $condition) {
                return $c['status'] === 'True';
            }
        }
        return false;
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function getEnvironment(): ?Environment { return $this->environment; }
    public function setEnvironment(?Environment $e): static { $this->environment = $e; return $this; }
    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $a): static { $this->application = $a; return $this; }
    public function getNodeName(): string { return $this->nodeName; }
    public function setNodeName(string $n): static { $this->nodeName = $n; return $this; }
    public function getInternalIp(): ?string { return $this->internalIp; }
    public function setInternalIp(?string $ip): static { $this->internalIp = $ip; return $this; }
    public function getExternalIp(): ?string { return $this->externalIp; }
    public function setExternalIp(?string $ip): static { $this->externalIp = $ip; return $this; }
    public function getRole(): NodeRole { return $this->role; }
    public function setRole(NodeRole $r): static { $this->role = $r; return $this; }
    public function getLabels(): array { return $this->labels; }
    public function setLabels(array $l): static { $this->labels = $l; return $this; }
    public function getTaints(): array { return $this->taints; }
    public function setTaints(array $t): static { $this->taints = $t; return $this; }
    public function getAnnotations(): array { return $this->annotations; }
    public function setAnnotations(array $a): static { $this->annotations = $a; return $this; }
    public function getConditions(): array { return $this->conditions; }
    public function setConditions(array $c): static { $this->conditions = $c; return $this; }
    public function getCapacity(): array { return $this->capacity; }
    public function setCapacity(array $c): static { $this->capacity = $c; return $this; }
    public function getAllocatable(): array { return $this->allocatable; }
    public function setAllocatable(array $a): static { $this->allocatable = $a; return $this; }
    public function getKubernetesVersion(): ?string { return $this->kubernetesVersion; }
    public function setKubernetesVersion(?string $v): static { $this->kubernetesVersion = $v; return $this; }
    public function getContainerRuntime(): ?string { return $this->containerRuntime; }
    public function setContainerRuntime(?string $r): static { $this->containerRuntime = $r; return $this; }
    public function getOsImage(): ?string { return $this->osImage; }
    public function setOsImage(?string $o): static { $this->osImage = $o; return $this; }
    public function getArchitecture(): ?string { return $this->architecture; }
    public function setArchitecture(?string $a): static { $this->architecture = $a; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getPods(): Collection { return $this->pods; }
    public function getCurrentMetrics(): array { return $this->currentMetrics; }
    public function setCurrentMetrics(array $m): static { $this->currentMetrics = $m; return $this; }
    public function getFirstSeenAt(): \DateTimeImmutable { return $this->firstSeenAt; }
    public function getLastSyncAt(): ?\DateTimeImmutable { return $this->lastSyncAt; }
    public function setLastSyncAt(?\DateTimeImmutable $v): static { $this->lastSyncAt = $v; return $this; }
}
