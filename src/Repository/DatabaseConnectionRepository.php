<?php

namespace App\Repository;

use App\Entity\DatabaseConnection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DatabaseConnection>
 */
class DatabaseConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DatabaseConnection::class);
    }

    public function findByName(string $name): ?DatabaseConnection
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }

    public function findTested(): array
    {
        return $this->findBy(['tested' => true, 'active' => true]);
    }

    public function findMysqlConnections(): array
    {
        return $this->findBy(['type' => DatabaseConnection::TYPE_MYSQL]);
    }

    public function findPostgresqlConnections(): array
    {
        return $this->findBy(['type' => DatabaseConnection::TYPE_POSTGRESQL]);
    }

    public function findNeo4jConnections(): array
    {
        return $this->findBy(['type' => DatabaseConnection::TYPE_NEO4J]);
    }

    public function findArangodbConnections(): array
    {
        return $this->findBy(['type' => DatabaseConnection::TYPE_ARANGODB]);
    }
}
