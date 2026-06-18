<?php
namespace App\Entity;

use App\Repository\AgentTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AgentTokenRepository::class)]
#[ORM\Table(name: 'agent_tokens')]
#[ORM\HasLifecycleCallbacks]
class AgentToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $token;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastHeartbeatAt = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $detectedHostname = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $detectedIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $installScript = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $modules = [];

    #[ORM\ManyToOne(targetEntity: Environment::class, inversedBy: 'agentTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private Environment $environment;

    #[ORM\OneToOne(targetEntity: Application::class, inversedBy: 'agentToken')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Application $application = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->regenerateToken();
    }

    // --- Getters/Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }
        if ($this->expiresAt && $this->expiresAt < new \DateTimeImmutable()) {
            return false;
        }
        return true;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getLastHeartbeatAt(): ?\DateTimeImmutable
    {
        return $this->lastHeartbeatAt;
    }

    public function setLastHeartbeatAt(?\DateTimeImmutable $lastHeartbeatAt): self
    {
        $this->lastHeartbeatAt = $lastHeartbeatAt;
        return $this;
    }

    public function getDetectedHostname(): ?string
    {
        return $this->detectedHostname;
    }

    public function setDetectedHostname(?string $detectedHostname): self
    {
        $this->detectedHostname = $detectedHostname;
        return $this;
    }

    public function getDetectedIp(): ?string
    {
        return $this->detectedIp;
    }

    public function setDetectedIp(?string $detectedIp): self
    {
        $this->detectedIp = $detectedIp;
        return $this;
    }

    public function getInstallScript(): ?string
    {
        return $this->installScript;
    }

    public function setInstallScript(?string $installScript): self
    {
        $this->installScript = $installScript;
        return $this;
    }

    public function getModules(): array
    {
        return $this->modules ?? [];
    }

    public function setModules(array $modules): self
    {
        $this->modules = $modules;
        return $this;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function setEnvironment(Environment $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;
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

    // --- Méthodes métiers ---

    public function regenerateToken(): self
    {
        $this->token = bin2hex(random_bytes(32));
        return $this;
    }

    public function getMaskedToken(): string
    {
        $t = $this->token ?? '';
        $len = strlen($t);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($t, 0, 4) . str_repeat('*', max(0, $len - 8)) . substr($t, -4);
    }

    public function recordHeartbeat(string $ip, string $hostname): self
    {
        $this->setDetectedIp($ip);
        $this->setDetectedHostname($hostname);
        $this->setLastHeartbeatAt(new \DateTimeImmutable());
        return $this;
    }

    public function revoke(): self
    {
        $this->setIsActive(false);
        $this->setExpiresAt(new \DateTimeImmutable());
        return $this;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isOnline(int $timeoutSeconds = 120): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->lastHeartbeatAt === null) {
            return false;
        }

        return $this->lastHeartbeatAt >= new \DateTimeImmutable("-{$timeoutSeconds} seconds");
    }
}
