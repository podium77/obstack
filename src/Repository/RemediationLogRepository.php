<?php
namespace App\Repository;

use App\Entity\Application;
use App\Entity\RemediationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RemediationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemediationLog::class);
    }

    public function findRecentForApp(Application $app, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.application = :app')
            ->setParameter('app', $app)
            ->orderBy('r.executedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.application', 'a')
            ->orderBy('r.executedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(RemediationLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
