<?php
namespace App\MessageHandler;

use App\Message\RemediationJobMessage;
use App\Remediation\RemediationEngine;
use App\Repository\ApplicationRepository;
use App\Repository\RemediationPolicyRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RemediationJobHandler
{
    public function __construct(
        private readonly RemediationEngine           $engine,
        private readonly ApplicationRepository       $appRepo,
        private readonly RemediationPolicyRepository $policyRepo,
        private readonly LoggerInterface             $logger,
    ) {}

    public function __invoke(RemediationJobMessage $message): void
    {
        $app = $this->appRepo->find($message->applicationId);
        if (!$app) {
            $this->logger->error("Application #{$message->applicationId} introuvable pour remédiation.");
            return;
        }

        $policy = $message->policyId ? $this->policyRepo->find($message->policyId) : null;

        $this->logger->info("Remédiation: {$message->action->value} sur {$app->getName()} [{$app->getEnvironment()->getCompany()->getSlug()}]");

        $this->engine->execute(
            app:         $app,
            action:      $message->action,
            policy:      $policy,
            triggeredBy: $message->triggeredBy,
            extraParams: $message->extraParams,
        );
    }
}
