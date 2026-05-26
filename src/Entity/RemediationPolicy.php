<?php
namespace App\Entity;

use App\Enum\RemediationAction;
use App\Enum\TriggerMetric;
use App\Repository\RemediationPolicyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RemediationPolicyRepository::class)]
#[ORM\Table(name: 'remediation_policies')]
#[ORM\HasLifecycleCallbacks]
class RemediationPolicy
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'remediationPolicies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\Column(length: 200)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, enumType: TriggerMetric::class)]
    private TriggerMetric $triggerMetric = TriggerMetric::CPU_PERCENT;

    #[ORM\Column(type: 'float')]
    private float $threshold = 80.0;

    #[ORM\Column(length: 5)]
    private string $operator = 'gte';

    #[ORM\Column(length: 50, enumType: RemediationAction::class)]
    private RemediationAction $action = RemediationAction::TOMCAT_RESTART;

    #[ORM\Column(type: 'json')]
    private array $actionParams = [];

    #[ORM\Column]
    private bool $autoExecute = false;

    #[ORM\Column]
    private int $cooldownMinutes = 30;

    #[ORM\Column]
    private int $priority = 100;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column]
    private int $maxConsecutiveExecutions = 3;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastExecutedAt = null;

    #[ORM\Column]
    private int $consecutiveExecutions = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function isInCooldown(): bool {
        if ($this->lastExecutedAt === null) return false;
        return $this->lastExecutedAt->modify("+{$this->cooldownMinutes} minutes") > new \DateTimeImmutable();
    }

    public function isThresholdReached(float $value): bool {
        return match($this->operator) {
            'gt'  => $value >  $this->threshold,
            'gte' => $value >= $this->threshold,
            'lt'  => $value <  $this->threshold,
            'lte' => $value <= $this->threshold,
            'eq'  => abs($value - $this->threshold) < 0.001,
            default => false,
        };
    }

    public function recordExecution(): void {
        $this->lastExecutedAt        = new \DateTimeImmutable();
        $this->consecutiveExecutions++;
    }

    public function resetConsecutiveExecutions(): void { $this->consecutiveExecutions = 0; }
    public function isEscalationNeeded(): bool { return $this->consecutiveExecutions >= $this->maxConsecutiveExecutions; }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $a): static { $this->application = $a; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
    public function getTriggerMetric(): TriggerMetric { return $this->triggerMetric; }
    public function setTriggerMetric(TriggerMetric $m): static { $this->triggerMetric = $m; return $this; }
    public function getThreshold(): float { return $this->threshold; }
    public function setThreshold(float $t): static { $this->threshold = $t; return $this; }
    public function getOperator(): string { return $this->operator; }
    public function setOperator(string $o): static { $this->operator = $o; return $this; }
    public function getAction(): RemediationAction { return $this->action; }
    public function setAction(RemediationAction $a): static { $this->action = $a; return $this; }
    public function getActionParams(): array { return $this->actionParams; }
    public function setActionParams(array $p): static { $this->actionParams = $p; return $this; }
    public function isAutoExecute(): bool { return $this->autoExecute; }
    public function setAutoExecute(bool $v): static { $this->autoExecute = $v; return $this; }
    public function getCooldownMinutes(): int { return $this->cooldownMinutes; }
    public function setCooldownMinutes(int $v): static { $this->cooldownMinutes = $v; return $this; }
    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $v): static { $this->priority = $v; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $v): static { $this->enabled = $v; return $this; }
    public function getMaxConsecutiveExecutions(): int { return $this->maxConsecutiveExecutions; }
    public function setMaxConsecutiveExecutions(int $v): static { $this->maxConsecutiveExecutions = $v; return $this; }
    public function getLastExecutedAt(): ?\DateTimeImmutable { return $this->lastExecutedAt; }
    public function getConsecutiveExecutions(): int { return $this->consecutiveExecutions; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function __toString(): string { return $this->name; }
}
