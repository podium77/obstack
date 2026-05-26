<?php
namespace App\Repository;

use App\Entity\Environment;
use App\Entity\KubernetesNode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class KubernetesNodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KubernetesNode::class);
    }

    public function findByEnvironmentWithPods(Environment $env): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.pods', 'p')
            ->addSelect('p')
            ->where('n.environment = :env')
            ->setParameter('env', $env)
            ->orderBy('n.role', 'ASC')
            ->addOrderBy('n.nodeName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMasterNodes(Environment $env): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.environment = :env')
            ->andWhere('n.role = :role')
            ->setParameter('env', $env)
            ->setParameter('role', \App\Enum\NodeRole::MASTER)
            ->getQuery()
            ->getResult();
    }
}
