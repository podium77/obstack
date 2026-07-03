<?php

namespace App\Repository;

use App\Entity\AIDeployment;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * AIDeploymentRepository
 * 
 * Repository pour les entités AIDeployment (déploiements Mode 2 automatisés)
 */
class AIDeploymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AIDeployment::class);
    }

    /**
     * Trouver tous les déploiements actifs (deploying, configuring, ou deployed)
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status IN (:statuses)')
            ->setParameter('statuses', ['deploying', 'configuring', 'deployed'])
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les déploiements en cours (deploying ou configuring)
     */
    public function findDeploying(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status IN (:statuses)')
            ->setParameter('statuses', ['deploying', 'configuring'])
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les déploiements échoués
     */
    public function findFailed(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver tous les déploiements pour une entreprise
     */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.company = :company')
            ->setParameter('company', $company)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les déploiements actifs pour une entreprise
     */
    public function findActiveByCompany(Company $company): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.company = :company')
            ->andWhere('d.status IN (:statuses)')
            ->setParameter('company', $company)
            ->setParameter('statuses', ['deploying', 'configuring', 'deployed'])
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les déploiements par fournisseur hyperviseur
     */
    public function findByHypervisor(string $provider): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.hypervisorProvider = :provider')
            ->setParameter('provider', $provider)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les déploiements par modèle IA
     */
    public function findByModel(string $model): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.aiModel = :model')
            ->setParameter('model', $model)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les déploiements par statut
     */
    public function countByStatus(string $status): int
    {
        return (int)$this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
