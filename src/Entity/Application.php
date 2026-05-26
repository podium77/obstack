<?php
namespace App\Entity;

use App\Enum\MachineType;
use App\Enum\OsType;
use App\Enum\DbType;
use App\Enum\TechnologyType;
use App\Repository\ApplicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Application/stack supervisée, appartenant à un environnement.
 * Contient la détection automatique du type de machine (VM/physique)
 * et des technologies installées.
 */
#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'applications')]
#[ORM\HasLifecycleCallbacks]
class Application
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Environment::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Environment $environment = null;

    /** Token agent associé */
    #[ORM\OneToOne(targetEntity: AgentToken::class, mappedBy: 'application')]
    private ?AgentToken $agentToken = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: OsType::class)]
    private OsType $osType = OsType::DEBIAN;

    #[ORM\Column(length: 20, enumType: DbType::class, nullable: true)]
    private ?DbType $dbType = null;

    /** Type de machine détecté automatiquement par l'agent */
    #[ORM\Column(length: 30, enumType: MachineType::class)]
    private MachineType $machineType = MachineType::UNKNOWN;

    /** Technologies détectées par l'agent */
    #[ORM\Column(type: 'json')]
    private array $detectedTechnologies = [];
    // Format: [{'type': 'tomcat', 'version': '9.0.65', 'port': 8080, 'service': 'tomcat9', 'status': 'running'}]

    /** Informations hardware détectées */
    #[ORM\Column(type: 'json')]
    private array $hardwareInfo = [
        'cpu_model'       => null,
        'cpu_cores'       => null,
        'cpu_threads'     => null,
        'total_ram_gb'    => null,
        'disks'           => [],
        'network_interfaces' => [],
        'serial_number'   => null,
        'manufacturer'    => null,
        'product_name'    => null,
        'bios_version'    => null,
        'hypervisor'      => null, // si VM: VMware/KVM/HyperV...
        'vm_uuid'         => null,
    ];

    /** Informations système */
    #[ORM\Column(type: 'json')]
    private array $systemInfo = [
        'hostname'        => null,
        'fqdn'            => null,
        'kernel_version'  => null,
        'os_version'      => null,
        'os_release'      => null,
        'architecture'    => null,
        'timezone'        => null,
        'locale'          => null,
        'is_container'    => false,
        'container_id'    => null,
        'kubernetes_node' => null, // nom du node K8s si applicable
    ];

    /** Ce host est-il un node Kubernetes ? */
    #[ORM\Column]
    private bool $isKubernetesNode = false;

    /** Node Kubernetes associé (si is_kubernetes_node = true) */
    #[ORM\ManyToOne(targetEntity: KubernetesNode::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?KubernetesNode $kubernetesNode = null;

    // Connexion SSH
    #[ORM\Column(length: 255)]
    private string $hostAddress = '';

    #[ORM\Column]
    private int $sshPort = 22;

    #[ORM\Column(length: 100)]
    private string $sshUser = 'obstack';

    #[ORM\Column(length: 500)]
    private string $sshKeyPath = '/var/lib/obstack/.ssh/id_rsa';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $healthUrl = null;

    #[ORM\Column(type: 'json')]
    private array $tomcatConfig = [
    'service_name' => 'tomcat9',
    'webapps_dir'  => '/opt/tomcat/webapps',
    'logs_dir'     => '/opt/tomcat/logs',
    'port'         => 8080,
];

    #[ORM\Column(type: 'json')]
    private array $dbConfig = [
    'service_name'          => '',
    'data_dir'              => '',
    'backup_dir'            => '/var/backups/db',
    'backup_retention_days' => 7,
    'oracle_sid'            => 'ORCL',
    'oracle_home'           => '/opt/oracle/product/19c/dbhome_1',
    'db_user'               => 'sys',
    'db_host'               => '127.0.0.1',
];

    #[ORM\Column(type: 'json')]
    private array $thresholds = [
        'cpu_warning'      => 75.0,
        'cpu_critical'     => 90.0,
        'memory_warning'   => 70.0,
        'memory_critical'  => 85.0,
        'disk_warning'     => 75.0,
        'disk_critical'    => 90.0,
        'latency_warning'  => 100,
        'latency_critical' => 500,
    ];

    #[ORM\Column(nullable: true)]
    private ?int $uptimeRestartThresholdHours = null;

    #[ORM\Column(type: 'json')]
    private array $uptimeRestartSchedule = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 7)]
    private string $color = '#185FA5';

    #[ORM\OneToMany(targetEntity: RemediationPolicy::class, mappedBy: 'application', cascade: ['all'], orphanRemoval: true)]
    private Collection $remediationPolicies;

    #[ORM\OneToMany(targetEntity: MetricSnapshot::class, mappedBy: 'application', cascade: ['remove'])]
    private Collection $metricSnapshots;

    #[ORM\OneToMany(targetEntity: RemediationLog::class, mappedBy: 'application', cascade: ['remove'])]
    private Collection $remediationLogs;

    /** Date de la dernière détection automatique des technologies */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastDetectionAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->remediationPolicies = new ArrayCollection();
        $this->metricSnapshots     = new ArrayCollection();
        $this->remediationLogs     = new ArrayCollection();
        $this->createdAt           = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /** Retourne les technologies détectées par catégorie */
    public function getTechnologiesByCategory(): array
    {
        $byCategory = [];
        foreach ($this->detectedTechnologies as $tech) {
            $type = TechnologyType::tryFrom($tech['type'] ?? '');
            if (!$type) continue;
            $cat = $type->getCategory();
            $byCategory[$cat][] = $tech;
        }
        return $byCategory;
    }

    /** Vérifie si une technologie est présente */
    public function hasTechnology(TechnologyType $type): bool
    {
        foreach ($this->detectedTechnologies as $tech) {
            if ($tech['type'] === $type->value) return true;
        }
        return false;
    }

    /** Retourne le type de machine humainement lisible */
    public function getMachineTypeLabel(): string
    {
        return $this->machineType->getLabel();
    }

    public function isVirtualMachine(): bool
    {
        return $this->machineType->isVirtual();
    }

    // --- Getters & Setters ---
    public function getId(): ?int { return $this->id; }
    public function getEnvironment(): ?Environment { return $this->environment; }
    public function setEnvironment(?Environment $e): static { $this->environment = $e; return $this; }
    public function getAgentToken(): ?AgentToken { return $this->agentToken; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getOsType(): OsType { return $this->osType; }
    public function setOsType(OsType $o): static { $this->osType = $o; return $this; }
    public function getDbType(): ?DbType { return $this->dbType; }
    public function setDbType(?DbType $d): static { $this->dbType = $d; return $this; }
    public function getMachineType(): MachineType { return $this->machineType; }
    public function setMachineType(MachineType $m): static { $this->machineType = $m; return $this; }
    public function getDetectedTechnologies(): array { return $this->detectedTechnologies; }
    public function setDetectedTechnologies(array $t): static { $this->detectedTechnologies = $t; return $this; }
    public function getHardwareInfo(): array { return $this->hardwareInfo; }
    public function setHardwareInfo(array $h): static { $this->hardwareInfo = $h; return $this; }
    public function getSystemInfo(): array { return $this->systemInfo; }
    public function setSystemInfo(array $s): static { $this->systemInfo = $s; return $this; }
    public function isKubernetesNode(): bool { return $this->isKubernetesNode; }
    public function setIsKubernetesNode(bool $v): static { $this->isKubernetesNode = $v; return $this; }
    public function getKubernetesNode(): ?KubernetesNode { return $this->kubernetesNode; }
    public function setKubernetesNode(?KubernetesNode $n): static { $this->kubernetesNode = $n; return $this; }
    public function getHostAddress(): string { return $this->hostAddress; }
    public function setHostAddress(string $h): static { $this->hostAddress = $h; return $this; }
    public function getSshPort(): int { return $this->sshPort; }
    public function setSshPort(int $p): static { $this->sshPort = $p; return $this; }
    public function getSshUser(): string { return $this->sshUser; }
    public function setSshUser(string $u): static { $this->sshUser = $u; return $this; }
    public function getSshKeyPath(): string { return $this->sshKeyPath; }
    public function setSshKeyPath(string $k): static { $this->sshKeyPath = $k; return $this; }
    public function getHealthUrl(): ?string { return $this->healthUrl; }
    public function setHealthUrl(?string $u): static { $this->healthUrl = $u; return $this; }
    public function getTomcatConfig(): array { return $this->tomcatConfig; }
    public function setTomcatConfig(array $c): static { $this->tomcatConfig = $c; return $this; }
    public function getDbConfig(): array { return $this->dbConfig; }
    public function setDbConfig(array $c): static { $this->dbConfig = $c; return $this; }
    public function getThresholds(): array { return $this->thresholds; }
    public function setThresholds(array $t): static { $this->thresholds = $t; return $this; }
    public function getUptimeRestartThresholdHours(): ?int { return $this->uptimeRestartThresholdHours; }
    public function setUptimeRestartThresholdHours(?int $h): static { $this->uptimeRestartThresholdHours = $h; return $this; }
    public function getUptimeRestartSchedule(): array { return $this->uptimeRestartSchedule; }
    public function setUptimeRestartSchedule(array $s): static { $this->uptimeRestartSchedule = $s; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getColor(): string { return $this->color; }
    public function setColor(string $c): static { $this->color = $c; return $this; }
    public function getRemediationPolicies(): Collection { return $this->remediationPolicies; }
    public function getMetricSnapshots(): Collection { return $this->metricSnapshots; }
    public function getRemediationLogs(): Collection { return $this->remediationLogs; }
    public function getLastDetectionAt(): ?\DateTimeImmutable { return $this->lastDetectionAt; }
    public function setLastDetectionAt(?\DateTimeImmutable $v): static { $this->lastDetectionAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function __toString(): string { return $this->name; }
}
