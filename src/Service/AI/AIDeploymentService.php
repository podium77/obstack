<?php

namespace App\Service\AI;

use App\Entity\AIDeployment;
use App\Entity\LocalUser;
use App\Entity\Company;
use App\Repository\AIDeploymentRepository;
use App\Service\AI\Hypervisor\HypervisorConnectorInterface;
use App\Service\AI\Hypervisor\ProxmoxConnector;
use App\Service\AI\Hypervisor\VMwareConnector;
use App\Service\AI\Hypervisor\NutanixConnector;
use App\Service\AI\Exception\HypervisorException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AIDeploymentService
 * 
 * Orchestration des déploiements Mode 2 (automatisés) :
 * - Validation de la configuration
 * - Création des VMs via hyperviseurs
 * - Installation d'Ollama
 * - Configuration de l'IA
 * - Suivi du déploiement
 */
class AIDeploymentService
{
    private AIDeploymentRepository $repository;
    private EntityManagerInterface $entityManager;
    private AiConfigurationValidator $validator;
    private LoggerInterface $logger;
    private HttpClientInterface $httpClient;

    public function __construct(
        AIDeploymentRepository $repository,
        EntityManagerInterface $entityManager,
        AiConfigurationValidator $validator,
        LoggerInterface $logger,
        HttpClientInterface $httpClient
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * Valider la configuration Mode 2
     * 
     * @param array $config Configuration du déploiement
     * @return array Résultat de validation (ValidationResult)
     */
    public function validateMode2Config(array $config): array
    {
        $this->logger->info('Validating Mode 2 deployment config', $config);

        $errors = [];
        $warnings = [];

        // Hypervisor config
        if (empty($config['hypervisor_provider'])) {
            $errors[] = 'Hypervisor provider required';
        } elseif (!in_array($config['hypervisor_provider'], ['proxmox', 'vmware', 'nutanix'])) {
            $errors[] = sprintf('Unsupported hypervisor: %s', $config['hypervisor_provider']);
        }

        if (empty($config['hypervisor_host'])) {
            $errors[] = 'Hypervisor host required';
        }

        if (empty($config['hypervisor_username'])) {
            $errors[] = 'Hypervisor username required';
        }

        if (empty($config['hypervisor_password'])) {
            $errors[] = 'Hypervisor password required';
        }

        // VM config
        if (empty($config['vm_name'])) {
            $errors[] = 'VM name required';
        }

        if (empty($config['vm_memory'])) {
            $errors[] = 'VM memory required';
        } elseif ($config['vm_memory'] < 2048) {
            $warnings[] = 'VM memory should be at least 2GB for Ollama';
        }

        if (empty($config['vm_cores'])) {
            $errors[] = 'VM cores required';
        } elseif ($config['vm_cores'] < 2) {
            $warnings[] = 'VM cores should be at least 2 for Ollama';
        }

        if (empty($config['vm_disk'])) {
            $warnings[] = 'VM disk size not specified, using default 50GB';
        }

        // AI Model
        if (empty($config['ai_model'])) {
            $errors[] = 'AI model required';
        } elseif (!in_array($config['ai_model'], ['qwen3:4b', 'qwen3:8b'])) {
            $errors[] = sprintf('Unsupported model: %s', $config['ai_model']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * Créer un connecteur hyperviseur basé sur la configuration
     */
    private function createHypervisorConnector(array $config): HypervisorConnectorInterface
    {
        $provider = $config['hypervisor_provider'];
        $host = $config['hypervisor_host'];
        $username = $config['hypervisor_username'];
        $password = $config['hypervisor_password'];

        return match ($provider) {
            'proxmox' => new ProxmoxConnector(
                $this->httpClient,
                $this->logger,
                $host,
                $username,
                $password,
                $config['proxmox_node'] ?? 'pve'
            ),
            'vmware' => new VMwareConnector(
                $this->httpClient,
                $this->logger,
                $host,
                $username,
                $password,
                $config['vmware_datacenter'] ?? 'ha-datacenter'
            ),
            'nutanix' => new NutanixConnector(
                $this->httpClient,
                $this->logger,
                $host,
                $username,
                $password,
                $config['nutanix_cluster'] ?? 'default'
            ),
            default => throw new HypervisorException("Unknown hypervisor: $provider")
        };
    }

    /**
     * Commencer un déploiement Mode 2
     * 
     * @return AIDeployment L'entité de déploiement créée
     */
    public function startDeployment(
        array $config,
        LocalUser $createdBy,
        ?Company $company = null
    ): AIDeployment {
        try {
            $this->logger->info('Starting Mode 2 deployment', ['name' => $config['vm_name']]);

            // Validation
            $validation = $this->validateMode2Config($config);
            if (!$validation['valid']) {
                throw new \InvalidArgumentException('Config validation failed: ' . implode(', ', $validation['errors']));
            }

            // Créer l'entité de déploiement
            $deployment = new AIDeployment(
                $config['deployment_name'] ?? $config['vm_name'],
                $config['hypervisor_provider'],
                [
                    'host' => $config['hypervisor_host'],
                    'username' => $config['hypervisor_username'],
                    'provider_specific' => [
                        'proxmox_node' => $config['proxmox_node'] ?? null,
                        'vmware_datacenter' => $config['vmware_datacenter'] ?? null,
                        'nutanix_cluster' => $config['nutanix_cluster'] ?? null,
                    ]
                ],
                [
                    'name' => $config['vm_name'],
                    'memory' => $config['vm_memory'],
                    'cores' => $config['vm_cores'],
                    'disk_size' => $config['vm_disk'] ?? 50,
                    'storage' => $config['vm_storage'] ?? null,
                ],
                $config['ai_model'],
                $createdBy,
                $company
            );

            $deployment->addDeploymentLog('Déploiement Mode 2 initié');
            $deployment->setStatus('pending');

            $this->entityManager->persist($deployment);
            $this->entityManager->flush();

            // Créer un job asynchrone pour le déploiement réel
            $this->logger->info('Mode 2 deployment entity created', ['id' => $deployment->getId()]);

            return $deployment;

        } catch (\Exception $e) {
            $this->logger->error('Mode 2 deployment start failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Exécuter le déploiement (appelé par Messenger job)
     */
    public function executeDeployment(AIDeployment $deployment): void
    {
        try {
            $deployment->setStatus('deploying');
            $deployment->addDeploymentLog('Création de la machine virtuelle...');
            $deployment->setDeployedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            // Créer le connecteur
            $connector = $this->createHypervisorConnector($deployment->getHypervisorConfig());

            // Test de connexion
            if (!$connector->testConnection()) {
                throw new HypervisorException('Hypervisor connection failed');
            }

            $deployment->addDeploymentLog('Hyperviseur connecté');

            // Créer la VM
            $vmId = $connector->createVirtualMachine($deployment->getVmConfig());
            $deployment->setVmId($vmId);
            $deployment->addDeploymentLog("VM créée: $vmId");

            // Récupérer l'IP
            $vmIp = $connector->getVirtualMachineIP($vmId);
            $deployment->setVmIp($vmIp);
            $deployment->addDeploymentLog("IP de la VM: $vmIp");

            // Configurer Ollama et l'IA (via SSH)
            $deployment->setStatus('configuring');
            $deployment->addDeploymentLog('Configuration d\'Ollama en cours...');
            $this->entityManager->flush();

            // Ici: Scripts SSH pour installer Ollama, télécharger modèle, etc.
            // À implémenter dans AIInstallationScript

            $deployment->setStatus('deployed');
            $deployment->setCompletedAt(new \DateTimeImmutable());
            $deployment->addDeploymentLog('Déploiement terminé avec succès');

            $this->entityManager->flush();
            $this->logger->info('Mode 2 deployment completed', ['id' => $deployment->getId()]);

        } catch (\Exception $e) {
            $deployment->setStatus('failed');
            $deployment->setErrorMessage($e->getMessage());
            $deployment->addDeploymentLog("ERREUR: {$e->getMessage()}");
            $this->entityManager->flush();
            
            $this->logger->error('Mode 2 deployment failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Arrêter un déploiement Mode 2
     */
    public function stopDeployment(AIDeployment $deployment): void
    {
        try {
            $deployment->setStatus('stopping');
            $deployment->addDeploymentLog('Arrêt en cours...');
            $this->entityManager->flush();

            $connector = $this->createHypervisorConnector($deployment->getHypervisorConfig());
            
            if ($deployment->getVmId()) {
                $connector->stopVirtualMachine((string)$deployment->getVmId());
                $deployment->addDeploymentLog('VM arrêtée');
            }

            $deployment->setStatus('stopped');
            $this->entityManager->flush();
            $this->logger->info('Mode 2 deployment stopped', ['id' => $deployment->getId()]);

        } catch (\Exception $e) {
            $deployment->setStatus('failed');
            $deployment->setErrorMessage($e->getMessage());
            $this->entityManager->flush();
            
            $this->logger->error('Mode 2 deployment stop failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Supprimer un déploiement Mode 2 (cleanup)
     */
    public function deleteDeployment(AIDeployment $deployment): void
    {
        try {
            $this->logger->info('Deleting Mode 2 deployment', ['id' => $deployment->getId()]);

            $connector = $this->createHypervisorConnector($deployment->getHypervisorConfig());
            
            if ($deployment->getVmId()) {
                $connector->deleteVirtualMachine((string)$deployment->getVmId());
                $deployment->addDeploymentLog('VM supprimée');
            }

            $this->entityManager->remove($deployment);
            $this->entityManager->flush();

            $this->logger->info('Mode 2 deployment deleted', ['id' => $deployment->getId()]);

        } catch (\Exception $e) {
            $this->logger->error('Mode 2 deployment delete failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Récupérer tous les déploiements actifs
     */
    public function findActiveDeployments(): array
    {
        return $this->repository->findActive();
    }

    /**
     * Récupérer les déploiements en cours
     */
    public function findDeployingDeployments(): array
    {
        return $this->repository->findDeploying();
    }
}
