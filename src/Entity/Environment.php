<?php
namespace App\Entity;

use App\Enum\EnvironmentType;
use App\Repository\EnvironmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Environnement d'une entreprise (prod, dev, lab, qa...).
 * Chaque environnement possède:
 *  - Ses propres tokens d'agent (liés à company+env+user)
 *  - Ses applications/stacks
 *  - Ses utilisateurs avec droits spécifiques
 *  - Son propre cluster Kubernetes (optionnel)
 */
#[ORM\Entity(repositoryClass: EnvironmentRepository::class)]
#[ORM\Table(name: 'environments')]
#[ORM\UniqueConstraint(name: 'uniq_company_slug', fields: ['company', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Environment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'environments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Company $company = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $slug = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30, enumType: EnvironmentType::class)]
    private EnvironmentType $type = EnvironmentType::DEVELOPMENT;

    /** Environnement par défaut de l'entreprise (non supprimable) */
    #[ORM\Column]
    private bool $isDefault = false;

    /** Couleur d'identification */
    #[ORM\Column(length: 7)]
    private string $color = '#185FA5';

    /** Token maître de cet environnement (préfixe des tokens agents) */
    #[ORM\Column(length: 64, unique: true)]
    private string $masterToken = '';

    /** Endpoint de l'API Kubernetes (si cluster K8s associé) */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $kubernetesApiUrl = null;

    /** Kubeconfig JSON encodé (optionnel) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $kubeconfig = null;

    /** Namespace Kubernetes par défaut */
    #[ORM\Column(length: 100)]
    private string $kubernetesNamespace = 'default';

    /** Supervision Kubernetes activée */
    #[ORM\Column]
    private bool $kubernetesEnabled = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(targetEntity: AgentToken::class, mappedBy: 'environment', cascade: ['all'], orphanRemoval: true)]
    private Collection $agentTokens;

    #[ORM\OneToMany(targetEntity: Application::class, mappedBy: 'environment', cascade: ['all'], orphanRemoval: true)]
    private Collection $applications;

    #[ORM\OneToMany(targetEntity: EnvironmentUser::class, mappedBy: 'environment', cascade: ['all'], orphanRemoval: true)]
    private Collection $environmentUsers;

    #[ORM\OneToMany(targetEntity: KubernetesNode::class, mappedBy: 'environment', cascade: ['all'], orphanRemoval: true)]
    private Collection $kubernetesNodes;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->agentTokens       = new ArrayCollection();
        $this->applications      = new ArrayCollection();
        $this->environmentUsers  = new ArrayCollection();
        $this->kubernetesNodes   = new ArrayCollection();
        $this->createdAt         = new \DateTimeImmutable();
        $this->masterToken       = $this->generateToken();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void {
        // Ensure slug is never empty
        if (empty($this->slug)) {
            $this->slug = $this->generateSlugFromName();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function generateSlugFromName(): string
    {
        $slug = strtolower(trim($this->name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'env-' . bin2hex(random_bytes(4));
    }

    public function regenerateMasterToken(): void
    {
        $this->masterToken = $this->generateToken();
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $c): static { $this->company = $c; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $s): static { $this->slug = $s; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getType(): EnvironmentType { return $this->type; }
    public function setType(EnvironmentType $t): static { $this->type = $t; return $this; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $v): static { $this->isDefault = $v; return $this; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $c): static { $this->color = $c; return $this; }
    public function getMasterToken(): string { return $this->masterToken; }
    public function getKubernetesApiUrl(): ?string { return $this->kubernetesApiUrl; }
    public function setKubernetesApiUrl(?string $u): static { $this->kubernetesApiUrl = $u; return $this; }
    public function getKubeconfig(): ?string { return $this->kubeconfig; }
    public function setKubeconfig(?string $k): static { $this->kubeconfig = $k; return $this; }
    public function getKubernetesNamespace(): string { return $this->kubernetesNamespace; }
    public function setKubernetesNamespace(string $n): static { $this->kubernetesNamespace = $n; return $this; }
    public function isKubernetesEnabled(): bool { return $this->kubernetesEnabled; }
    public function setKubernetesEnabled(bool $v): static { $this->kubernetesEnabled = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getAgentTokens(): Collection { return $this->agentTokens; }
    public function getApplications(): Collection { return $this->applications; }
    public function getEnvironmentUsers(): Collection { return $this->environmentUsers; }
    public function getKubernetesNodes(): Collection { return $this->kubernetesNodes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function __toString(): string { return $this->name; }
}
