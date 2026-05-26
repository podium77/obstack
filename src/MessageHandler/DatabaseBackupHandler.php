<?php

namespace App\MessageHandler;

use App\Enum\RemediationAction;
use App\Message\DatabaseBackupMessage;
use App\Remediation\RemediationEngine;
use App\Repository\ApplicationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DatabaseBackupHandler
{
    public function __construct(
        private readonly RemediationEngine       $engine,
        private readonly ApplicationRepository   $appRepo,
        private readonly LoggerInterface         $logger,
    ) {}

    public function __invoke(DatabaseBackupMessage $message): void
    {
        $apps = $message->applicationId
            ? [$this->appRepo->find($message->applicationId)]
            : $this->appRepo->findAllActive();

        foreach (array_filter($apps) as $app) {
            $this->logger->info("Sauvegarde planifiée BDD pour {$app->getName()}");
            try {
                $this->engine->execute($app, RemediationAction::DB_BACKUP, triggeredBy: 'scheduler');
            } catch (\Throwable $e) {
                $this->logger->error("Sauvegarde échouée pour {$app->getName()}: {$e->getMessage()}");
            }
        }
    }
}
