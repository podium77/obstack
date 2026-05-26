<?php
namespace App\Repository;

use App\Entity\Company;
use App\Entity\RcaAnalysis;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RcaAnalysisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RcaAnalysis::class);
    }

    public function save(RcaAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RcaAnalysis $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.alert', 'a')
            ->join('a.application', 'app')
            ->join('app.environment', 'e')
            ->where('e.company = :company')
            ->setParameter('company', $company)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
