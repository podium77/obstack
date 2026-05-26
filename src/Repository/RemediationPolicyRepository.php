<?php
namespace App\Repository;

use App\Entity\Application;
use App\Entity\RemediationPolicy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RemediationPolicyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemediationPolicy::class);
    }

    public function findEnabledForApp(Application $app): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.application = :app')
            ->andWhere('p.enabled = true')
            ->setParameter('app', $app)
            ->orderBy('p.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(RemediationPolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }

    public function remove(RemediationPolicy $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
