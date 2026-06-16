<?php
namespace App\Controller;

use App\Entity\Application;
use App\Enum\DbType;
use App\Enum\MachineType;
use App\Enum\OsType;
use App\Enum\RemediationAction;
use App\Enum\TriggerMetric;
use App\Message\CollectMetricsMessage;
use App\Repository\ApplicationRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\MetricSnapshotRepository;
use App\Repository\RemediationLogRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/application', name: 'app_application_')]
#[IsGranted('ROLE_USER')]
class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly TenantContext            $tenant,
        private readonly ApplicationRepository    $appRepo,
        private readonly MetricSnapshotRepository $snapshotRepo,
        private readonly RemediationLogRepository $logRepo,
        private readonly EnvironmentRepository    $envRepo,
        private readonly EntityManagerInterface   $em,
        private readonly MessageBusInterface      $bus,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $environments = $this->tenant->getAccessibleEnvironments();
        $apps         = [];
        $metrics      = [];

        foreach ($environments as $env) {
            foreach ($this->appRepo->findAllActiveByEnvironment($env) as $app) {
                $apps[]               = $app;
                $snap                 = $this->snapshotRepo->findLatestForApp($app);
                if ($snap) $metrics[$app->getId()] = $snap;
            }
        }

        return $this->render('application/index.html.twig', [
            'apps'    => $apps,
            'metrics' => $metrics,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Application $app): Response
    {
        $this->denyAccessIfNotAllowed($app);

        $latestSnapshot = $this->snapshotRepo->findLatestForApp($app);
        $history24h     = $this->snapshotRepo->findHistoryForApp($app, 24);
        $recentLogs     = $this->logRepo->findRecentForApp($app, 15);
        $stats24h       = $this->snapshotRepo->getAggregatedStats($app, 24);
        $chartData      = $this->buildChartData($history24h);

        return $this->render('application/show.html.twig', [
            'application'    => $app,
            'latestSnapshot' => $latestSnapshot,
            'recentLogs'     => $recentLogs,
            'stats24h'       => $stats24h,
            'chartData'      => $chartData,
        ]);
    }

    #[Route('/new', name: 'new')]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function form(Request $request, ?Application $app = null): Response
    {
        $isNew = ($app === null);
        if ($isNew) {
            $app = new Application();
        } else {
            $this->denyAccessIfNotAllowed($app);
        }

        $environments = $this->tenant->getAccessibleEnvironments();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Environnement
            $envId = (int)($data['environment_id'] ?? 0);
            $env   = $this->envRepo->find($envId);
            if ($env && $this->tenant->canAccessEnvironment($env)) {
                $app->setEnvironment($env);
            }

            $app->setName($data['name'] ?? '');
            $app->setDescription($data['description'] ?? null);
            $app->setOsType(OsType::from($data['os_type'] ?? 'debian'));
            $app->setDbType(!empty($data['db_type']) ? DbType::from($data['db_type']) : null);
            $app->setHostAddress($data['host_address'] ?? '');
            $app->setSshPort((int)($data['ssh_port'] ?? 22));
            $app->setSshUser($data['ssh_user'] ?? 'obstack');
            $app->setSshKeyPath($data['ssh_key_path'] ?? '/var/lib/obstack/.ssh/id_rsa');
            $app->setHealthUrl($data['health_url'] ?: null);
            $app->setColor($data['color'] ?? '#185FA5');
            $app->setActive(isset($data['active']));
            $app->setUptimeRestartThresholdHours(!empty($data['uptime_threshold']) ? (int)$data['uptime_threshold'] : null);

            $schedules = array_filter(array_map('trim', explode(',', $data['uptime_schedule'] ?? '')));
            $app->setUptimeRestartSchedule(array_values($schedules));

            $app->setThresholds([
                'cpu_warning'      => (float)($data['cpu_warning']  ?? 75),
                'cpu_critical'     => (float)($data['cpu_critical'] ?? 90),
                'memory_warning'   => (float)($data['mem_warning']  ?? 70),
                'memory_critical'  => (float)($data['mem_critical'] ?? 85),
                'disk_warning'     => (float)($data['disk_warning'] ?? 75),
                'disk_critical'    => (float)($data['disk_critical']?? 90),
                'latency_warning'  => (int)($data['lat_warning']    ?? 100),
                'latency_critical' => (int)($data['lat_critical']   ?? 500),
            ]);

            $app->setTomcatConfig([
                'service_name' => $data['tomcat_service'] ?? 'tomcat9',
                'webapps_dir'  => $data['tomcat_webapps'] ?? '/opt/tomcat/webapps',
                'logs_dir'     => $data['tomcat_logs']    ?? '/opt/tomcat/logs',
                'port'         => (int)($data['tomcat_port'] ?? 8080),
            ]);

            $app->setDbConfig([
                'service_name'          => $data['db_service']    ?? '',
                'data_dir'              => $data['db_data_dir']   ?? '',
                'backup_dir'            => $data['db_backup_dir'] ?? '/var/backups/db',
                'backup_retention_days' => (int)($data['db_retention'] ?? 7),
                'oracle_sid'            => $data['oracle_sid']    ?? 'ORCL',
                'oracle_home'           => $data['oracle_home']   ?? '/opt/oracle/product/19c/dbhome_1',
                'db_user'               => $data['db_user']       ?? 'sys',
                'db_host'               => $data['db_host']       ?? '127.0.0.1',
            ]);

            $this->em->persist($app);
            $this->em->flush();

            $this->addFlash('success', $isNew
                ? "Application \"{$app->getName()}\" créée."
                : "Application mise à jour."
            );

            return $this->redirectToRoute('app_application_show', ['id' => $app->getId()]);
        }
        // dd([
        //     'app_name'     => $app->getName(),
        //     'osTypes'      => OsType::cases(),
        //     'dbTypes'      => DbType::cases(),
        //     'machineTypes' => MachineType::cases(),
        //     'remActions'   => RemediationAction::cases(),
        //     'triggerMetrics' => TriggerMetric::cases(),
        // ]);
        return $this->render('application/form.html.twig', [
            'application'  => $app,
            'isNew'        => $isNew,
            'environments' => $environments,
            'osTypes'      => OsType::cases(),
            'dbTypes'      => DbType::cases(),
            'machineTypes' => MachineType::cases(),
            'remActions'   => RemediationAction::cases(),
            'triggerMetrics' => TriggerMetric::cases(),
        ]);
    }

    #[Route('/{id}/collect-now', name: 'collect_now', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function collectNow(Application $app): JsonResponse
    {
        $this->denyAccessIfNotAllowed($app);
        $this->bus->dispatch(new CollectMetricsMessage($app->getId()));

        return $this->json(['success' => true, 'message' => "Collecte déclenchée pour {$app->getName()}."]);
    }

    #[Route('/{id}/metrics-history', name: 'metrics_history', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function metricsHistory(Application $app, Request $request): JsonResponse
    {
        $this->denyAccessIfNotAllowed($app);
        $hours     = min(168, max(1, (int)$request->query->get('hours', 24)));
        $history   = $this->snapshotRepo->findHistoryForApp($app, $hours);
        return $this->json($this->buildChartData($history));
    }

    #[Route('/{id}/technologies', name: 'technologies', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function technologies(Application $app): JsonResponse
    {
        $this->denyAccessIfNotAllowed($app);
        return $this->json([
            'technologies'    => $app->getDetectedTechnologies(),
            'machine_type'    => $app->getMachineType()->value,
            'machine_label'   => $app->getMachineType()->getLabel(),
            'is_virtual'      => $app->isVirtualMachine(),
            'hardware_info'   => $app->getHardwareInfo(),
            'system_info'     => $app->getSystemInfo(),
            'last_detection'  => $app->getLastDetectionAt()?->format('c'),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function delete(Application $app, Request $request): Response
    {
        $this->denyAccessIfNotAllowed($app);
        if ($this->isCsrfTokenValid('delete_app_' . $app->getId(), $request->request->get('_token'))) {
            $envId = $app->getEnvironment()->getId();
            $this->appRepo->remove($app, true);
            $this->addFlash('success', "Application supprimée.");
            return $this->redirectToRoute('env_show', ['id' => $envId]);
        }
        return $this->redirectToRoute('app_application_show', ['id' => $app->getId()]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    private function denyAccessIfNotAllowed(Application $app): void
    {
        if (!$this->tenant->canAccessEnvironment($app->getEnvironment())) {
            throw $this->createAccessDeniedException(
                "Accès non autorisé à l'application {$app->getName()}."
            );
        }
    }

    private function buildChartData(array $snapshots): array
    {
        $labels = $cpu = $memory = $disk = $latency = [];
        foreach ($snapshots as $snap) {
            $labels[]  = $snap->getCollectedAt()->format('H:i');
            $cpu[]     = $snap->getCpuPercent();
            $memory[]  = $snap->getMemoryPercent();
            $disk[]    = $snap->getMaxDiskPercent();
            $latency[] = $snap->getLatencies()['internal_ms']
                      ?? $snap->getLatencies()['loopback_ms']
                      ?? null;
        }
        return compact('labels', 'cpu', 'memory', 'disk', 'latency');
    }
}
