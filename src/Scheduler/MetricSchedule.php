<?php
namespace App\Scheduler;

use App\Message\CheckAlertsMessage;
use App\Message\CollectMetricsMessage;
use App\Message\DatabaseBackupMessage;
use App\Message\SyncKubernetesMessage;
use App\Repository\EnvironmentRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('default')]
class MetricSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface       $cache,
        private readonly EnvironmentRepository $envRepo,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = (new Schedule())->stateful($this->cache);

        // Collecte métriques toutes les 60s
        $schedule->add(RecurringMessage::every('1 minute', new CollectMetricsMessage()));

        // Vérification alertes toutes les 30s
        $schedule->add(RecurringMessage::every('30 seconds', new CheckAlertsMessage()));

        // Sauvegarde BDD quotidienne à 3h
        $schedule->add(RecurringMessage::cron('0 3 * * *', new DatabaseBackupMessage()));

        // Synchronisation Kubernetes toutes les 5 minutes pour chaque env K8s actif
        $schedule->add(RecurringMessage::every('5 minutes', new class {
            public string $type = 'k8s_sync_trigger';
        }));

        return $schedule;
    }
}
