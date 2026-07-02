<?php
namespace App\Controller;

use App\Entity\Environment;
use App\Entity\LocalUser;
use App\Repository\AlertRepository;
use App\Repository\ApplicationRepository;
use App\Repository\MetricSnapshotRepository;
use App\Repository\CompanyRepository;
use App\Repository\RemediationLogRepository;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TenantContext            $tenant,
        private readonly ApplicationRepository    $appRepo,
        private readonly MetricSnapshotRepository $snapshotRepo,
        private readonly AlertRepository          $alertRepo,
        private readonly RemediationLogRepository $logRepo,
        private readonly CompanyRepository        $companyRepo,
    ) {}

#    #[Route('/', name: 'dashboard')]
#[Route('/dashboard', name: 'dashboard')]
public function index(Request $request): Response
{
    $symfonyUser = $this->getUser();
    
    // ── Admin Global : vue système sans limitation d'entreprise ──────
    if ($symfonyUser instanceof LocalUser && $symfonyUser->isGlobalAdmin()) {
        return $this->render('dashboard/admin_global.html.twig', [
            'user' => $symfonyUser,
            'companyCount' => $this->companyRepo->count([]),
        ]);
    }
    
    // ── Superadmin : vue globale toutes compagnies ──────────────────
    if ($this->isGranted('ROLE_SUPERADMIN')) {
        $allEnvironments = []; // ou charge tout depuis un repo global
        $allApps         = [];
        foreach ($allEnvironments as $env) {
            array_push($allApps, ...$this->appRepo->findAllActiveByEnvironment($env));
        }

        return $this->render('dashboard/superadmin.html.twig', [
            'user'                => $this->getUser(),
            'environments'        => $allEnvironments,
            'apps'                => $allApps,
            'companyCount'        => $this->companyRepo->count([]),
            'showRegisterNotice'  => $this->tenant->getCompany() === null,
        ]);
    }

    // ── Utilisateur standard ────────────────────────────────────────
    $user = $this->tenant->getUser();

    if (!$user) {
        $symfonyUser = $this->getUser();
        if (!$symfonyUser) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('dashboard/no_company.html.twig', [
            'username' => $symfonyUser->getUserIdentifier(),
        ]);
    }

    $company     = $user->getCompany();
        $environments = $this->tenant->getAccessibleEnvironments();

        // Filtre par environnement (optionnel)
        $envId      = $request->query->getInt('env', 0);
        $currentEnv = null;

        if ($envId) {
            foreach ($environments as $env) {
                if ($env->getId() === $envId && $this->tenant->canAccessEnvironment($env)) {
                    $currentEnv = $env;
                    $this->tenant->setCurrentEnvironment($env);
                    break;
                }
            }
        }

        // Charger les applications selon l'env sélectionné ou tous
        if ($currentEnv) {
            $apps = $this->appRepo->findAllActiveByEnvironment($currentEnv);
        } else {
            // Toutes les apps des environnements accessibles
            $apps = [];
            foreach ($environments as $env) {
                array_push($apps, ...$this->appRepo->findAllActiveByEnvironment($env));
            }
        }

        // Dernières métriques
        $latestMetrics = [];
        foreach ($apps as $app) {
            $snap = $this->snapshotRepo->findLatestForApp($app);
            if ($snap) $latestMetrics[$app->getId()] = $snap;
        }

        // Alertes actives (filtrées par env si applicable)
        $alertQuery   = $this->alertRepo->createQueryBuilder('a')
            ->join('a.application', 'app')
            ->join('app.environment', 'e')
            ->where('a.resolved = false')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(30);

        if ($currentEnv) {
            $alertQuery->andWhere('e.id = :env')->setParameter('env', $currentEnv->getId());
        } else {
            $envIds = array_map(fn($e) => $e->getId(), $environments);
            if ($envIds) {
                $alertQuery->andWhere('e.id IN (:envIds)')->setParameter('envIds', $envIds);
            }
        }
        $activeAlerts = $alertQuery->getQuery()->getResult();

        // Remédiations récentes
        $recentLogs  = $this->logRepo->findRecent(10);

        // Stats globales
        $alertsBySev  = $this->computeAlertStats($activeAlerts);
        $globalStats  = $this->computeGlobalStats($latestMetrics);

        return $this->render('dashboard/index.html.twig', [
            'user'          => $user,
            'company'       => $company,
            'environments'  => $environments,
            'currentEnv'    => $currentEnv,
            'apps'          => $apps,
            'latestMetrics' => $latestMetrics,
            'activeAlerts'  => $activeAlerts,
            'recentLogs'    => $recentLogs,
            'alertsBySev'   => $alertsBySev,
            'globalStats'   => $globalStats,
        ]);
    }

    #[Route('/api/dashboard/stream', name: 'api_dashboard_stream')]
    public function metricsStream(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->setCallback(function () {
            $i = 0;
            while ($i < 120) {
                echo "data: {\"t\":" . time() . "}\n\n";
                ob_flush(); flush();
                sleep(5);
                $i++;
            }
        });
        return $response;
    }

    #[Route('/api/dashboard/metrics', name: 'api_dashboard_metrics')]
    public function apiMetrics(): JsonResponse
    {
        $environments = $this->tenant->getAccessibleEnvironments();
        $data         = [];

        foreach ($environments as $env) {
            $apps = $this->appRepo->findAllActiveByEnvironment($env);
            foreach ($apps as $app) {
                $snap = $this->snapshotRepo->findLatestForApp($app);
                if (!$snap) continue;
                $data[] = [
                    'app_id'        => $app->getId(),
                    'app_name'      => $app->getName(),
                    'env_name'      => $env->getName(),
                    'severity'      => $snap->getSeverity()->value,
                    'cpu'           => $snap->getCpuPercent(),
                    'memory'        => $snap->getMemoryPercent(),
                    'disk'          => $snap->getMaxDiskPercent(),
                    'tomcat'        => $snap->getTomcatStatus(),
                    'db'            => $snap->getDbStatus(),
                    'collected_at'  => $snap->getCollectedAt()->format('c'),
                    'machine_type'  => $app->getMachineType()->value,
                    'is_k8s'        => $app->isKubernetesNode(),
                ];
            }
        }

        return $this->json(['metrics' => $data, 'ts' => time()]);
    }

    private function computeAlertStats(array $alerts): array
    {
        $stats = [];
        foreach ($alerts as $alert) {
            $key = $alert->getSeverity()->value;
            $stats[$key] = ($stats[$key] ?? 0) + 1;
        }
        return $stats;
    }

    private function computeGlobalStats(array $latestMetrics): array
    {
        if (empty($latestMetrics)) {
            return ['avg_cpu' => 0, 'avg_mem' => 0, 'agents_up' => 0, 'agents_total' => 0];
        }

        $cpuVals  = array_filter(array_map(fn($s) => $s->getCpuPercent(), $latestMetrics));
        $memVals  = array_filter(array_map(fn($s) => $s->getMemoryPercent(), $latestMetrics));
        $agentsUp = count(array_filter($latestMetrics, fn($s) => $s->isCollectionSuccess()));

        return [
            'avg_cpu'      => $cpuVals ? round(array_sum($cpuVals) / count($cpuVals), 1) : 0,
            'avg_mem'      => $memVals ? round(array_sum($memVals) / count($memVals), 1) : 0,
            'agents_up'    => $agentsUp,
            'agents_total' => count($latestMetrics),
        ];
    }
}