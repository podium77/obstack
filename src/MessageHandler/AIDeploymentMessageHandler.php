<?php

namespace App\MessageHandler;

use App\Entity\AIDeployment;
use App\Message\AIDeploymentMessage;
use App\Repository\AIDeploymentRepository;
use App\Service\AI\AIDeploymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler pour les messages AIDeploymentMessage
 * 
 * Traite les déploiements Mode 2 de manière asynchrone
 */
#[AsMessageHandler]
class AIDeploymentMessageHandler
{
    public function __construct(
        private AIDeploymentRepository $repository,
        private AIDeploymentService $deploymentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function __invoke(AIDeploymentMessage $message): void
    {
        try {
            $deployment = $this->repository->find($message->getDeploymentId());
            
            if (!$deployment) {
                $this->logger->error('Deployment not found', ['id' => $message->getDeploymentId()]);
                return;
            }

            $this->logger->info('Processing deployment message', ['id' => $deployment->getId()]);
            
            // Exécuter le déploiement
            $this->deploymentService->executeDeployment($deployment);
            
            $this->logger->info('Deployment message processed successfully', ['id' => $deployment->getId()]);

        } catch (\Exception $e) {
            $this->logger->error('Error processing deployment message', [
                'id' => $message->getDeploymentId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw so Messenger retries
        }
    }
}
