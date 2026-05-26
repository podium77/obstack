<?php
namespace App\Repository;

use App\Entity\AgentToken;
use App\Entity\Company;
use App\Entity\Environment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgentTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentToken::class);
    }

    public function findByToken(string $token): ?AgentToken
    {
        return $this->createQueryBuilder('t')
            ->join('t.environment', 'e')
            ->join('e.company', 'c')
            ->where('t.token = :token')
            ->andWhere('t.isActive = true')
            ->andWhere('c.active = true')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTokenHash(string $hash): ?AgentToken
    {
        return $this->findOneBy(['tokenHash' => $hash, 'isActive' => true]);
    }

    public function findByEnvironment(Environment $environment): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.environment = :env')
            ->setParameter('env', $environment)
            ->getQuery()
            ->getResult();
    }

    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.environment', 'e')
            ->where('e.company = :company')
            ->andWhere('t.isActive = true')
            ->setParameter('company', $company)
            ->getQuery()
            ->getResult();
    }

    public function findOnlineTokens(): array
    {
        $threshold = new \DateTimeImmutable('-3 minutes');
        return $this->createQueryBuilder('t')
            ->where('t.isActive = true')
            ->andWhere('t.lastSeenAt >= :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    public function save(AgentToken $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) $this->getEntityManager()->flush();
    }
}
