<?php
namespace App\Repository;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class CompanyRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $r) { parent::__construct($r, Company::class); }
    public function findOneBySlugPrefix(string $slug): ?Company {
        return $this->createQueryBuilder('c')
            ->where('c.slug LIKE :slug')->setParameter('slug', $slug . '%')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
    public function save(Company $e, bool $flush = false): void {
        $this->getEntityManager()->persist($e);
        if ($flush) $this->getEntityManager()->flush();
    }
}
