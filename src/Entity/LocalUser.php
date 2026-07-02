<?php

namespace App\Entity;

use App\Repository\LocalUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LocalUserRepository::class)]
#[ORM\Table(name: 'local_users')]
#[ORM\UniqueConstraint(name: 'UNIQ_username', fields: ['username'])]
#[ORM\HasLifecycleCallbacks]
class LocalUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $username = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private ?string $password = null;

    /** Compte actif */
    #[ORM\Column]
    private bool $active = true;

    /** Source: local|ldap */
    #[ORM\Column(length: 20)]
    private string $source = 'local';

    /** Administrateur global (seulement pour 'admin') */
    #[ORM\Column]
    private bool $isGlobalAdmin = false;

    /** Rôle RBAC principal */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Role $role = null;

    /** Permissions supplémentaires (override de rôle) */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'local_user_permissions')]
    private Collection $permissions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->permissions = new ArrayCollection();
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        $roles = [];
        
        // Si admin global, donner tous les rôles
        if ($this->isGlobalAdmin) {
            return ['ROLE_ADMIN', 'ROLE_USER'];
        }
        
        // Sinon, récupérer du rôle RBAC
        if ($this->role) {
            // Ajouter le rôle principal
            $roles[] = 'ROLE_' . strtoupper($this->role->getName());
            
            // Ajouter les rôles hérités
            foreach ($this->role->getInheritedRoles() as $inherited) {
                $roles[] = 'ROLE_' . strtoupper($inherited->getName());
            }
        }
        
        // Fallback
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }
        
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getId(): ?int { return $this->id; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $u): static { $this->username = $u; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): static { $this->email = $e; return $this; }

    public function getDisplayName(): ?string { return $this->displayName ?? $this->username; }
    public function setDisplayName(?string $d): static { $this->displayName = $d; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $s): static { $this->source = $s; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $v): static { $this->lastLoginAt = $v; return $this; }

    public function isGlobalAdmin(): bool
    {
        return $this->isGlobalAdmin;
    }

    /**
     * Marquer comme administrateur global.
     * Ne doit être utilisé que pour le compte 'admin'.
     */
    public function setIsGlobalAdmin(bool $v): static
    {
        $this->isGlobalAdmin = $v;
        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): static
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
        return $this;
    }

    public function removePermission(Permission $permission): static
    {
        $this->permissions->removeElement($permission);
        return $this;
    }

    public function hasPermission(string $permissionCode): bool
    {
        // Admin global a toutes les permissions
        if ($this->isGlobalAdmin) {
            return true;
        }

        // Vérifier dans les permissions explicites
        foreach ($this->permissions as $permission) {
            if ($permission->getCode() === $permissionCode) {
                return true;
            }
        }

        // Vérifier dans le rôle
        if ($this->role) {
            foreach ($this->role->getPermissions() as $permission) {
                if ($permission->getCode() === $permissionCode) {
                    return true;
                }
            }
        }

        return false;
    }

    public function __toString(): string { return $this->getDisplayName() ?? $this->username; }
}
