<?php

namespace App\Repository;

use App\Entity\AICluster;
use App\Entity\Application;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * AIClusterRepository
 * 
 * Repository pour les entités AICluster
 */
class AIClusterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AICluster::class);
    }

    /**
     * Trouver le cluster IA pour une application
     */
    public function findByApplication(Application $app): ?AICluster
    {
        return $this->createQueryBuilder('c')
            ->where('c.application = :app')
            ->setParameter('app', $app)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver tous les clusters en cours de déploiement
     */
    public function findDeploying(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'deploying')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les clusters actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les clusters échoués
     */
    public function findFailed(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
