<?php
namespace App\RCA;

use App\Entity\Company;
use App\Entity\Alert;
use App\Entity\MetricSnapshot;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Intégration de la bibliothèque PyRCA pour l'analyse de cause racine (RCA).
 *
 * PyRCA (https://github.com/salesforce/PyRCA) est une bibliothèque Python
 * d'analyse de cause racine pour les microservices et systèmes distribués.
 *
 * Modes supportés:
 *  - API REST (PyRCA exposé via une API)
 *  - Exécution locale via Python subprocess
 *
 * Modèles d'analyse:
 *  - bayesian     : Réseau bayésien
 *  - causal       : Analyse causale (PC algorithm)
 *  - epsilon_diag : Epsilon-Diagnosis
 *  - random_walk  : Random Walk
 *  - micro_scope  : MicroScope
 */
class PyRcaService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Analyse les métriques d'un snapshot pour identifier la cause racine d'une alerte.
     */
    public function analyzeAlert(Alert $alert, array $recentSnapshots, Company $company): RcaResult
    {
        $config = $company->getRcaConfig();

        if (!$company->isRcaEnabled() || empty($config['api_url'])) {
            return RcaResult::disabled();
        }

        try {
            $payload = $this->buildAnalysisPayload($alert, $recentSnapshots, $config);

            $response = $this->httpClient->request('POST', $config['api_url'] . '/analyze', [
                'headers' => [
                    'Authorization' => 'Bearer ' . ($config['api_key'] ?? ''),
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            return $this->parseResult($data);

        } catch (\Throwable $e) {
            $this->logger->error("PyRCA analysis failed: {$e->getMessage()}");
            return RcaResult::error($e->getMessage());
        }
    }

    /**
     * Lance une analyse PyRCA en batch sur plusieurs alertes.
     */
    public function batchAnalyze(array $alerts, array $metricsTimeSeries, Company $company): array
    {
        $config = $company->getRcaConfig();
        if (!$company->isRcaEnabled()) {
            return [];
        }

        $results = [];
        foreach ($alerts as $alert) {
            $snapshots = $metricsTimeSeries[$alert->getApplication()->getId()] ?? [];
            $results[$alert->getId()] = $this->analyzeAlert($alert, $snapshots, $company);
        }

        return $results;
    }

    private function buildAnalysisPayload(Alert $alert, array $snapshots, array $config): array
    {
        $app = $alert->getApplication();

        // Construire la série temporelle des métriques
        $timeSeries = [];
        foreach ($snapshots as $snap) {
            $ts = $snap->getCollectedAt()->getTimestamp();
            $timeSeries[] = [
                'timestamp'      => $ts,
                'cpu_percent'    => $snap->getCpuPercent(),
                'memory_percent' => $snap->getMemoryPercent(),
                'disk_percent'   => $snap->getMaxDiskPercent(),
                'latency_ms'     => $snap->getLatencies()['internal_ms'] ?? null,
                'tomcat_status'  => $snap->getTomcatStatus() === 'running' ? 1 : 0,
                'db_status'      => $snap->getDbStatus() === 'running' ? 1 : 0,
                'load_avg_1m'    => $snap->getLoadAverage()[0] ?? null,
                'connections'    => $snap->getActiveConnections(),
            ];
        }

        return [
            'model'        => $config['model'] ?? 'bayesian',
            'alert'        => [
                'id'           => $alert->getId(),
                'title'        => $alert->getTitle(),
                'metric'       => $alert->getMetric(),
                'metric_value' => $alert->getMetricValue(),
                'threshold'    => $alert->getThreshold(),
                'severity'     => $alert->getSeverity()->value,
                'timestamp'    => $alert->getCreatedAt()->getTimestamp(),
            ],
            'application'  => [
                'name'     => $app->getName(),
                'os'       => $app->getOsType()->value,
                'db_type'  => $app->getDbType()?->value,
                'technologies' => array_column($app->getDetectedTechnologies(), 'type'),
            ],
            'time_series'  => $timeSeries,
            'window_size'  => count($timeSeries),
        ];
    }

    private function parseResult(array $data): RcaResult
    {
        return new RcaResult(
            success:     true,
            rootCauses:  $data['root_causes'] ?? [],
            confidence:  $data['confidence'] ?? null,
            explanation: $data['explanation'] ?? null,
            graphData:   $data['causal_graph'] ?? null,
            model:       $data['model_used'] ?? null,
            analyzedAt:  new \DateTimeImmutable(),
        );
    }
}
