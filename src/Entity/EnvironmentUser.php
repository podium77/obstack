<?php
namespace App\Entity;

use App\Enum\UserEnvironmentRole;
use App\Repository\EnvironmentUserRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Association utilisateur ↔ environnement avec rôle spécifique.
 * Un utilisateur peut avoir des rôles différents sur des environnements différents.
 */
#[ORM\Entity(repositoryClass: EnvironmentUserRepository::class)]
#[ORM\Table(name: 'environment_users')]
#[ORM\UniqueConstraint(name: 'uniq_env_user', fields: ['environment', 'user'])]
class EnvironmentUser
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Environment::class, inversedBy: 'environmentUsers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Environment $environment = null;

    #[ORM\ManyToOne(targetEntity: CompanyUser::class, inversedBy: 'environmentAccesses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CompanyUser $user = null;

    #[ORM\Column(length: 20, enumType: UserEnvironmentRole::class)]
    private UserEnvironmentRole $role = UserEnvironmentRole::VIEWER;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $grantedAt;

    #[ORM\ManyToOne(targetEntity: CompanyUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CompanyUser $grantedBy = null;

    public function __construct()
    {
        $this->grantedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEnvironment(): ?Environment { return $this->environment; }
    public function setEnvironment(?Environment $e): static { $this->environment = $e; return $this; }
    public function getUser(): ?CompanyUser { return $this->user; }
    public function setUser(?CompanyUser $u): static { $this->user = $u; return $this; }
    public function getRole(): UserEnvironmentRole { return $this->role; }
    public function setRole(UserEnvironmentRole $r): static { $this->role = $r; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function getGrantedAt(): \DateTimeImmutable { return $this->grantedAt; }
    public function getGrantedBy(): ?CompanyUser { return $this->grantedBy; }
    public function setGrantedBy(?CompanyUser $u): static { $this->grantedBy = $u; return $this; }
}
