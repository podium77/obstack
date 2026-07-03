<?php

namespace App\Service\AI\Hypervisor;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\AI\Exception\HypervisorException;

/**
 * Connecteur VMware vSphere
 * 
 * Gère la communication avec l'API vSphere pour :
 * - Créer/supprimer des VMs
 * - Gérer les templates de clonage
 * - Récupérer les ressources disponibles
 */
class VMwareConnector implements HypervisorConnectorInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $host;
    private string $username;
    private string $password;
    private string $datacenter;
    private ?string $sessionId = null;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $host,
        string $username,
        string $password,
        string $datacenter
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
        $this->datacenter = $datacenter;
    }

    public function testConnection(): bool
    {
        try {
            $this->authenticate();
            $this->logger->info('VMware connection successful', [
                'host' => $this->host,
                'datacenter' => $this->datacenter
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('VMware connection failed', [
                'host' => $this->host,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createVirtualMachine(array $config): string
    {
        try {
            $this->authenticate();

            // VMware Mode 2: Clone from template
            $templateName = $config['template'] ?? throw new HypervisorException('VMware requires template_name');
            $vmName = $config['name'];

            $payload = [
                'spec' => [
                    'config' => [
                        'name' => $vmName,
                        'memory_mb' => $config['memory'] ?? 4096,
                        'num_cpus' => $config['cores'] ?? 2,
                    ],
                    'placement' => [
                        'datastore' => $config['datastore'] ?? 'datastore1',
                        'folder' => $config['folder'] ?? '/vm',
                    ]
                ]
            ];

            $response = $this->httpClient->request('POST',
                "{$this->host}/api/rest/com/vmware/cis/vm/{$templateName}?action=clone",
                [
                    'headers' => $this->getHeaders(),
                    'json' => $payload,
                    'verify_peer' => false
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new HypervisorException('VMware VM cloning failed');
            }

            $data = json_decode($response->getContent(), true);
            $vmId = $data['value'] ?? $vmName;

            $this->logger->info('VMware VM created', ['vm_id' => $vmId]);
            return $vmId;

        } catch (\Exception $e) {
            $this->logger->error('VMware VM creation error', ['error' => $e->getMessage()]);
            throw new HypervisorException('Cannot create VMware VM: ' . $e->getMessage());
        }
    }

    public function getVirtualMachineIP(string $vmId): ?string
    {
        try {
            $this->authenticate();

            // Attendre que la VM ait une IP (max 30 tentatives)
            for ($i = 0; $i < 30; $i++) {
                sleep(1);

                $response = $this->httpClient->request('GET',
                    "{$this->host}/api/rest/com/vmware/cis/vm/{$vmId}/guest/networking/interfaces",
                    ['headers' => $this->getHeaders(), 'verify_peer' => false]
                );

                $data = json_decode($response->getContent(), true);

                if (!empty($data['value'])) {
                    foreach ($data['value'] as $interface) {
                        if (!empty($interface['ip_addresses'])) {
                            return $interface['ip_addresses'][0];
                        }
                    }
                }
            }

            throw new HypervisorException('Timeout waiting for VM IP');

        } catch (\Exception $e) {
            $this->logger->error('Cannot get VMware VM IP', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function executeCommand(string $vmId, string $command): string
    {
        // VMware: Nécessite guest tools et SSH configuré
        throw new HypervisorException('Command execution via VMware requires SSH setup on guest');
    }

    public function stopVirtualMachine(string $vmId): bool
    {
        try {
            $this->authenticate();

            $response = $this->httpClient->request('POST',
                "{$this->host}/api/rest/com/vmware/cis/vm/{$vmId}/power?action=stop",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $success = in_array($response->getStatusCode(), [200, 202]);
            
            if ($success) {
                $this->logger->info('VMware VM stopped', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot stop VMware VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteVirtualMachine(string $vmId): bool
    {
        try {
            $this->authenticate();

            // First power off VM
            $this->stopVirtualMachine($vmId);
            sleep(3);

            // Then delete it
            $response = $this->httpClient->request('DELETE',
                "{$this->host}/api/rest/com/vmware/cis/vm/{$vmId}",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $success = in_array($response->getStatusCode(), [200, 202]);
            
            if ($success) {
                $this->logger->info('VMware VM deleted', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot delete VMware VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAvailableResources(): array
    {
        try {
            $this->authenticate();

            $response = $this->httpClient->request('GET',
                "{$this->host}/api/rest/com/vmware/cis/inventory/datacenters",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $datacenters = json_decode($response->getContent(), true)['value'] ?? [];

            return [
                'cpu' => ['cores' => 0, 'usage' => 0],
                'memory' => ['total' => 0, 'used' => 0, 'available' => 0],
                'disk' => ['total' => 0, 'used' => 0, 'available' => 0],
                'datacenters' => $datacenters,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Cannot get VMware resources', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getConnectionStatus(): array
    {
        return [
            'provider' => 'vmware',
            'host' => $this->host,
            'datacenter' => $this->datacenter,
            'authenticated' => $this->sessionId !== null,
        ];
    }

    // Private Methods

    private function authenticate(): void
    {
        if ($this->sessionId) {
            return; // Already authenticated
        }

        try {
            $response = $this->httpClient->request('POST',
                "{$this->host}/api/session",
                [
                    'auth_basic' => [$this->username, $this->password],
                    'verify_peer' => false,
                ]
            );

            $this->sessionId = $response->getHeaderLine('vmware-api-session-cookie');

            if (!$this->sessionId) {
                throw new HypervisorException('Failed to get VMware session ID');
            }

        } catch (\Exception $e) {
            throw new HypervisorException('VMware authentication failed: ' . $e->getMessage());
        }
    }

    private function getHeaders(): array
    {
        return [
            'vmware-api-session-id' => $this->sessionId,
            'Content-Type' => 'application/json',
        ];
    }
}
