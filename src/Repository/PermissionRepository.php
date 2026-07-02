<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function findByCode(string $code): ?Permission
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findByScope(string $scope): array
    {
        return $this->findBy(['scope' => $scope]);
    }

    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category]);
    }

    public function findGlobalPermissions(): array
    {
        return $this->findBy(['scope' => 'global']);
    }

    public function findCompanyPermissions(): array
    {
        return $this->findBy(['scope' => 'company']);
    }

    public function findEnvironmentPermissions(): array
    {
        return $this->findBy(['scope' => 'environment']);
    }

    public function findResourcePermissions(): array
    {
        return $this->findBy(['scope' => 'resource']);
    }
}
