<?php
namespace App\MessageHandler;

use App\Kubernetes\KubernetesCollector;
use App\Message\SyncKubernetesMessage;
use App\Repository\EnvironmentRepository;
use App\RCA\KnowledgeGraphService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncKubernetesHandler
{
    public function __construct(
        private readonly KubernetesCollector    $collector,
        private readonly EnvironmentRepository  $envRepo,
        private readonly KnowledgeGraphService  $kg,
        private readonly LoggerInterface        $logger,
    ) {}

    public function __invoke(SyncKubernetesMessage $message): void
    {
        $env = $this->envRepo->find($message->environmentId);
        if (!$env || !$env->isKubernetesEnabled()) {
            return;
        }

        $this->logger->info("Synchronisation Kubernetes: {$env->getName()}");

        try {
            $stats = $this->collector->syncCluster($env);

            // Synchroniser les nodes dans le Knowledge Graph
            if ($env->getCompany()->isKgEnabled()) {
                foreach ($env->getKubernetesNodes() as $node) {
                    $this->kg->syncKubernetesNode($node, $env->getCompany());
                }
            }

            $this->logger->info(
                "K8s sync terminé: {$stats['nodes_synced']} nodes, {$stats['pods_synced']} pods",
                $stats
            );
        } catch (\Throwable $e) {
            $this->logger->error("K8s sync échoué pour {$env->getName()}: {$e->getMessage()}");
            throw $e;
        }
    }
}
