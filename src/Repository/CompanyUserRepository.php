<?php
namespace App\Repository;
use App\Entity\Company;
use App\Entity\CompanyUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class CompanyUserRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $r) { parent::__construct($r, CompanyUser::class); }
    public function findByCompany(Company $company): array {
        return $this->createQueryBuilder('u')
            ->where('u.company = :c')->andWhere('u.active = true')
            ->setParameter('c', $company)->orderBy('u.username','ASC')
            ->getQuery()->getResult();
    }
    public function findSuperAdmin(Company $company): ?CompanyUser {
        return $this->findOneBy(['company' => $company, 'type' => CompanyUser::TYPE_SUPERADMIN]);
    }
    public function save(CompanyUser $e, bool $flush = false): void {
        $this->getEntityManager()->persist($e);
        if ($flush) $this->getEntityManager()->flush();
    }
}
