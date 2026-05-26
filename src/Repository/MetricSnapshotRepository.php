<?php
namespace App\Repository;

use App\Entity\Application;
use App\Entity\MetricSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MetricSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MetricSnapshot::class);
    }

    public function findLatestForApp(Application $app): ?MetricSnapshot
    {
        return $this->createQueryBuilder('m')
            ->where('m.application = :app')
            ->setParameter('app', $app)
            ->orderBy('m.collectedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findHistoryForApp(Application $app, int $hours = 24): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");
        return $this->createQueryBuilder('m')
            ->where('m.application = :app')
            ->andWhere('m.collectedAt >= :since')
            ->andWhere('m.collectionSuccess = true')
            ->setParameter('app', $app)
            ->setParameter('since', $since)
            ->orderBy('m.collectedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAggregatedStats(Application $app, int $hours = 24): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");
        return $this->createQueryBuilder('m')
            ->select(
                'AVG(m.cpuPercent) as avg_cpu',
                'MAX(m.cpuPercent) as max_cpu',
                'AVG(m.memoryPercent) as avg_mem',
                'MAX(m.memoryPercent) as max_mem',
                'COUNT(m.id) as total'
            )
            ->where('m.application = :app')
            ->andWhere('m.collectedAt >= :since')
            ->setParameter('app', $app)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.collectedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
