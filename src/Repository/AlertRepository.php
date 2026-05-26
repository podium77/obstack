<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function findActiveAlerts(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.resolved = false')
            ->join('a.application', 'app')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findActiveForApp(Application $app): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.application = :app')
            ->andWhere('a.resolved = false')
            ->setParameter('app', $app)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveAlertsBySeverity(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.severity, COUNT(a.id) as cnt')
            ->where('a.resolved = false')
            ->groupBy('a.severity')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['severity']->value] = (int) $row['cnt'];
        }
        return $result;
    }

    public function save(Alert $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
