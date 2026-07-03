<?php

namespace App\Service\AI\Hypervisor;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\AI\Exception\HypervisorException;

/**
 * Connecteur Proxmox
 * 
 * Gère la communication avec l'API Proxmox pour :
 * - Créer/supprimer des VMs
 * - Exécuter des commandes SSH
 * - Récupérer les ressources disponibles
 */
class ProxmoxConnector implements HypervisorConnectorInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $host;
    private string $username;
    private string $password;
    private string $nodeId;
    private ?string $ticket = null;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $host,
        string $username,
        string $password,
        string $nodeId
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
        $this->nodeId = $nodeId;
    }

    public function testConnection(): bool
    {
        try {
            $this->authenticate();
            $this->logger->info('Proxmox connection successful', [
                'host' => $this->host,
                'node' => $this->nodeId
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Proxmox connection failed', [
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

            $vmId = $config['vm_id'] ?? $this->generateVmId();
            
            $payload = [
                'vmid' => $vmId,
                'name' => $config['name'],
                'memory' => $config['memory'] ?? 4096,
                'cores' => $config['cores'] ?? 2,
                'sockets' => $config['sockets'] ?? 1,
            ];

            if (!empty($config['storage'])) {
                $payload['scsihw'] = 'virtio-scsi-pci';
                $payload['scsi0'] = sprintf('%s:%s,size=%dG', 
                    $config['storage'],
                    $vmId,
                    $config['disk_size'] ?? 50
                );
            }

            $response = $this->httpClient->request('POST', 
                "{$this->host}/api2/json/nodes/{$this->nodeId}/qemu",
                [
                    'headers' => $this->getHeaders(),
                    'json' => $payload,
                    'verify_peer' => false
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new HypervisorException('Proxmox VM creation failed: ' . $response->getContent(false));
            }

            $this->logger->info('Proxmox VM created', ['vm_id' => $vmId]);
            return $vmId;

        } catch (\Exception $e) {
            $this->logger->error('Proxmox VM creation error', ['error' => $e->getMessage()]);
            throw new HypervisorException('Cannot create Proxmox VM: ' . $e->getMessage());
        }
    }

    public function getVirtualMachineIP(string $vmId): ?string
    {
        try {
            $this->authenticate();
            
            // Attendre que la VM ait une IP
            for ($i = 0; $i < 30; $i++) {
                sleep(1);
                
                $response = $this->httpClient->request('GET',
                    "{$this->host}/api2/json/nodes/{$this->nodeId}/qemu/{$vmId}/status/current",
                    ['headers' => $this->getHeaders(), 'verify_peer' => false]
                );

                $data = json_decode($response->getContent(), true);
                
                if (!empty($data['data']['network']['ip'])) {
                    return $data['data']['network']['ip'];
                }
            }

            throw new HypervisorException('Timeout waiting for VM IP');

        } catch (\Exception $e) {
            $this->logger->error('Cannot get Proxmox VM IP', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function executeCommand(string $vmId, string $command): string
    {
        // Mode 2 Note: Nécessite SSH configuré sur la VM
        // Pour Proxmox, utiliser lxc exec ou SSH
        throw new HypervisorException('Command execution via Proxmox requires SSH setup');
    }

    public function stopVirtualMachine(string $vmId): bool
    {
        try {
            $this->authenticate();

            $response = $this->httpClient->request('POST',
                "{$this->host}/api2/json/nodes/{$this->nodeId}/qemu/{$vmId}/status/shutdown",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $success = in_array($response->getStatusCode(), [200, 202]);
            
            if ($success) {
                $this->logger->info('Proxmox VM stopped', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot stop Proxmox VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteVirtualMachine(string $vmId): bool
    {
        try {
            $this->authenticate();

            $response = $this->httpClient->request('DELETE',
                "{$this->host}/api2/json/nodes/{$this->nodeId}/qemu/{$vmId}",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $success = in_array($response->getStatusCode(), [200, 202]);
            
            if ($success) {
                $this->logger->info('Proxmox VM deleted', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot delete Proxmox VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAvailableResources(): array
    {
        try {
            $this->authenticate();

            $response = $this->httpClient->request('GET',
                "{$this->host}/api2/json/nodes/{$this->nodeId}/status",
                ['headers' => $this->getHeaders(), 'verify_peer' => false]
            );

            $data = json_decode($response->getContent(), true);

            return [
                'cpu' => [
                    'cores' => $data['data']['cpuinfo']['cores'] ?? 0,
                    'usage' => $data['data']['cpu'] ?? 0,
                ],
                'memory' => [
                    'total' => $data['data']['memory']['total'] ?? 0,
                    'used' => $data['data']['memory']['used'] ?? 0,
                    'available' => ($data['data']['memory']['total'] ?? 0) - ($data['data']['memory']['used'] ?? 0),
                ],
                'disk' => [
                    'total' => $data['data']['disk']['total'] ?? 0,
                    'used' => $data['data']['disk']['used'] ?? 0,
                    'available' => ($data['data']['disk']['total'] ?? 0) - ($data['data']['disk']['used'] ?? 0),
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Cannot get Proxmox resources', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getConnectionStatus(): array
    {
        return [
            'provider' => 'proxmox',
            'host' => $this->host,
            'node' => $this->nodeId,
            'authenticated' => $this->ticket !== null,
        ];
    }

    // Private Methods

    private function authenticate(): void
    {
        if ($this->ticket) {
            return; // Already authenticated
        }

        try {
            $response = $this->httpClient->request('POST',
                "{$this->host}/api2/json/access/ticket",
                [
                    'body' => http_build_query([
                        'username' => $this->username,
                        'password' => $this->password,
                    ]),
                    'verify_peer' => false,
                ]
            );

            $data = json_decode($response->getContent(), true);
            $this->ticket = $data['data']['ticket'] ?? null;

            if (!$this->ticket) {
                throw new HypervisorException('Failed to get Proxmox ticket');
            }

        } catch (\Exception $e) {
            throw new HypervisorException('Proxmox authentication failed: ' . $e->getMessage());
        }
    }

    private function getHeaders(): array
    {
        return [
            'Cookie' => "PVEAuthCookie={$this->ticket}",
            'Content-Type' => 'application/json',
        ];
    }

    private function generateVmId(): string
    {
        // Proxmox VMID is numeric
        return (string)rand(100, 999999);
    }
}
