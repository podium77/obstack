<?php
namespace App\Entity;

use App\Enum\AlertSeverity;
use App\Repository\MetricSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MetricSnapshotRepository::class)]
#[ORM\Table(name: 'metric_snapshots')]
#[ORM\Index(columns: ['application_id', 'collected_at'], name: 'idx_snap_app_date')]
class MetricSnapshot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'metricSnapshots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $cpuPercent = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $memoryPercent = null;

    #[ORM\Column(nullable: true)]
    private ?int $memoryTotalMb = null;

    #[ORM\Column(nullable: true)]
    private ?int $memoryUsedMb = null;

    #[ORM\Column(type: 'json')]
    private array $diskStats = [];

    #[ORM\Column(length: 20)]
    private string $tomcatStatus = 'unknown';

    #[ORM\Column(length: 20)]
    private string $dbStatus = 'unknown';

    #[ORM\Column(type: 'json')]
    private array $latencies = [];

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $uptimeSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $processCount = null;

    #[ORM\Column(type: 'json')]
    private array $loadAverage = [];

    #[ORM\Column(nullable: true)]
    private ?int $activeConnections = null;

    #[ORM\Column(type: 'json')]
    private array $bottlenecks = [];

    #[ORM\Column(length: 20, enumType: AlertSeverity::class)]
    private AlertSeverity $severity = AlertSeverity::OK;

    #[ORM\Column]
    private bool $collectionSuccess = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $collectionError = null;

    #[ORM\Column]
    private \DateTimeImmutable $collectedAt;

    public function __construct() { $this->collectedAt = new \DateTimeImmutable(); }

    public function getMaxDiskPercent(): ?float {
        if (empty($this->diskStats)) return null;
        $vals = array_column(array_values($this->diskStats), 'used_percent');
        return $vals ? max($vals) : null;
    }
    public function getUptimeHours(): ?float {
        return $this->uptimeSeconds !== null ? round($this->uptimeSeconds / 3600, 1) : null;
    }
    public function getUptimeDays(): ?float {
        return $this->uptimeSeconds !== null ? round($this->uptimeSeconds / 86400, 1) : null;
    }
    public function isCollectionSuccess(): bool { return $this->collectionSuccess; }
    public function hasBottleneck(): bool { return !empty($this->bottlenecks); }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $a): static { $this->application = $a; return $this; }
    public function getCpuPercent(): ?float { return $this->cpuPercent; }
    public function setCpuPercent(?float $v): static { $this->cpuPercent = $v; return $this; }
    public function getMemoryPercent(): ?float { return $this->memoryPercent; }
    public function setMemoryPercent(?float $v): static { $this->memoryPercent = $v; return $this; }
    public function getMemoryTotalMb(): ?int { return $this->memoryTotalMb; }
    public function setMemoryTotalMb(?int $v): static { $this->memoryTotalMb = $v; return $this; }
    public function getMemoryUsedMb(): ?int { return $this->memoryUsedMb; }
    public function setMemoryUsedMb(?int $v): static { $this->memoryUsedMb = $v; return $this; }
    public function getDiskStats(): array { return $this->diskStats; }
    public function setDiskStats(array $v): static { $this->diskStats = $v; return $this; }
    public function getTomcatStatus(): string { return $this->tomcatStatus; }
    public function setTomcatStatus(string $v): static { $this->tomcatStatus = $v; return $this; }
    public function getDbStatus(): string { return $this->dbStatus; }
    public function setDbStatus(string $v): static { $this->dbStatus = $v; return $this; }
    public function getLatencies(): array { return $this->latencies; }
    public function setLatencies(array $v): static { $this->latencies = $v; return $this; }
    public function getUptimeSeconds(): ?int { return $this->uptimeSeconds; }
    public function setUptimeSeconds(?int $v): static { $this->uptimeSeconds = $v; return $this; }
    public function getProcessCount(): ?int { return $this->processCount; }
    public function setProcessCount(?int $v): static { $this->processCount = $v; return $this; }
    public function getLoadAverage(): array { return $this->loadAverage; }
    public function setLoadAverage(array $v): static { $this->loadAverage = $v; return $this; }
    public function getActiveConnections(): ?int { return $this->activeConnections; }
    public function setActiveConnections(?int $v): static { $this->activeConnections = $v; return $this; }
    public function getBottlenecks(): array { return $this->bottlenecks; }
    public function setBottlenecks(array $v): static { $this->bottlenecks = $v; return $this; }
    public function getSeverity(): AlertSeverity { return $this->severity; }
    public function setSeverity(AlertSeverity $v): static { $this->severity = $v; return $this; }
    public function setCollectionSuccess(bool $v): static { $this->collectionSuccess = $v; return $this; }
    public function getCollectionError(): ?string { return $this->collectionError; }
    public function setCollectionError(?string $v): static { $this->collectionError = $v; return $this; }
    public function getCollectedAt(): \DateTimeImmutable { return $this->collectedAt; }
    public function setCollectedAt(\DateTimeImmutable $v): static { $this->collectedAt = $v; return $this; }
}
