<?php
namespace App\MessageHandler;

use App\Entity\Alert;
use App\Message\CheckAlertsMessage;
use App\Message\RemediationJobMessage;
use App\Message\SendAlertNotificationMessage;
use App\Repository\ApplicationRepository;
use App\Repository\MetricSnapshotRepository;
use App\Repository\RemediationPolicyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class CheckAlertsHandler
{
    public function __construct(
        private readonly ApplicationRepository       $appRepo,
        private readonly MetricSnapshotRepository    $snapshotRepo,
        private readonly RemediationPolicyRepository $policyRepo,
        private readonly EntityManagerInterface      $em,
        private readonly MessageBusInterface         $bus,
        private readonly LoggerInterface             $logger,
    ) {}

    public function __invoke(CheckAlertsMessage $message): void
    {
        $apps = $message->applicationId
            ? array_filter([$this->appRepo->find($message->applicationId)])
            : $this->appRepo->findAllActive();

        foreach ($apps as $app) {
            $snapshot = $this->snapshotRepo->findLatestForApp($app);
            if (!$snapshot) continue;

            $policies = $this->policyRepo->findEnabledForApp($app);

            foreach ($policies as $policy) {
                $value = $policy->getTriggerMetric()->extractValue($snapshot);
                if ($value === null) continue;

                if (!$policy->isThresholdReached($value)) {
                    if ($policy->getConsecutiveExecutions() > 0) {
                        $policy->resetConsecutiveExecutions();
                    }
                    continue;
                }

                if ($policy->isInCooldown()) {
                    $this->logger->debug("Cooldown actif: {$policy->getName()}");
                    continue;
                }

                // Créer alerte
                $alert = new Alert();
                $alert->setApplication($app);
                $alert->setSeverity($snapshot->getSeverity());
                $alert->setMetric($policy->getTriggerMetric()->value);
                $alert->setMetricValue($value);
                $alert->setThreshold($policy->getThreshold());
                $alert->setTitle(sprintf(
                    '%s — %s: %.1f%s (seuil: %.1f%s)',
                    $app->getName(),
                    $policy->getTriggerMetric()->getLabel(),
                    $value, $policy->getTriggerMetric()->getUnit(),
                    $policy->getThreshold(), $policy->getTriggerMetric()->getUnit()
                ));
                $alert->setMessage(sprintf(
                    'La métrique "%s" a atteint %.1f%s, dépassant le seuil de %.1f%s. %s',
                    $policy->getTriggerMetric()->getLabel(),
                    $value, $policy->getTriggerMetric()->getUnit(),
                    $policy->getThreshold(), $policy->getTriggerMetric()->getUnit(),
                    $policy->isAutoExecute()
                        ? "Auto-remédiation: {$policy->getAction()->getLabel()} déclenchée."
                        : "Action manuelle requise: {$policy->getAction()->getLabel()}."
                ));
                $this->em->persist($alert);

                // Notification
                $this->em->flush();
                $this->bus->dispatch(new SendAlertNotificationMessage($alert->getId()));

                // Auto-remédiation
                if ($policy->isAutoExecute()) {
                    if ($policy->isEscalationNeeded()) {
                        $this->logger->warning("Escalade: {$app->getName()} — {$policy->getName()} ({$policy->getConsecutiveExecutions()} exécutions)");
                        $escalade = new Alert();
                        $escalade->setApplication($app);
                        $escalade->setSeverity(\App\Enum\AlertSeverity::CRITICAL);
                        $escalade->setTitle("ESCALADE — {$app->getName()}: {$policy->getName()}");
                        $escalade->setMessage(
                            "Remédiation '{$policy->getAction()->getLabel()}' exécutée {$policy->getConsecutiveExecutions()} fois sans résolution. Intervention manuelle requise."
                        );
                        $this->em->persist($escalade);
                    } else {
                        $this->bus->dispatch(new RemediationJobMessage(
                            applicationId: $app->getId(),
                            action:        $policy->getAction(),
                            policyId:      $policy->getId(),
                        ));
                    }
                }
            }
            $this->em->flush();
        }
    }
}
