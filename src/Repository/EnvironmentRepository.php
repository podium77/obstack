<?php
namespace App\Repository;
use App\Entity\Company;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class EnvironmentRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $r) { parent::__construct($r, Environment::class); }
    public function findActiveByCompany(Company $company): array {
        return $this->createQueryBuilder('e')
            ->where('e.company = :c')->andWhere('e.active = true')
            ->setParameter('c', $company)->orderBy('e.name','ASC')
            ->getQuery()->getResult();
    }
    public function save(Environment $e, bool $flush = false): void {
        $this->getEntityManager()->persist($e);
        if ($flush) $this->getEntityManager()->flush();
    }
}
