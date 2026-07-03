<?php

namespace App\Service\AI;

use App\Entity\AICluster;
use App\Entity\Application;
use App\Entity\AIInfrastructure;
use App\Message\AIClusterDeploymentMessage;
use App\Repository\AIClusterRepository;
use App\Repository\AIInfrastructureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * AIClusterService
 * 
 * Gère la création et configuration des clusters IA pour les applications
 * Supporte Mode 1 (existant) et Mode 2 (déploiement automatisé)
 */
class AIClusterService
{
    public function __construct(
        private AIClusterRepository $clusterRepository,
        private AIInfrastructureRepository $infrastructureRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {}

    /**
     * Créer ou mettre à jour le cluster IA pour une application
     * 
     * @param Application $app
     * @param string $mode 'none'|'existing'|'deployed'
     * @param string|null $aiModel Modèle IA (qwen3:4b ou qwen3:8b)
     * @param int|null $infrastructureId ID infrastructure existante (Mode 1)
     * @param array|null $deploymentConfig Config de déploiement (Mode 2)
     */
    public function configureAICluster(
        Application $app,
        string $mode,
        ?string $aiModel = null,
        ?int $infrastructureId = null,
        ?array $deploymentConfig = null
    ): AICluster {
        try {
            $this->logger->info('Configuring AI cluster for application', [
                'app_id' => $app->getId(),
                'mode' => $mode,
                'model' => $aiModel
            ]);

            // Récupérer ou créer le cluster
            $cluster = $this->clusterRepository->findByApplication($app);
            if (!$cluster) {
                $cluster = new AICluster($app);
            }

            // Valider le mode
            if (!in_array($mode, ['none', 'existing', 'deployed'])) {
                throw new \InvalidArgumentException("Invalid mode: $mode");
            }

            $cluster->setMode($mode);

            if ($mode === 'none') {
                // Pas d'IA
                $cluster->setStatus('active');
                $cluster->setAiModel(null);
                $cluster->setExistingInfrastructure(null);
                $cluster->setDeployment(null);
                $cluster->addLog('IA disabled for this application');

            } elseif ($mode === 'existing') {
                // Mode 1: Infrastructure existante
                if (!$infrastructureId) {
                    throw new \InvalidArgumentException('infrastructure_id required for existing mode');
                }

                if (!$aiModel) {
                    throw new \InvalidArgumentException('ai_model required');
                }

                $infrastructure = $this->infrastructureRepository->find($infrastructureId);
                if (!$infrastructure) {
                    throw new \InvalidArgumentException("Infrastructure not found: $infrastructureId");
                }

                $cluster->setAiModel($aiModel);
                $cluster->setExistingInfrastructure($infrastructure);
                $cluster->setDeployment(null);
                $cluster->setStatus('active');
                $cluster->setActiveAt(new \DateTimeImmutable());
                $cluster->setApiUrl($infrastructure->getApiUrl());
                $cluster->addLog("Using existing infrastructure: {$infrastructure->getName()}");

            } elseif ($mode === 'deployed') {
                // Mode 2: Déploiement nouveau
                if (!$aiModel) {
                    throw new \InvalidArgumentException('ai_model required');
                }

                if (!$deploymentConfig) {
                    throw new \InvalidArgumentException('deployment_config required for deployed mode');
                }

                $cluster->setAiModel($aiModel);
                $cluster->setDeploymentConfig($deploymentConfig);
                $cluster->setStatus('pending');
                $cluster->addLog('Deployment pending');

                // Sauvegarder avant de dispatcher le message
                $this->entityManager->persist($cluster);
                $this->entityManager->flush();

                // Dispatcher le message de déploiement asynchrone
                $this->messageBus->dispatch(new AIClusterDeploymentMessage($cluster->getId()));

                return $cluster;
            }

            $this->entityManager->persist($cluster);
            $this->entityManager->flush();

            return $cluster;

        } catch (\Exception $e) {
            $this->logger->error('AI cluster configuration failed', [
                'error' => $e->getMessage(),
                'app_id' => $app->getId()
            ]);
            throw $e;
        }
    }

    /**
     * Déployer un cluster IA (Mode 2)
     * Cette méthode est appelée de manière asynchrone par Messenger
     */
    public function deployCluster(AICluster $cluster): void
    {
        try {
            $this->logger->info('Deploying AI cluster', ['cluster_id' => $cluster->getId()]);

            $cluster->setStatus('deploying');
            $cluster->addLog('Starting cluster deployment...');
            $this->entityManager->flush();

            // Étape 1: Récupérer les infos de l'application
            $app = $cluster->getApplication();
            $config = $cluster->getDeploymentConfig();

            // Étape 2: Créer une AIDeployment (Mode 2)
            // Note: C'est lié au service AIDeploymentService
            $deployment = $this->createDeploymentFromConfig(
                $app,
                $cluster->getAiModel(),
                $config
            );

            $cluster->setDeployment($deployment);
            $cluster->addLog("Deployment created: {$deployment->getId()}");

            // Étape 3: Attendre que le déploiement se termine
            // (Pour ce prototype, on envoie simplement le message)
            $this->entityManager->flush();

            $this->logger->info('AI cluster deployment initiated', ['cluster_id' => $cluster->getId()]);

        } catch (\Exception $e) {
            $cluster->setStatus('failed');
            $cluster->setErrorMessage($e->getMessage());
            $cluster->addLog("ERROR: {$e->getMessage()}");
            $this->entityManager->flush();

            $this->logger->error('AI cluster deployment failed', [
                'error' => $e->getMessage(),
                'cluster_id' => $cluster->getId()
            ]);
            throw $e;
        }
    }

    /**
     * Marquer un cluster comme actif après déploiement réussi
     */
    public function activateCluster(AICluster $cluster, string $apiUrl): void
    {
        $cluster->setStatus('active');
        $cluster->setApiUrl($apiUrl);
        $cluster->setActiveAt(new \DateTimeImmutable());
        $cluster->addLog("Cluster activated at $apiUrl");
        $this->entityManager->flush();

        $this->logger->info('AI cluster activated', [
            'cluster_id' => $cluster->getId(),
            'api_url' => $apiUrl
        ]);
    }

    /**
     * Créer une AIDeployment à partir de la config du cluster
     */
    private function createDeploymentFromConfig(
        Application $app,
        ?string $aiModel,
        array $config
    ) {
        // Note: Ce serait appelé avec AIDeploymentService
        // Pour le prototype, on retourne simplement null
        // Une implémentation complète créerait une AIDeployment entity
        return null;
    }

    /**
     * Récupérer le cluster IA pour une application
     */
    public function getClusterForApplication(Application $app): ?AICluster
    {
        return $this->clusterRepository->findByApplication($app);
    }
}
