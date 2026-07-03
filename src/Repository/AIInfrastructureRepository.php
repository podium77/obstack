<?php

namespace App\Repository;

use App\Entity\AIInfrastructure;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AIInfrastructure>
 */
class AIInfrastructureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AIInfrastructure::class);
    }

    /**
     * Trouver l'infrastructure IA active globale
     * (IA partagée pour toutes les entreprises)
     */
    public function findActiveGlobal(): ?AIInfrastructure
    {
        return $this->createQueryBuilder('ai')
            ->andWhere('ai.isActive = true')
            ->andWhere('ai.status = :status')
            ->andWhere('ai.company IS NULL')
            ->setParameter('status', 'active')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver l'infrastructure IA active pour une entreprise spécifique
     */
    public function findActiveForCompany(Company $company): ?AIInfrastructure
    {
        return $this->createQueryBuilder('ai')
            ->andWhere('ai.isActive = true')
            ->andWhere('ai.status = :status')
            ->andWhere('ai.company = :company')
            ->setParameter('status', 'active')
            ->setParameter('company', $company)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver une infrastructure IA active (globale ou pour la compagnie)
     */
    public function findActive(?LocalCompany $company = null): ?AIInfrastructure
    {
        // Préférer l'IA spécifique à l'entreprise
        if ($company) {
            $result = $this->findActiveForCompany($company);
            if ($result) {
                return $result;
            }
        }

        // Fallback à l'IA globale
        return $this->findActiveGlobal();
    }

    /**
     * Lister toutes les infrastructures IA
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('ai')
            ->andWhere('ai.isActive = true')
            ->orderBy('ai.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les infrastructure en attente de déploiement
     */
    public function findDeploying(): array
    {
        return $this->createQueryBuilder('ai')
            ->andWhere('ai.status = :status')
            ->setParameter('status', 'deploying')
            ->orderBy('ai.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
