<?php
namespace App\Repository;

use App\Entity\AgentToken;
use App\Entity\Application;
use App\Entity\Company;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    public function findAllActiveByEnvironment(Environment $env): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.agentToken', 'at')
            ->addSelect('at')
            ->where('a.environment = :env')
            ->andWhere('a.active = true')
            ->setParameter('env', $env)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.environment', 'e')
            ->join('e.company', 'c')
            ->where('a.active = true')
            ->andWhere('e.active = true')
            ->andWhere('c.active = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllActiveByCompany(Company $company): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.environment', 'e')
            ->join('e.company', 'c')
            ->where('a.active = true')
            ->andWhere('e.active = true')
            ->andWhere('c = :company')
            ->setParameter('company', $company)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByTokenAndEnv(AgentToken $token, Environment $env): ?Application
    {
        return $this->createQueryBuilder('a')
            ->where('a.environment = :env')
            ->andWhere('a.hostAddress = :host')
            ->setParameter('env', $env)
            ->setParameter('host', $token->getDetectedHostname() ?? '')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Recherche les applications K8s nodes dans un environnement */
    public function findKubernetesNodes(Environment $env): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.environment = :env')
            ->andWhere('a.isKubernetesNode = true')
            ->andWhere('a.active = true')
            ->setParameter('env', $env)
            ->getQuery()
            ->getResult();
    }

    public function save(Application $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Application $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
