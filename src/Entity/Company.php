<?php
namespace App\Entity;

use App\Enum\EnvironmentType;
use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un tenant/entreprise dans la plateforme multi-tenant.
 * Chaque entreprise possède:
 *  - Un superadmin
 *  - Un environnement "default" créé automatiquement
 *  - Plusieurs environnements additionnels
 *  - Une clé de licence unique
 *  - Des tokens agents spécifiques
 *  - Configuration optionnelle PyRCA + Knowledge Graph
 */
#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'companies')]
#[ORM\UniqueConstraint(name: 'uniq_slug', fields: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Company
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private string $name = '';

    /** Identifiant URL-safe unique de l'entreprise */
    #[ORM\Column(length: 100)]
    private string $slug = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    /** Clé de licence unique générée à la création */
    #[ORM\Column(length: 64, unique: true)]
    private string $licenseKey = '';

    /** Logo base64 ou chemin */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $logo = null;

    /** Couleur principale de la marque */
    #[ORM\Column(length: 7)]
    private string $brandColor = '#185FA5';

    /** Domaine LDAP de l'entreprise */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ldapHost = null;

    #[ORM\Column(nullable: true)]
    private ?int $ldapPort = 389;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapBaseDn = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapBindDn = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapBindPassword = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapUserBaseDn = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapGroupBaseDn = null;

    /** Intégration PyRCA activée */
    #[ORM\Column]
    private bool $rcaEnabled = false;

    /** Configuration PyRCA */
    #[ORM\Column(type: 'json')]
    private array $rcaConfig = [
        'backend'          => 'pyrca',  // pyrca | custom
        'api_url'          => null,
        'api_key'          => null,
        'model'            => 'bayesian', // bayesian | causal | ml
        'auto_analyze'     => false,
        'severity_trigger' => 'critical',
    ];

    /** Knowledge Graph activé */
    #[ORM\Column]
    private bool $kgEnabled = false;

    /** Configuration Knowledge Graph */
    #[ORM\Column(type: 'json')]
    private array $kgConfig = [
        'backend'    => 'neo4j',  // neo4j | arangodb | custom
        'uri'        => null,
        'user'       => null,
        'password'   => null,
        'database'   => 'obstack',
        'auto_sync'  => true,
    ];

    /** Nombre maximum d'environnements autorisés */
    #[ORM\Column]
    private int $maxEnvironments = 10;

    /** Nombre maximum d'agents par environnement */
    #[ORM\Column]
    private int $maxAgentsPerEnv = 50;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(targetEntity: Environment::class, mappedBy: 'company', cascade: ['all'], orphanRemoval: true)]
    private Collection $environments;

    #[ORM\OneToMany(targetEntity: CompanyUser::class, mappedBy: 'company', cascade: ['all'], orphanRemoval: true)]
    private Collection $companyUsers;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->environments  = new ArrayCollection();
        $this->companyUsers  = new ArrayCollection();
        $this->createdAt     = new \DateTimeImmutable();
        $this->licenseKey    = bin2hex(random_bytes(32));
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** Crée l'environnement par défaut lors de l'inscription */
    public function initDefaultEnvironment(): Environment
    {
        $env = new Environment();
        $env->setCompany($this)
            ->setName('Environnement par défaut')
            ->setSlug('default')
            ->setType(EnvironmentType::DEFAULT)
            ->setIsDefault(true)
            ->setDescription('Environnement initial créé automatiquement');

        $this->environments->add($env);
        return $env;
    }

    /** Génère un slug depuis le nom */
    public function generateSlug(): void
    {
        $slug = strtolower(trim($this->name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        $this->slug = $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $s): static { $this->slug = $s; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getLicenseKey(): string { return $this->licenseKey; }
    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $l): static { $this->logo = $l; return $this; }
    public function getBrandColor(): string { return $this->brandColor; }
    public function setBrandColor(string $c): static { $this->brandColor = $c; return $this; }
    public function getLdapHost(): ?string { return $this->ldapHost; }
    public function setLdapHost(?string $h): static { $this->ldapHost = $h; return $this; }
    public function getLdapPort(): ?int { return $this->ldapPort; }
    public function setLdapPort(?int $p): static { $this->ldapPort = $p; return $this; }
    public function getLdapBaseDn(): ?string { return $this->ldapBaseDn; }
    public function setLdapBaseDn(?string $d): static { $this->ldapBaseDn = $d; return $this; }
    public function getLdapBindDn(): ?string { return $this->ldapBindDn; }
    public function setLdapBindDn(?string $d): static { $this->ldapBindDn = $d; return $this; }
    public function getLdapBindPassword(): ?string { return $this->ldapBindPassword; }
    public function setLdapBindPassword(?string $p): static { $this->ldapBindPassword = $p; return $this; }
    public function getLdapUserBaseDn(): ?string { return $this->ldapUserBaseDn; }
    public function setLdapUserBaseDn(?string $d): static { $this->ldapUserBaseDn = $d; return $this; }
    public function getLdapGroupBaseDn(): ?string { return $this->ldapGroupBaseDn; }
    public function setLdapGroupBaseDn(?string $d): static { $this->ldapGroupBaseDn = $d; return $this; }
    public function isRcaEnabled(): bool { return $this->rcaEnabled; }
    public function setRcaEnabled(bool $v): static { $this->rcaEnabled = $v; return $this; }
    public function getRcaConfig(): array { return $this->rcaConfig; }
    public function setRcaConfig(array $c): static { $this->rcaConfig = $c; return $this; }
    public function isKgEnabled(): bool { return $this->kgEnabled; }
    public function setKgEnabled(bool $v): static { $this->kgEnabled = $v; return $this; }
    public function getKgConfig(): array { return $this->kgConfig; }
    public function setKgConfig(array $c): static { $this->kgConfig = $c; return $this; }
    public function getMaxEnvironments(): int { return $this->maxEnvironments; }
    public function setMaxEnvironments(int $v): static { $this->maxEnvironments = $v; return $this; }
    public function getMaxAgentsPerEnv(): int { return $this->maxAgentsPerEnv; }
    public function setMaxAgentsPerEnv(int $v): static { $this->maxAgentsPerEnv = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getEnvironments(): Collection { return $this->environments; }
    public function getCompanyUsers(): Collection { return $this->companyUsers; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function hasLdap(): bool { return !empty($this->ldapHost); }
    public function __toString(): string { return $this->name; }
}
