<?php

namespace App\Service\DatabaseConnector;

use App\Entity\DatabaseConnection;
use App\Service\PasswordEncryptionService;
use Psr\Log\LoggerInterface;

/**
 * Classe abstraite de base pour tous les connecteurs.
 * 
 * Fournit les fonctionnalités communes et force
 * l'implémentation des méthodes spécifiques à chaque moteur.
 */
abstract class AbstractDatabaseConnector implements DatabaseConnectorInterface
{
    protected DatabaseConnection $connection;
    protected LoggerInterface $logger;
    protected PasswordEncryptionService $passwordEncryption;
    protected bool $connected = false;
    protected mixed $dbConnection = null;

    public function __construct(
        DatabaseConnection $connection,
        LoggerInterface $logger,
        PasswordEncryptionService $passwordEncryption
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->passwordEncryption = $passwordEncryption;
    }

    abstract public function connect(): void;

    abstract public function disconnect(): void;

    abstract public function testConnection(): bool;

    abstract public function listStructures(): array;

    abstract public function listData(string $structure, array $options = []): array;

    abstract public function insert(string $structure, array $data): mixed;

    abstract public function update(string $structure, array $criteria, array $data): int;

    abstract public function delete(string $structure, array $criteria): int;

    abstract public function executeQuery(string $query, array $params = []): mixed;

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function getType(): string
    {
        return $this->connection->getType();
    }

    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    protected function getConnectionTimeout(): int
    {
        return $this->connection->getAdvancedOptions()['timeout'] ?? 30;
    }

    /**
     * Récupère le mot de passe déchiffré
     */
    protected function getDecryptedPassword(): string
    {
        $encrypted = $this->connection->getEncryptedPassword();
        if (empty($encrypted)) {
            return '';
        }
        
        try {
            return $this->passwordEncryption->decrypt($encrypted);
        } catch (\Exception $e) {
            $this->logError('Failed to decrypt password', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Password decryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    protected function getPoolSize(): int
    {
        return $this->connection->getAdvancedOptions()['pool_size'] ?? 5;
    }

    protected function isSslEnabled(): bool
    {
        return $this->connection->getAdvancedOptions()['ssl'] ?? false;
    }
}
