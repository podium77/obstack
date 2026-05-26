<?php
namespace App\Entity;

use App\Repository\CompanyUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Utilisateur d'une entreprise.
 *
 * Types:
 *  - SUPERADMIN : créé automatiquement à l'inscription de l'entreprise,
 *                 accès total à tous les environnements
 *  - LDAP       : utilisateur provenant de l'annuaire OpenLDAP de l'entreprise
 *  - LOCAL      : compte local (max 1 par environnement hors superadmin)
 *
 * Règle métier:
 *  - 1 seul superadmin par entreprise (non supprimable)
 *  - Les utilisateurs LDAP peuvent avoir des droits sur plusieurs environnements
 *  - Maximum 1 utilisateur LOCAL (non-LDAP) par environnement
 */
#[ORM\Entity(repositoryClass: CompanyUserRepository::class)]
#[ORM\Table(name: 'company_users')]
#[ORM\UniqueConstraint(name: 'uniq_company_username', fields: ['company', 'username'])]
#[ORM\HasLifecycleCallbacks]
class CompanyUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    const TYPE_SUPERADMIN = 'superadmin';
    const TYPE_LDAP       = 'ldap';
    const TYPE_LOCAL      = 'local';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'companyUsers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Company $company = null;

    #[ORM\Column(length: 150)]
    private string $username = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $email = null;

    /** superadmin | ldap | local */
    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_LOCAL;

    /** DN LDAP si type=ldap */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $ldapDn = null;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private bool $active = true;

    /** Accès à TOUS les environnements (superadmin uniquement) */
    #[ORM\Column]
    private bool $globalAccess = false;

    #[ORM\OneToMany(targetEntity: EnvironmentUser::class, mappedBy: 'user', cascade: ['all'], orphanRemoval: true)]
    private Collection $environmentAccesses;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->environmentAccesses = new ArrayCollection();
        $this->createdAt           = new \DateTimeImmutable();
    }

    // UserInterface
    public function getUserIdentifier(): string { return $this->username; }
    public function getRoles(): array {
        if ($this->type === self::TYPE_SUPERADMIN) {
            return ['ROLE_SUPERADMIN', 'ROLE_ADMIN', 'ROLE_USER'];
        }
        return ['ROLE_USER'];
    }
    public function getPassword(): ?string { return $this->password; }
    public function eraseCredentials(): void {}

    public function isSuperAdmin(): bool { return $this->type === self::TYPE_SUPERADMIN; }
    public function isLdap(): bool       { return $this->type === self::TYPE_LDAP; }
    public function isLocal(): bool      { return $this->type === self::TYPE_LOCAL; }

    /** Vérifie si l'utilisateur a accès à un environnement donné */
    public function hasAccessToEnvironment(Environment $env): bool
    {
        if ($this->globalAccess || $this->isSuperAdmin()) {
            return true;
        }
        foreach ($this->environmentAccesses as $access) {
            if ($access->getEnvironment() === $env && $access->isActive()) {
                return true;
            }
        }
        return false;
    }

    /** Retourne le rôle de l'utilisateur dans un environnement */
    public function getRoleInEnvironment(Environment $env): ?\App\Enum\UserEnvironmentRole
    {
        if ($this->isSuperAdmin()) {
            return \App\Enum\UserEnvironmentRole::OWNER;
        }
        foreach ($this->environmentAccesses as $access) {
            if ($access->getEnvironment() === $env && $access->isActive()) {
                return $access->getRole();
            }
        }
        return null;
    }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): static { $this->id = $id; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $c): static { $this->company = $c; return $this; }
    public function getUsername(): string { return $this->username; }
    public function setUsername(string $u): static { $this->username = $u; return $this; }
    public function getDisplayName(): ?string { return $this->displayName ?? $this->username; }
    public function setDisplayName(?string $d): static { $this->displayName = $d; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): static { $this->email = $e; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $t): static { $this->type = $t; return $this; }
    public function getLdapDn(): ?string { return $this->ldapDn; }
    public function setLdapDn(?string $d): static { $this->ldapDn = $d; return $this; }
    public function setPassword(?string $p): static { $this->password = $p; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function hasGlobalAccess(): bool { return $this->globalAccess; }
    public function setGlobalAccess(bool $v): static { $this->globalAccess = $v; return $this; }
    public function getEnvironmentAccesses(): Collection { return $this->environmentAccesses; }
    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $v): static { $this->lastLoginAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getInitials(): string {
        $parts = explode(' ', $this->getDisplayName());
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
    public function __toString(): string { return $this->getDisplayName() ?? $this->username; }
}
