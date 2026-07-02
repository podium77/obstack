<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\LocalUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findByUser(LocalUser $user, int $limit = 50): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC'], $limit);
    }

    public function findByAction(string $action, int $limit = 50): array
    {
        return $this->findBy(['action' => $action], ['createdAt' => 'DESC'], $limit);
    }

    public function findByResourceType(string $resourceType, int $limit = 50): array
    {
        return $this->findBy(['resourceType' => $resourceType], ['createdAt' => 'DESC'], $limit);
    }

    public function findByResourceId(int $resourceId, int $limit = 50): array
    {
        return $this->findBy(['resourceId' => $resourceId], ['createdAt' => 'DESC'], $limit);
    }

    public function findByStatus(string $status, int $limit = 50): array
    {
        return $this->findBy(['status' => $status], ['createdAt' => 'DESC'], $limit);
    }

    public function findFailures(int $limit = 50): array
    {
        return $this->findBy(['status' => AuditLog::STATUS_FAILURE], ['createdAt' => 'DESC'], $limit);
    }

    public function findRecentActivity(\DateTimeImmutable $since, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAccessDeniedAttempts(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.action = :action')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('action', AuditLog::ACTION_ACCESS_DENIED)
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
