<?php

namespace App\MessageHandler;

use App\Message\AIClusterDeploymentMessage;
use App\Repository\AIClusterRepository;
use App\Service\AI\AIClusterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour AIClusterDeploymentMessage
 * Traite les déploiements de clusters IA de manière asynchrone
 */
#[AsMessageHandler]
class AIClusterDeploymentMessageHandler
{
    public function __construct(
        private AIClusterRepository $clusterRepository,
        private AIClusterService $clusterService,
        private LoggerInterface $logger
    ) {}

    public function __invoke(AIClusterDeploymentMessage $message): void
    {
        try {
            $cluster = $this->clusterRepository->find($message->getClusterId());

            if (!$cluster) {
                $this->logger->error('AI cluster not found', ['cluster_id' => $message->getClusterId()]);
                return;
            }

            $this->clusterService->deployCluster($cluster);

        } catch (\Exception $e) {
            $this->logger->error('Error processing AI cluster deployment message', [
                'cluster_id' => $message->getClusterId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
