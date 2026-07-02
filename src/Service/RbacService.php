<?php

namespace App\Service;

use App\Entity\LocalUser;
use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service RBAC pour la gestion des rôles et permissions.
 * 
 * Gère:
 *  - Vérification des permissions globales
 *  - Vérification des permissions au niveau entreprise
 *  - Vérification des permissions au niveau environnement
 *  - Vérification des permissions basées sur la propriété
 */
class RbacService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Vérifier si un utilisateur possède une permission.
     * 
     * @param LocalUser $user Utilisateur
     * @param string $permissionCode Code de la permission
     * 
     * @return bool
     */
    public function hasPermission(LocalUser $user, string $permissionCode): bool
    {
        // Admin global a toutes les permissions
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Vérifier la permission explicite
        return $user->hasPermission($permissionCode);
    }

    /**
     * Obtenir toutes les permissions d'un utilisateur.
     * 
     * @param LocalUser $user
     * 
     * @return Permission[]
     */
    public function getUserPermissions(LocalUser $user): array
    {
        // Admin global a toutes les permissions
        if ($user->isGlobalAdmin()) {
            return $this->permissionRepository->findAll();
        }

        $permissions = [];

        // Permissions explicites
        foreach ($user->getPermissions() as $permission) {
            $permissions[$permission->getCode()] = $permission;
        }

        // Permissions du rôle
        if ($user->getRole()) {
            foreach ($user->getRole()->getPermissions() as $permission) {
                $permissions[$permission->getCode()] = $permission;
            }

            // Permissions des rôles hérités
            foreach ($user->getRole()->getInheritedRoles() as $inheritedRole) {
                foreach ($inheritedRole->getPermissions() as $permission) {
                    $permissions[$permission->getCode()] = $permission;
                }
            }
        }

        return array_values($permissions);
    }

    /**
     * Créer un rôle.
     * 
     * @param string $name Nom du rôle
     * @param string $scope Portée du rôle
     * @param string|null $description Description
     * 
     * @return Role
     */
    public function createRole(string $name, string $scope, ?string $description = null): Role
    {
        $role = new Role();
        $role->setName($name);
        $role->setScope($scope);
        $role->setDescription($description);

        $this->em->persist($role);
        $this->em->flush();

        return $role;
    }

    /**
     * Assigner une permission à un rôle.
     * 
     * @param Role $role
     * @param Permission $permission
     */
    public function addPermissionToRole(Role $role, Permission $permission): void
    {
        if (!$role->getPermissions()->contains($permission)) {
            $role->addPermission($permission);
            $this->em->flush();
        }
    }

    /**
     * Retirer une permission d'un rôle.
     * 
     * @param Role $role
     * @param Permission $permission
     */
    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        if ($role->getPermissions()->contains($permission)) {
            $role->removePermission($permission);
            $this->em->flush();
        }
    }

    /**
     * Créer une permission.
     * 
     * @param string $code Code unique de la permission
     * @param string $scope Portée (global|company|environment|resource)
     * @param string $category Catégorie
     * @param string|null $description Description
     * 
     * @return Permission
     */
    public function createPermission(
        string $code,
        string $scope,
        string $category,
        ?string $description = null
    ): Permission {
        $permission = new Permission();
        $permission->setCode($code);
        $permission->setScope($scope);
        $permission->setCategory($category);
        $permission->setDescription($description);

        $this->em->persist($permission);
        $this->em->flush();

        return $permission;
    }

    /**
     * Assigner un rôle à un utilisateur.
     * 
     * @param LocalUser $user
     * @param Role $role
     */
    public function assignRoleToUser(LocalUser $user, Role $role): void
    {
        $user->setRole($role);
        $this->em->flush();
    }

    /**
     * Assigner une permission supplémentaire à un utilisateur.
     * 
     * @param LocalUser $user
     * @param Permission $permission
     */
    public function addPermissionToUser(LocalUser $user, Permission $permission): void
    {
        if (!$user->getPermissions()->contains($permission)) {
            $user->addPermission($permission);
            $this->em->flush();
        }
    }

    /**
     * Retirer une permission d'un utilisateur.
     * 
     * @param LocalUser $user
     * @param Permission $permission
     */
    public function removePermissionFromUser(LocalUser $user, Permission $permission): void
    {
        $user->removePermission($permission);
        $this->em->flush();
    }

    /**
     * Obtenir tous les rôles d'une portée.
     * 
     * @param string $scope
     * 
     * @return Role[]
     */
    public function getRolesByScope(string $scope): array
    {
        return $this->roleRepository->findByScope($scope);
    }

    /**
     * Obtenir toutes les permissions d'une portée.
     * 
     * @param string $scope
     * 
     * @return Permission[]
     */
    public function getPermissionsByScope(string $scope): array
    {
        return $this->permissionRepository->findByScope($scope);
    }

    /**
     * Obtenir toutes les permissions d'une catégorie.
     * 
     * @param string $category
     * 
     * @return Permission[]
     */
    public function getPermissionsByCategory(string $category): array
    {
        return $this->permissionRepository->findByCategory($category);
    }
}
