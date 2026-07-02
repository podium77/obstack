<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findByName(string $name): ?Role
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findByScope(string $scope): array
    {
        return $this->findBy(['scope' => $scope]);
    }

    public function findAllGlobalRoles(): array
    {
        return $this->findBy(['scope' => 'global']);
    }

    public function findAllCompanyRoles(): array
    {
        return $this->findBy(['scope' => 'company']);
    }
}
