<?php

namespace App\Service\AI\Hypervisor;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\AI\Exception\HypervisorException;

/**
 * Connecteur Nutanix Prism
 * 
 * Gère la communication avec l'API Nutanix Prism pour :
 * - Créer/supprimer des VMs
 * - Gérer les images de système
 * - Récupérer les ressources disponibles
 */
class NutanixConnector implements HypervisorConnectorInterface
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $host;
    private string $username;
    private string $password;
    private string $cluster;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $host,
        string $username,
        string $password,
        string $cluster
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cluster = $cluster;
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->httpClient->request('GET',
                "{$this->host}/api/nutanix/v3/clusters",
                ['auth_basic' => [$this->username, $this->password], 'verify_peer' => false]
            );

            $success = $response->getStatusCode() === 200;
            
            if ($success) {
                $this->logger->info('Nutanix connection successful', [
                    'host' => $this->host,
                    'cluster' => $this->cluster
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Nutanix connection failed', [
                'host' => $this->host,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createVirtualMachine(array $config): string
    {
        try {
            $vmName = $config['name'];
            
            $payload = [
                'spec' => [
                    'name' => $vmName,
                    'resources' => [
                        'memory_size_mib' => ($config['memory'] ?? 4096) / 1024,
                        'num_vcpus' => $config['cores'] ?? 2,
                        'num_sockets' => $config['sockets'] ?? 1,
                    ],
                    'cluster_reference' => [
                        'kind' => 'cluster',
                        'uuid' => $this->cluster,
                    ]
                ]
            ];

            // Ajouter le disque s'il est configuré
            if (!empty($config['storage'])) {
                $payload['spec']['resources']['disk_list'] = [
                    [
                        'disk_size_mib' => ($config['disk_size'] ?? 50) * 1024,
                        'storage_config' => [
                            'storage_container_reference' => [
                                'kind' => 'storage_container',
                                'uuid' => $config['storage'],
                            ]
                        ]
                    ]
                ];
            }

            $response = $this->httpClient->request('POST',
                "{$this->host}/api/nutanix/v3/vms",
                [
                    'auth_basic' => [$this->username, $this->password],
                    'json' => $payload,
                    'verify_peer' => false
                ]
            );

            if ($response->getStatusCode() !== 201) {
                throw new HypervisorException('Nutanix VM creation failed');
            }

            $data = json_decode($response->getContent(), true);
            $vmId = $data['metadata']['uuid'] ?? $vmName;

            $this->logger->info('Nutanix VM created', ['vm_id' => $vmId]);
            return $vmId;

        } catch (\Exception $e) {
            $this->logger->error('Nutanix VM creation error', ['error' => $e->getMessage()]);
            throw new HypervisorException('Cannot create Nutanix VM: ' . $e->getMessage());
        }
    }

    public function getVirtualMachineIP(string $vmId): ?string
    {
        try {
            // Attendre que la VM ait une IP (max 30 tentatives)
            for ($i = 0; $i < 30; $i++) {
                sleep(1);

                $response = $this->httpClient->request('GET',
                    "{$this->host}/api/nutanix/v3/vms/{$vmId}",
                    ['auth_basic' => [$this->username, $this->password], 'verify_peer' => false]
                );

                $data = json_decode($response->getContent(), true);

                if (!empty($data['status']['resources']['nic_list'])) {
                    foreach ($data['status']['resources']['nic_list'] as $nic) {
                        if (!empty($nic['ip_endpoint_list'])) {
                            return $nic['ip_endpoint_list'][0]['ip'];
                        }
                    }
                }
            }

            throw new HypervisorException('Timeout waiting for VM IP');

        } catch (\Exception $e) {
            $this->logger->error('Cannot get Nutanix VM IP', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function executeCommand(string $vmId, string $command): string
    {
        // Nutanix: Nécessite SSH ou Nutanix Guest Tools
        throw new HypervisorException('Command execution via Nutanix requires SSH or Guest Tools setup');
    }

    public function stopVirtualMachine(string $vmId): bool
    {
        try {
            $payload = ['transition' => 'OFF'];

            $response = $this->httpClient->request('POST',
                "{$this->host}/api/nutanix/v3/vms/{$vmId}/set_power_state",
                [
                    'auth_basic' => [$this->username, $this->password],
                    'json' => $payload,
                    'verify_peer' => false
                ]
            );

            $success = in_array($response->getStatusCode(), [200, 202]);
            
            if ($success) {
                $this->logger->info('Nutanix VM stopped', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot stop Nutanix VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteVirtualMachine(string $vmId): bool
    {
        try {
            // First get the current spec_version
            $getResponse = $this->httpClient->request('GET',
                "{$this->host}/api/nutanix/v3/vms/{$vmId}",
                ['auth_basic' => [$this->username, $this->password], 'verify_peer' => false]
            );

            $data = json_decode($getResponse->getContent(), true);
            $specVersion = $data['metadata']['spec_version'] ?? 0;

            // Delete with spec_version
            $response = $this->httpClient->request('DELETE',
                "{$this->host}/api/nutanix/v3/vms/{$vmId}?_spec_version={$specVersion}",
                ['auth_basic' => [$this->username, $this->password], 'verify_peer' => false]
            );

            $success = in_array($response->getStatusCode(), [200, 202, 204]);
            
            if ($success) {
                $this->logger->info('Nutanix VM deleted', ['vm_id' => $vmId]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Cannot delete Nutanix VM', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAvailableResources(): array
    {
        try {
            $response = $this->httpClient->request('GET',
                "{$this->host}/api/nutanix/v3/clusters/{$this->cluster}",
                ['auth_basic' => [$this->username, $this->password], 'verify_peer' => false]
            );

            $data = json_decode($response->getContent(), true);
            $resources = $data['status']['resources'] ?? [];

            return [
                'cpu' => [
                    'cores' => $resources['cpu_capacity_in_ghz'] ?? 0,
                    'usage' => 0,
                ],
                'memory' => [
                    'total' => $resources['memory_capacity_in_bytes'] ?? 0,
                    'used' => $resources['memory_usage_in_bytes'] ?? 0,
                    'available' => ($resources['memory_capacity_in_bytes'] ?? 0) - ($resources['memory_usage_in_bytes'] ?? 0),
                ],
                'disk' => [
                    'total' => $resources['storage_capacity_in_bytes'] ?? 0,
                    'used' => $resources['usage_stats']['storage.usage_bytes'] ?? 0,
                    'available' => ($resources['storage_capacity_in_bytes'] ?? 0) - ($resources['usage_stats']['storage.usage_bytes'] ?? 0),
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Cannot get Nutanix resources', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getConnectionStatus(): array
    {
        return [
            'provider' => 'nutanix',
            'host' => $this->host,
            'cluster' => $this->cluster,
            'api_version' => 'v3',
        ];
    }
}
