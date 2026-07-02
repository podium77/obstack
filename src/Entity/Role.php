<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un rôle dans le système RBAC.
 *
 * Rôles disponibles:
 *  - GLOBAL_ADMIN : Administrateur global, accès à toutes les entreprises et consoles système
 *  - COMPANY_ADMIN : Administrateur d'entreprise, gestion totale de sa société
 *  - USER : Utilisateur standard, accès aux environnements assignés
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
#[ORM\UniqueConstraint(name: 'uniq_role_name', fields: ['name'])]
class Role
{
    const GLOBAL_ADMIN = 'GLOBAL_ADMIN';
    const COMPANY_ADMIN = 'COMPANY_ADMIN';
    const USER = 'USER';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    /** Portée du rôle: global | company | environment | resource */
    #[ORM\Column(length: 50)]
    private string $scope = 'global';

    /** Permissions attachées à ce rôle */
    #[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'roles')]
    #[ORM\JoinTable(name: 'role_permissions')]
    private Collection $permissions;

    /** Rôles hérités (ex: COMPANY_ADMIN hérite de USER) */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(
        name: 'role_inheritance',
        joinColumns: new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', onDelete: 'CASCADE'),
        inverseJoinColumns: new ORM\JoinColumn(name: 'parent_role_id', referencedColumnName: 'id', onDelete: 'CASCADE')
    )]
    private Collection $inheritedRoles;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->permissions = new ArrayCollection();
        $this->inheritedRoles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function addPermission(Permission $permission): self
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
            $permission->addRole($this);
        }
        return $this;
    }

    public function removePermission(Permission $permission): self
    {
        $this->permissions->removeElement($permission);
        $permission->removeRole($this);
        return $this;
    }

    public function getInheritedRoles(): Collection
    {
        return $this->inheritedRoles;
    }

    public function addInheritedRole(Role $role): self
    {
        if (!$this->inheritedRoles->contains($role)) {
            $this->inheritedRoles->add($role);
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
}
