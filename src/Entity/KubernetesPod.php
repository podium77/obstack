<?php
namespace App\Entity;

use App\Repository\KubernetesPodRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KubernetesPodRepository::class)]
#[ORM\Table(name: 'kubernetes_pods')]
#[ORM\HasLifecycleCallbacks]
class KubernetesPod
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: KubernetesNode::class, inversedBy: 'pods')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?KubernetesNode $node = null;

    #[ORM\Column(length: 255)]
    private string $podName = '';

    #[ORM\Column(length: 255)]
    private string $namespace = 'default';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deploymentName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serviceName = null;

    /** Phase: Running | Pending | Succeeded | Failed | Unknown */
    #[ORM\Column(length: 30)]
    private string $phase = 'Unknown';

    /** Statut des containers */
    #[ORM\Column(type: 'json')]
    private array $containerStatuses = [];

    /** Labels du pod */
    #[ORM\Column(type: 'json')]
    private array $labels = [];

    /** Images des containers */
    #[ORM\Column(type: 'json')]
    private array $images = [];

    /** Ressources demandées (requests) */
    #[ORM\Column(type: 'json')]
    private array $resourceRequests = [];

    /** Limites de ressources (limits) */
    #[ORM\Column(type: 'json')]
    private array $resourceLimits = [];

    /** IP du pod */
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $podIp = null;

    /** Nombre de redémarrages */
    #[ORM\Column]
    private int $restartCount = 0;

    /** Date de démarrage du pod */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    public function __construct()
    {
        $this->firstSeenAt = new \DateTimeImmutable();
    }

    public function isRunning(): bool     { return $this->phase === 'Running'; }
    public function isPending(): bool     { return $this->phase === 'Pending'; }
    public function isFailed(): bool      { return $this->phase === 'Failed'; }
    public function isSucceeded(): bool   { return $this->phase === 'Succeeded'; }
    public function hasRestarts(): bool   { return $this->restartCount > 0; }
    public function isCrashLooping(): bool { return $this->restartCount > 5; }

    public function getContainerCount(): int { return count($this->containerStatuses); }

    public function getReadyContainerCount(): int {
        return count(array_filter($this->containerStatuses, fn($c) => $c['ready'] ?? false));
    }

    public function getMainImage(): ?string {
        return $this->images[0] ?? null;
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function getNode(): ?KubernetesNode { return $this->node; }
    public function setNode(?KubernetesNode $n): static { $this->node = $n; return $this; }
    public function getPodName(): string { return $this->podName; }
    public function setPodName(string $n): static { $this->podName = $n; return $this; }
    public function getNamespace(): string { return $this->namespace; }
    public function setNamespace(string $n): static { $this->namespace = $n; return $this; }
    public function getDeploymentName(): ?string { return $this->deploymentName; }
    public function setDeploymentName(?string $d): static { $this->deploymentName = $d; return $this; }
    public function getServiceName(): ?string { return $this->serviceName; }
    public function setServiceName(?string $s): static { $this->serviceName = $s; return $this; }
    public function getPhase(): string { return $this->phase; }
    public function setPhase(string $p): static { $this->phase = $p; return $this; }
    public function getContainerStatuses(): array { return $this->containerStatuses; }
    public function setContainerStatuses(array $s): static { $this->containerStatuses = $s; return $this; }
    public function getLabels(): array { return $this->labels; }
    public function setLabels(array $l): static { $this->labels = $l; return $this; }
    public function getImages(): array { return $this->images; }
    public function setImages(array $i): static { $this->images = $i; return $this; }
    public function getResourceRequests(): array { return $this->resourceRequests; }
    public function setResourceRequests(array $r): static { $this->resourceRequests = $r; return $this; }
    public function getResourceLimits(): array { return $this->resourceLimits; }
    public function setResourceLimits(array $r): static { $this->resourceLimits = $r; return $this; }
    public function getPodIp(): ?string { return $this->podIp; }
    public function setPodIp(?string $ip): static { $this->podIp = $ip; return $this; }
    public function getRestartCount(): int { return $this->restartCount; }
    public function setRestartCount(int $r): static { $this->restartCount = $r; return $this; }
    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(?\DateTimeImmutable $v): static { $this->startedAt = $v; return $this; }
    public function getFirstSeenAt(): \DateTimeImmutable { return $this->firstSeenAt; }
    public function getLastSyncAt(): ?\DateTimeImmutable { return $this->lastSyncAt; }
    public function setLastSyncAt(?\DateTimeImmutable $v): static { $this->lastSyncAt = $v; return $this; }
}
