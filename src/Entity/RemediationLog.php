<?php
namespace App\Entity;

use App\Enum\RemediationAction;
use App\Repository\RemediationLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemediationLogRepository::class)]
#[ORM\Table(name: 'remediation_logs')]
#[ORM\Index(columns: ['application_id', 'executed_at'], name: 'idx_rem_app_date')]
class RemediationLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'remediationLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: RemediationPolicy::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RemediationPolicy $policy = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $triggeredBy = null;

    #[ORM\Column(length: 50, enumType: RemediationAction::class)]
    private RemediationAction $action;

    #[ORM\Column]
    private bool $success = false;

    #[ORM\Column(type: 'json')]
    private array $steps = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'json')]
    private array $metricsBefore = [];

    #[ORM\Column(type: 'json')]
    private array $metricsAfter = [];

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column]
    private bool $automatic = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $executedAt;

    public function __construct(Application $application, RemediationAction $action) {
        $this->application = $application;
        $this->action      = $action;
        $this->executedAt  = new \DateTimeImmutable();
    }

    public function addStep(string $message): static {
        $this->steps[] = ['time' => (new \DateTimeImmutable())->format('H:i:s'), 'message' => $message];
        return $this;
    }

    public function buildSummary(): string {
        $status  = $this->success ? '✓ Succès' : '✗ Échec';
        $trigger = $this->automatic ? 'automatique' : "par {$this->triggeredBy}";
        $dur     = $this->durationSeconds ? " ({$this->durationSeconds}s)" : '';
        $lines   = [
            "{$status} — {$this->action->getLabel()} sur {$this->application->getName()}",
            "Déclenchement {$trigger} le {$this->executedAt->format('d/m/Y à H:i:s')}{$dur}",
            '',
            'Étapes exécutées :',
        ];
        foreach ($this->steps as $s) {
            $lines[] = "  [{$s['time']}] {$s['message']}";
        }
        if ($this->errorMessage) {
            $lines[] = '';
            $lines[] = "Erreur : {$this->errorMessage}";
        }
        return implode("\n", $lines);
    }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function getApplication(): ?Application { return $this->application; }
    public function getPolicy(): ?RemediationPolicy { return $this->policy; }
    public function setPolicy(?RemediationPolicy $p): static { $this->policy = $p; return $this; }
    public function getTriggeredBy(): ?string { return $this->triggeredBy; }
    public function setTriggeredBy(?string $v): static { $this->triggeredBy = $v; return $this; }
    public function getAction(): RemediationAction { return $this->action; }
    public function isSuccess(): bool { return $this->success; }
    public function setSuccess(bool $v): static { $this->success = $v; return $this; }
    public function getSteps(): array { return $this->steps; }
    public function setSteps(array $v): static { $this->steps = $v; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): static { $this->summary = $v; return $this; }
    public function getMetricsBefore(): array { return $this->metricsBefore; }
    public function setMetricsBefore(array $v): static { $this->metricsBefore = $v; return $this; }
    public function getMetricsAfter(): array { return $this->metricsAfter; }
    public function setMetricsAfter(array $v): static { $this->metricsAfter = $v; return $this; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function setDurationSeconds(?int $v): static { $this->durationSeconds = $v; return $this; }
    public function isAutomatic(): bool { return $this->automatic; }
    public function setAutomatic(bool $v): static { $this->automatic = $v; return $this; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }
    public function getExecutedAt(): \DateTimeImmutable { return $this->executedAt; }
}
