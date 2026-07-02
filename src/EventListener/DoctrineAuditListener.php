<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Service\AuditService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Écouteur d'événements Doctrine pour la journalisation d'audit automatique.
 * 
 * Enregistre automatiquement les opérations create/update/delete pour les entités sensibles.
 */
#[AsEventListener(event: 'postPersist', method: 'onPostPersist')]
#[AsEventListener(event: 'postUpdate', method: 'onPostUpdate')]
#[AsEventListener(event: 'postRemove', method: 'onPostRemove')]
class DoctrineAuditListener
{
    // Entités à auditer (configurable)
    private const AUDITABLE_ENTITIES = [
        'App\Entity\Role',
        'App\Entity\Permission',
        'App\Entity\DatabaseConnection',
        'App\Entity\LocalUser',
        'App\Entity\Company',
        'App\Entity\Application',
    ];

    public function __construct(
        private AuditService $auditService,
    ) {
    }

    public function onPostPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $class = $this->getEntityName($entity);
        $id = $this->getId($entity);

        $this->auditService->log(
            AuditLog::ACTION_CREATE,
            $class,
            $id,
            $class . ' créé',
            AuditLog::STATUS_SUCCESS,
            [],
            $this->getEntityData($entity),
        );
    }

    public function onPostUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $class = $this->getEntityName($entity);
        $id = $this->getId($entity);

        // Extraire les changements
        $changeSet = $args->getEntityChangeSet();
        if (empty($changeSet)) {
            return;
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changeSet as $field => [$oldValue, $newValue]) {
            // Ignorer les champs sensibles
            if ($field === 'encryptedPassword' || $field === 'password') {
                continue;
            }

            $oldValues[$field] = $oldValue;
            $newValues[$field] = $newValue;
        }

        if (empty($oldValues)) {
            return;
        }

        $this->auditService->log(
            AuditLog::ACTION_UPDATE,
            $class,
            $id,
            $class . ' modifié',
            AuditLog::STATUS_SUCCESS,
            $oldValues,
            $newValues,
        );
    }

    public function onPostRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $class = $this->getEntityName($entity);
        $id = $this->getId($entity);

        $this->auditService->log(
            AuditLog::ACTION_DELETE,
            $class,
            $id,
            $class . ' supprimé',
            AuditLog::STATUS_SUCCESS,
            $this->getEntityData($entity),
            [],
        );
    }

    private function isAuditable(object $entity): bool
    {
        $class = get_class($entity);
        return in_array($class, self::AUDITABLE_ENTITIES);
    }

    private function getEntityName(object $entity): string
    {
        $class = get_class($entity);
        $parts = explode('\\', $class);
        return end($parts);
    }

    private function getId(object $entity): ?int
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }
        return null;
    }

    private function getEntityData(object $entity): array
    {
        $data = [];

        // Extraire les propriétés publiques de base
        $reflection = new \ReflectionClass($entity);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);

            // Ignorer les champs sensibles
            if (in_array($property->getName(), ['encryptedPassword', 'password'])) {
                continue;
            }

            // Ignorer les objets complexes
            if (is_object($value) && !$value instanceof \DateTimeImmutable && !$value instanceof \DateTime) {
                continue;
            }

            // Converter les dates en string
            if ($value instanceof \DateTimeImmutable || $value instanceof \DateTime) {
                $value = $value->format('c');
            }

            $data[$property->getName()] = $value;
        }

        return $data;
    }
}
