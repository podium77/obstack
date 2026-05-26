<?php
namespace App\RCA;

use App\Entity\Company;
use App\Entity\Application;
use App\Entity\KubernetesNode;
use App\Entity\Alert;
use App\Entity\RemediationLog;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Intégration Knowledge Graph pour la représentation des dépendances
 * et la corrélation d'incidents.
 *
 * Supporte Neo4j (via API Bolt/REST) et ArangoDB.
 *
 * Le graphe modélise:
 *  - Noeuds: Application, Service, Database, KubernetesNode, Pod, Host
 *  - Relations: DEPENDS_ON, RUNS_ON, CONNECTS_TO, PART_OF, ALERTS, REMEDIATED
 *
 * Permet:
 *  - Tracer les dépendances entre services
 *  - Propager les alertes aux services dépendants
 *  - Identifier les points de défaillance uniques (SPOF)
 *  - Historiser les patterns de remédiation
 */
class KnowledgeGraphService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Synchronise une application dans le graphe de connaissance.
     */
    public function syncApplication(Application $app, Company $company): void
    {
        if (!$company->isKgEnabled()) return;

        $config = $company->getKgConfig();

        try {
            $appNode = [
                'id'           => "app_{$app->getId()}",
                'type'         => 'Application',
                'name'         => $app->getName(),
                'environment'  => $app->getEnvironment()->getSlug(),
                'os'           => $app->getOsType()->value,
                'host'         => $app->getHostAddress(),
                'machine_type' => $app->getMachineType()->value,
                'is_virtual'   => $app->isVirtualMachine(),
            ];

            $this->runCypher($config, "
                MERGE (a:Application {id: \$id})
                SET a += \$props
                RETURN a
            ", ['id' => $appNode['id'], 'props' => $appNode]);

            // Créer les noeuds pour les technologies détectées
            foreach ($app->getDetectedTechnologies() as $tech) {
                $techId = "tech_{$app->getId()}_{$tech['type']}";
                $this->runCypher($config, "
                    MERGE (t:Technology {id: \$id})
                    SET t.name = \$name, t.type = \$type, t.version = \$version, t.status = \$status
                    WITH t
                    MATCH (a:Application {id: \$appId})
                    MERGE (a)-[:HAS_TECHNOLOGY]->(t)
                ", [
                    'id'      => $techId,
                    'name'    => $tech['type'],
                    'type'    => $tech['type'],
                    'version' => $tech['version'] ?? null,
                    'status'  => $tech['status'] ?? 'unknown',
                    'appId'   => $appNode['id'],
                ]);
            }

            // Créer les dépendances BDD
            if ($app->getDbType()) {
                $this->runCypher($config, "
                    MATCH (a:Application {id: \$appId})
                    MERGE (d:Database {type: \$dbType, environment: \$env})
                    MERGE (a)-[:CONNECTS_TO]->(d)
                ", [
                    'appId'  => $appNode['id'],
                    'dbType' => $app->getDbType()->value,
                    'env'    => $app->getEnvironment()->getSlug(),
                ]);
            }

        } catch (\Throwable $e) {
            $this->logger->warning("KG sync failed for {$app->getName()}: {$e->getMessage()}");
        }
    }

    /**
     * Enregistre une alerte dans le graphe.
     */
    public function recordAlert(Alert $alert, Company $company): void
    {
        if (!$company->isKgEnabled()) return;

        $config = $company->getKgConfig();
        $app    = $alert->getApplication();

        try {
            $this->runCypher($config, "
                MATCH (a:Application {id: \$appId})
                CREATE (alert:Alert {
                    id: \$alertId,
                    title: \$title,
                    severity: \$severity,
                    metric: \$metric,
                    metric_value: \$metricValue,
                    created_at: \$createdAt
                })
                CREATE (a)-[:HAS_ALERT]->(alert)
            ", [
                'appId'       => "app_{$app->getId()}",
                'alertId'     => "alert_{$alert->getId()}",
                'title'       => $alert->getTitle(),
                'severity'    => $alert->getSeverity()->value,
                'metric'      => $alert->getMetric(),
                'metricValue' => $alert->getMetricValue(),
                'createdAt'   => $alert->getCreatedAt()->format('c'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning("KG alert record failed: {$e->getMessage()}");
        }
    }

    /**
     * Enregistre une remédiation et son impact.
     */
    public function recordRemediation(RemediationLog $log, Company $company): void
    {
        if (!$company->isKgEnabled()) return;

        $config = $company->getKgConfig();
        $app    = $log->getApplication();

        try {
            $this->runCypher($config, "
                MATCH (a:Application {id: \$appId})
                CREATE (r:Remediation {
                    id: \$remId,
                    action: \$action,
                    success: \$success,
                    duration_seconds: \$duration,
                    automatic: \$automatic,
                    executed_at: \$executedAt
                })
                CREATE (a)-[:WAS_REMEDIATED]->(r)
            ", [
                'appId'       => "app_{$app->getId()}",
                'remId'       => "rem_{$log->getId()}",
                'action'      => $log->getAction()->value,
                'success'     => $log->isSuccess(),
                'duration'    => $log->getDurationSeconds(),
                'automatic'   => $log->isAutomatic(),
                'executedAt'  => $log->getExecutedAt()->format('c'),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning("KG remediation record failed: {$e->getMessage()}");
        }
    }

    /**
     * Synchronise un node Kubernetes dans le graphe.
     */
    public function syncKubernetesNode(KubernetesNode $node, Company $company): void
    {
        if (!$company->isKgEnabled()) return;

        $config = $company->getKgConfig();

        try {
            $nodeId = "k8s_node_{$node->getId()}";
            $this->runCypher($config, "
                MERGE (n:KubernetesNode {id: \$id})
                SET n.name = \$name,
                    n.role = \$role,
                    n.status = \$status,
                    n.internal_ip = \$internalIp,
                    n.k8s_version = \$k8sVersion,
                    n.runtime = \$runtime
                RETURN n
            ", [
                'id'         => $nodeId,
                'name'       => $node->getNodeName(),
                'role'       => $node->getRole()->value,
                'status'     => $node->getStatus(),
                'internalIp' => $node->getInternalIp(),
                'k8sVersion' => $node->getKubernetesVersion(),
                'runtime'    => $node->getContainerRuntime(),
            ]);

            // Lier les pods au node
            foreach ($node->getPods() as $pod) {
                $podId = "pod_{$pod->getId()}";
                $this->runCypher($config, "
                    MERGE (p:Pod {id: \$podId})
                    SET p.name = \$name,
                        p.namespace = \$ns,
                        p.phase = \$phase,
                        p.restart_count = \$restarts
                    WITH p
                    MATCH (n:KubernetesNode {id: \$nodeId})
                    MERGE (p)-[:RUNS_ON]->(n)
                ", [
                    'podId'    => $podId,
                    'name'     => $pod->getPodName(),
                    'ns'       => $pod->getNamespace(),
                    'phase'    => $pod->getPhase(),
                    'restarts' => $pod->getRestartCount(),
                    'nodeId'   => $nodeId,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning("KG K8s node sync failed: {$e->getMessage()}");
        }
    }

    /**
     * Requête pour trouver les dépendances d'une application.
     */
    public function getApplicationDependencies(Application $app, Company $company): array
    {
        if (!$company->isKgEnabled()) return [];

        $config = $company->getKgConfig();

        try {
            $result = $this->runCypher($config, "
                MATCH (a:Application {id: \$appId})-[r]->(dep)
                RETURN type(r) as relationship, dep
                LIMIT 50
            ", ['appId' => "app_{$app->getId()}"]);

            return $result['data'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->warning("KG query failed: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Requête pour identifier les points de défaillance uniques (SPOF).
     */
    public function findSinglePointsOfFailure(Company $company): array
    {
        if (!$company->isKgEnabled()) return [];

        $config = $company->getKgConfig();

        try {
            $result = $this->runCypher($config, "
                MATCH (n)
                WHERE (()-[:DEPENDS_ON]->(n)) OR (()-[:CONNECTS_TO]->(n))
                WITH n, COUNT {()-[:DEPENDS_ON|CONNECTS_TO]->(n)} AS dependents
                WHERE dependents >= 2
                RETURN n, dependents
                ORDER BY dependents DESC
                LIMIT 20
            ", []);

            return $result['data'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Exécute une requête Cypher (Neo4j) via l'API REST.
     */
    private function runCypher(array $config, string $query, array $params): array
    {
        $uri      = rtrim($config['uri'] ?? '', '/');
        $database = $config['database'] ?? 'neo4j';

        $response = $this->httpClient->request(
            'POST',
            "{$uri}/db/{$database}/tx/commit",
            [
                'auth_basic' => [$config['user'] ?? 'neo4j', $config['password'] ?? ''],
                'json'       => [
                    'statements' => [[
                        'statement'  => $query,
                        'parameters' => $params,
                    ]],
                ],
                'timeout'    => 10,
            ]
        );

        $data = $response->toArray();

        if (!empty($data['errors'])) {
            throw new \RuntimeException(
                'Neo4j error: ' . ($data['errors'][0]['message'] ?? 'Unknown error')
            );
        }

        return $data['results'][0] ?? [];
    }
}
