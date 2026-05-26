<?php
namespace App\Entity;

use App\Enum\AlertSeverity;
use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
#[ORM\Index(columns: ['application_id', 'resolved', 'created_at'], name: 'idx_alert_app_status')]
class Alert
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\Column(length: 20, enumType: AlertSeverity::class)]
    private AlertSeverity $severity = AlertSeverity::WARNING;

    #[ORM\Column(length: 200)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $metric = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $metricValue = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $threshold = null;

    #[ORM\Column]
    private bool $resolved = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column]
    private bool $notified = false;

    #[ORM\ManyToOne(targetEntity: RemediationLog::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RemediationLog $remediationLog = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function resolve(): static {
        $this->resolved   = true;
        $this->resolvedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getAge(): string {
        $diff = $this->createdAt->diff(new \DateTimeImmutable());
        if ($diff->days > 0)  return "il y a {$diff->days}j";
        if ($diff->h > 0)     return "il y a {$diff->h}h";
        if ($diff->i > 0)     return "il y a {$diff->i}min";
        return "à l'instant";
    }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): static { $this->id = $id; return $this; }
    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $a): static { $this->application = $a; return $this; }
    public function getSeverity(): AlertSeverity { return $this->severity; }
    public function setSeverity(AlertSeverity $s): static { $this->severity = $s; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): static { $this->title = $t; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $m): static { $this->message = $m; return $this; }
    public function getMetric(): ?string { return $this->metric; }
    public function setMetric(?string $m): static { $this->metric = $m; return $this; }
    public function getMetricValue(): ?float { return $this->metricValue; }
    public function setMetricValue(?float $v): static { $this->metricValue = $v; return $this; }
    public function getThreshold(): ?float { return $this->threshold; }
    public function setThreshold(?float $v): static { $this->threshold = $v; return $this; }
    public function isResolved(): bool { return $this->resolved; }
    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function isNotified(): bool { return $this->notified; }
    public function setNotified(bool $v): static { $this->notified = $v; return $this; }
    public function getRemediationLog(): ?RemediationLog { return $this->remediationLog; }
    public function setRemediationLog(?RemediationLog $r): static { $this->remediationLog = $r; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
