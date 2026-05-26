<?php

namespace App\Test;

use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Cache;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class NullEntityManager implements EntityManagerInterface
{
    public function getRepository(string $className): EntityRepository
    {
        throw new \BadMethodCallException('NullEntityManager does not support getRepository.');
    }

    public function getCache(): Cache|null
    {
        return null;
    }

    public function getConnection(): Connection
    {
        throw new \BadMethodCallException('NullEntityManager does not support getConnection.');
    }

    public function getMetadataFactory(): ClassMetadataFactory
    {
        throw new \BadMethodCallException('NullEntityManager does not support getMetadataFactory.');
    }

    public function getExpressionBuilder(): Expr
    {
        throw new \BadMethodCallException('NullEntityManager does not support getExpressionBuilder.');
    }

    public function beginTransaction(): void
    {
    }

    public function wrapInTransaction(callable $func): mixed
    {
        return $func($this);
    }

    public function commit(): void
    {
    }

    public function rollback(): void
    {
    }

    public function createQuery(string $dql = ''): Query
    {
        throw new \BadMethodCallException('NullEntityManager does not support createQuery.');
    }

    public function createNativeQuery(string $sql, ResultSetMapping $rsm): NativeQuery
    {
        throw new \BadMethodCallException('NullEntityManager does not support createNativeQuery.');
    }

    public function createQueryBuilder(): QueryBuilder
    {
        throw new \BadMethodCallException('NullEntityManager does not support createQueryBuilder.');
    }

    public function find(string $className, mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null): object|null
    {
        return null;
    }

    public function refresh(object $object, LockMode|int|null $lockMode = null): void
    {
    }

    public function flush(): void
    {
    }

    public function getReference(string $entityName, mixed $id): object|null
    {
        return null;
    }

    public function close(): void
    {
    }

    public function lock(object $entity, LockMode|int $lockMode, DateTimeInterface|int|null $lockVersion = null): void
    {
    }

    public function getEventManager(): EventManager
    {
        throw new \BadMethodCallException('NullEntityManager does not support getEventManager.');
    }

    public function getConfiguration(): Configuration
    {
        throw new \BadMethodCallException('NullEntityManager does not support getConfiguration.');
    }

    public function isOpen(): bool
    {
        return true;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        throw new \BadMethodCallException('NullEntityManager does not support getUnitOfWork.');
    }

    public function newHydrator(string|int $hydrationMode): AbstractHydrator
    {
        throw new \BadMethodCallException('NullEntityManager does not support newHydrator.');
    }

    public function getProxyFactory(): ProxyFactory
    {
        throw new \BadMethodCallException('NullEntityManager does not support getProxyFactory.');
    }

    public function getFilters(): FilterCollection
    {
        throw new \BadMethodCallException('NullEntityManager does not support getFilters.');
    }

    public function isFiltersStateClean(): bool
    {
        return true;
    }

    public function hasFilters(): bool
    {
        return false;
    }

    public function persist(object $object): void
    {
    }

    public function remove(object $object): void
    {
    }

    public function clear(): void
    {
    }

    public function detach(object $object): void
    {
    }

    public function initializeObject(object $obj): void
    {
    }

    public function isUninitializedObject(mixed $value): bool
    {
        return false;
    }

    public function contains(object $object): bool
    {
        return false;
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        throw new \BadMethodCallException('NullEntityManager does not support getClassMetadata.');
    }
}
