<?php
namespace App\Repository;

use App\Entity\Environment;
use App\Entity\EnvironmentUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EnvironmentUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, EnvironmentUser::class);
    }

    public function findActiveByEnvironment(Environment $env): array
    {
        return $this->createQueryBuilder('eu')
            ->join('eu.user', 'u')
            ->addSelect('u')
            ->where('eu.environment = :env')
            ->andWhere('eu.active = true')
            ->setParameter('env', $env)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countLocalUsersInEnvironment(Environment $env): int
    {
        return (int) $this->createQueryBuilder('eu')
            ->select('COUNT(eu.id)')
            ->join('eu.user', 'u')
            ->where('eu.environment = :env')
            ->andWhere('u.type = :type')
            ->andWhere('eu.active = true')
            ->setParameter('env', $env)
            ->setParameter('type', \App\Entity\CompanyUser::TYPE_LOCAL)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(EnvironmentUser $e, bool $flush = false): void
    {
        $this->getEntityManager()->persist($e);
        if ($flush) $this->getEntityManager()->flush();
    }
}
