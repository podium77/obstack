<?php
namespace App\Agent;

use App\Entity\Application;
use Psr\Log\LoggerInterface;

class SshClient
{
    private array $connections = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $connectTimeoutSeconds = 10,
    ) {}

    public function connect(Application $app): SshConnection
    {
        $key = "{$app->getHostAddress()}:{$app->getSshPort()}:{$app->getSshUser()}";
        if (!isset($this->connections[$key])) {
            $this->connections[$key] = $this->createConnection($app);
        }
        return $this->connections[$key];
    }

    private function createConnection(Application $app): SshConnection
    {
        if (!function_exists('ssh2_connect')) {
            throw new \RuntimeException('Extension PHP ssh2 manquante (apt install php8.3-ssh2)');
        }
        $conn = @ssh2_connect($app->getHostAddress(), $app->getSshPort());
        if (!$conn) {
            throw new \RuntimeException("Connexion SSH impossible vers {$app->getHostAddress()}:{$app->getSshPort()}");
        }
        $keyPath    = $app->getSshKeyPath();
        $pubKeyPath = $keyPath . '.pub';
        if (!@ssh2_auth_pubkey_file($conn, $app->getSshUser(), $pubKeyPath, $keyPath)) {
            throw new \RuntimeException("Authentification SSH échouée pour {$app->getSshUser()}@{$app->getHostAddress()}");
        }
        $this->logger->debug("SSH connecté: {$app->getSshUser()}@{$app->getHostAddress()}");
        return new SshConnection($conn, $app->getSshUser(), $app->getHostAddress(), $this->logger);
    }

    public function disconnect(Application $app): void
    {
        $key = "{$app->getHostAddress()}:{$app->getSshPort()}:{$app->getSshUser()}";
        unset($this->connections[$key]);
    }

    public function disconnectAll(): void { $this->connections = []; }
    public function __destruct() { $this->disconnectAll(); }
}
