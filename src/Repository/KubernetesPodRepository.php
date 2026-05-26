<?php
namespace App\Repository;
use App\Entity\KubernetesPod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class KubernetesPodRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $r) { parent::__construct($r, KubernetesPod::class); }
    public function findCrashLooping(): array {
        return $this->createQueryBuilder('p')
            ->where('p.restartCount > 5')->andWhere('p.phase != :s')
            ->setParameter('s', 'Succeeded')->getQuery()->getResult();
    }
}
