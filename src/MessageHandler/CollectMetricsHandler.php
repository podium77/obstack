<?php
namespace App\MessageHandler;

use App\Agent\MetricCollector;
use App\Message\CheckAlertsMessage;
use App\Message\CollectMetricsMessage;
use App\Repository\ApplicationRepository;
use App\Repository\MetricSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CollectMetricsHandler
{
    public function __construct(
        private readonly MetricCollector          $collector,
        private readonly ApplicationRepository    $appRepo,
        private readonly MetricSnapshotRepository $snapshotRepo,
        private readonly EntityManagerInterface   $em,
        private readonly MessageBusInterface      $bus,
        private readonly LoggerInterface          $logger,
    ) {}

    public function __invoke(CollectMetricsMessage $message): void
    {
        $apps = $message->applicationId
            ? array_filter([$this->appRepo->find($message->applicationId)])
            : $this->appRepo->findAllActive();

        foreach ($apps as $app) {
            try {
                $snapshot = $this->collector->collect($app);
                $this->em->persist($snapshot);
                $this->em->flush();
                $this->bus->dispatch(new CheckAlertsMessage($app->getId()));
                $this->logger->info("Métriques collectées: {$app->getName()}");
            } catch (\Throwable $e) {
                $this->logger->error("Collecte échouée [{$app->getName()}]: {$e->getMessage()}");
            }
        }

        $retentionDays = (int)($_ENV['METRIC_RETENTION_DAYS'] ?? 90);
        $deleted = $this->snapshotRepo->deleteOlderThan(new \DateTimeImmutable("-{$retentionDays} days"));
        if ($deleted > 0) {
            $this->logger->info("Purge: {$deleted} snapshots supprimés (rétention {$retentionDays}j)");
        }
    }
}
