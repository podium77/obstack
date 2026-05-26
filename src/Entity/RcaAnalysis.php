<?php
namespace App\Entity;

use App\Enum\RcaStatus;
use App\Repository\RcaAnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RcaAnalysisRepository::class)]
#[ORM\Table(name: 'rca_analyses')]
#[ORM\HasLifecycleCallbacks]
class RcaAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Alert::class, inversedBy: 'rcaAnalyses')]
    #[ORM\JoinColumn(nullable: false)]
    private Alert $alert;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $probableCause = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recommendations = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metricsAtTrigger = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metricsUnits = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $thresholds = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $timeline = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: RcaStatus::class)]
    private RcaStatus $status = RcaStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- Getters/Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlert(): Alert
    {
        return $this->alert;
    }

    public function setAlert(Alert $alert): self
    {
        $this->alert = $alert;
        return $this;
    }

    public function getProbableCause(): ?string
    {
        return $this->probableCause;
    }

    public function setProbableCause(?string $probableCause): self
    {
        $this->probableCause = $probableCause;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getRecommendations(): ?array
    {
        return $this->recommendations;
    }

    public function setRecommendations(?array $recommendations): self
    {
        $this->recommendations = $recommendations;
        return $this;
    }

    public function addRecommendation(string $recommendation): self
    {
        $this->recommendations[] = $recommendation;
        return $this;
    }

    public function getMetricsAtTrigger(): ?array
    {
        return $this->metricsAtTrigger;
    }

    public function setMetricsAtTrigger(?array $metricsAtTrigger): self
    {
        $this->metricsAtTrigger = $metricsAtTrigger;
        return $this;
    }

    public function getMetricsUnits(): ?array
    {
        return $this->metricsUnits;
    }

    public function setMetricsUnits(?array $metricsUnits): self
    {
        $this->metricsUnits = $metricsUnits;
        return $this;
    }

    public function getThresholds(): ?array
    {
        return $this->thresholds;
    }

    public function setThresholds(?array $thresholds): self
    {
        $this->thresholds = $thresholds;
        return $this;
    }

    public function getTimeline(): ?array
    {
        return $this->timeline;
    }

    public function setTimeline(?array $timeline): self
    {
        $this->timeline = $timeline;
        return $this;
    }

    public function addTimelineEvent(array $event): self
    {
        $this->timeline[] = $event;
        return $this;
    }

    public function getStatus(): RcaStatus
    {
        return $this->status;
    }

    public function setStatus(RcaStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
