<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une permission fine dans le système RBAC.
 *
 * Permissions couvertes:
 *  - Gestion des entreprises (créer, modifier, supprimer)
 *  - Gestion des utilisateurs (créer, modifier, supprimer, assigner)
 *  - Gestion des environnements (créer, modifier, supprimer)
 *  - Gestion des applications (créer, modifier, supprimer)
 *  - Accès à la console d'administration système
 *  - Gestion des connexions de bases de données
 *
 * Portées:
 *  - GLOBAL: Permission globale (admin système)
 *  - COMPANY: Permission au niveau d'une entreprise
 *  - ENVIRONMENT: Permission au niveau d'un environnement
 *  - RESOURCE: Permission basée sur la propriété d'une ressource
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permissions')]
#[ORM\UniqueConstraint(name: 'uniq_permission_code', fields: ['code'])]
class Permission
{
    // Global permissions
    const ADMIN_ACCESS_CONSOLE = 'admin.access_console';
    const ADMIN_MANAGE_COMPANIES = 'admin.manage_companies';
    const ADMIN_MANAGE_USERS = 'admin.manage_users';
    const ADMIN_MANAGE_DATABASE_CONNECTIONS = 'admin.manage_database_connections';
    const ADMIN_EXECUTE_QUERIES = 'admin.execute_queries';
    const ADMIN_VIEW_AUDIT = 'admin.view_audit';

    // Company-level permissions
    const COMPANY_MANAGE_USERS = 'company.manage_users';
    const COMPANY_MANAGE_ENVIRONMENTS = 'company.manage_environments';
    const COMPANY_MANAGE_APPLICATIONS = 'company.manage_applications';
    const COMPANY_VIEW_ANALYTICS = 'company.view_analytics';

    // Environment-level permissions
    const ENVIRONMENT_MANAGE_AGENTS = 'environment.manage_agents';
    const ENVIRONMENT_VIEW_APPLICATIONS = 'environment.view_applications';
    const ENVIRONMENT_MANAGE_USERS = 'environment.manage_users';

    // Resource-level permissions
    const RESOURCE_CREATE_APPLICATION = 'resource.create_application';
    const RESOURCE_MODIFY_APPLICATION = 'resource.modify_application';
    const RESOURCE_DELETE_APPLICATION = 'resource.delete_application';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Code unique de la permission (ex: 'admin.manage_companies') */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $code = '';

    /** Description lisible de la permission */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    /** Portée: global | company | environment | resource */
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(['global', 'company', 'environment', 'resource'])]
    private string $scope = 'global';

    /** Catégorie: admin | company | environment | application */
    #[ORM\Column(length: 50)]
    private string $category = 'admin';

    /** Rôles ayant cette permission */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'permissions')]
    private Collection $roles;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): self
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
