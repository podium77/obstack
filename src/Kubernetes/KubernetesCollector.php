<?php
namespace App\Kubernetes;

use App\Entity\Environment;
use App\Entity\KubernetesNode;
use App\Entity\KubernetesPod;
use App\Enum\NodeRole;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Collecte les données du cluster Kubernetes via l'API officielle.
 * Supporte: kubeconfig, Bearer token, in-cluster config.
 */
class KubernetesCollector
{
    private array $headers = [];
    private string $apiBase = '';

    public function __construct(
        private readonly HttpClientInterface  $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface      $logger,
    ) {}

    /**
     * Initialise la connexion vers l'API Kubernetes d'un environnement.
     */
    public function init(Environment $env): void
    {
        $this->apiBase = rtrim($env->getKubernetesApiUrl() ?? '', '/');

        // Extraire le Bearer token du kubeconfig JSON
        $kubeconfig = $env->getKubeconfig();
        if ($kubeconfig) {
            $config = json_decode($kubeconfig, true);
            $token  = $config['users'][0]['user']['token']
                ?? $config['token']
                ?? null;
            if ($token) {
                $this->headers = ['Authorization' => "Bearer {$token}"];
            }
        }
    }

    /**
     * Synchronise tous les nodes et pods du cluster.
     */
    public function syncCluster(Environment $env): array
    {
        $this->init($env);

        $stats = [
            'nodes_synced' => 0,
            'pods_synced'  => 0,
            'errors'       => [],
        ];

        try {
            // 1. Récupérer les nodes
            $nodesData = $this->apiGet('/api/v1/nodes');
            foreach ($nodesData['items'] ?? [] as $nodeItem) {
                $node = $this->syncNode($env, $nodeItem);
                $stats['nodes_synced']++;

                // 2. Récupérer les pods de ce node
                $nodeName  = $nodeItem['metadata']['name'];
                $podsData  = $this->apiGet("/api/v1/pods?fieldSelector=spec.nodeName={$nodeName}");
                foreach ($podsData['items'] ?? [] as $podItem) {
                    $this->syncPod($node, $podItem);
                    $stats['pods_synced']++;
                }
            }

            // 3. Métriques des nodes (metrics-server si disponible)
            $this->syncNodeMetrics($env);

        } catch (\Throwable $e) {
            $stats['errors'][] = $e->getMessage();
            $this->logger->error("Kubernetes sync error: {$e->getMessage()}");
        }

        $this->em->flush();
        return $stats;
    }

    private function syncNode(Environment $env, array $data): KubernetesNode
    {
        $nodeName = $data['metadata']['name'];

        // Chercher le node existant ou en créer un nouveau
        $repo = $this->em->getRepository(KubernetesNode::class);
        $node = $repo->findOneBy(['environment' => $env, 'nodeName' => $nodeName])
            ?? new KubernetesNode();

        $node->setEnvironment($env);
        $node->setNodeName($nodeName);
        $node->setLabels($data['metadata']['labels'] ?? []);
        $node->setAnnotations($data['metadata']['annotations'] ?? []);
        $node->setTaints($data['spec']['taints'] ?? []);
        $node->setConditions($data['status']['conditions'] ?? []);
        $node->setCapacity($data['status']['capacity'] ?? []);
        $node->setAllocatable($data['status']['allocatable'] ?? []);
        $node->setKubernetesVersion($data['status']['nodeInfo']['kubeletVersion'] ?? null);
        $node->setContainerRuntime($data['status']['nodeInfo']['containerRuntimeVersion'] ?? null);
        $node->setOsImage($data['status']['nodeInfo']['osImage'] ?? null);
        $node->setArchitecture($data['status']['nodeInfo']['architecture'] ?? null);
        $node->setLastSyncAt(new \DateTimeImmutable());

        // Déterminer le rôle du node
        $labels = $data['metadata']['labels'] ?? [];
        $role   = $this->detectNodeRole($labels, $data['spec']['taints'] ?? []);
        $node->setRole($role);

        // IPs
        foreach ($data['status']['addresses'] ?? [] as $addr) {
            if ($addr['type'] === 'InternalIP') $node->setInternalIp($addr['address']);
            if ($addr['type'] === 'ExternalIP') $node->setExternalIp($addr['address']);
        }

        // Statut Ready
        $readyCondition = array_filter(
            $data['status']['conditions'] ?? [],
            fn($c) => $c['type'] === 'Ready'
        );
        $readyCondition = array_values($readyCondition);
        $node->setStatus($readyCondition[0]['status'] === 'True' ? 'Ready' : 'NotReady');

        $this->em->persist($node);
        return $node;
    }

    private function syncPod(KubernetesNode $node, array $data): KubernetesPod
    {
        $podName   = $data['metadata']['name'];
        $namespace = $data['metadata']['namespace'];

        $repo = $this->em->getRepository(KubernetesPod::class);
        $pod  = $repo->findOneBy(['node' => $node, 'podName' => $podName, 'namespace' => $namespace])
            ?? new KubernetesPod();

        $pod->setNode($node);
        $pod->setPodName($podName);
        $pod->setNamespace($namespace);
        $pod->setLabels($data['metadata']['labels'] ?? []);
        $pod->setPhase($data['status']['phase'] ?? 'Unknown');
        $pod->setPodIp($data['status']['podIP'] ?? null);

        // Détecter deployment/service owner
        foreach ($data['metadata']['ownerReferences'] ?? [] as $owner) {
            if ($owner['kind'] === 'ReplicaSet') {
                // Le nom du RS contient le nom du déploiement
                $parts = explode('-', $owner['name']);
                array_pop($parts); // retirer le hash aléatoire
                $pod->setDeploymentName(implode('-', $parts));
            }
        }

        // Images des containers
        $images   = [];
        $requests = [];
        $limits   = [];
        foreach ($data['spec']['containers'] ?? [] as $container) {
            $images[] = $container['image'] ?? '';
            foreach ($container['resources']['requests'] ?? [] as $k => $v) {
                $requests[$container['name']][$k] = $v;
            }
            foreach ($container['resources']['limits'] ?? [] as $k => $v) {
                $limits[$container['name']][$k] = $v;
            }
        }
        $pod->setImages($images);
        $pod->setResourceRequests($requests);
        $pod->setResourceLimits($limits);

        // Statuts des containers
        $containerStatuses = [];
        $totalRestarts     = 0;
        foreach ($data['status']['containerStatuses'] ?? [] as $cs) {
            $containerStatuses[] = [
                'name'         => $cs['name'],
                'ready'        => $cs['ready'] ?? false,
                'restartCount' => $cs['restartCount'] ?? 0,
                'image'        => $cs['image'] ?? '',
                'state'        => array_key_first($cs['state'] ?? []) ?? 'unknown',
            ];
            $totalRestarts += $cs['restartCount'] ?? 0;
        }
        $pod->setContainerStatuses($containerStatuses);
        $pod->setRestartCount($totalRestarts);

        // Date de démarrage
        if (!empty($data['status']['startTime'])) {
            $pod->setStartedAt(new \DateTimeImmutable($data['status']['startTime']));
        }

        $pod->setLastSyncAt(new \DateTimeImmutable());
        $this->em->persist($pod);
        return $pod;
    }

    private function syncNodeMetrics(Environment $env): void
    {
        try {
            $metricsData = $this->apiGet('/apis/metrics.k8s.io/v1beta1/nodes');
            foreach ($metricsData['items'] ?? [] as $metric) {
                $nodeName = $metric['metadata']['name'];
                $repo     = $this->em->getRepository(KubernetesNode::class);
                $node     = $repo->findOneBy(['environment' => $env, 'nodeName' => $nodeName]);
                if (!$node) continue;

                $cpuUsage    = $this->parseCpuNano($metric['usage']['cpu'] ?? '0n');
                $memUsageMi  = $this->parseMemoryKi($metric['usage']['memory'] ?? '0Ki');

                $capacity    = $node->getCapacity();
                $cpuCores    = (int)($capacity['cpu'] ?? 1);
                $memKi       = $this->parseMemoryKi($capacity['memory'] ?? '0Ki');

                $node->setCurrentMetrics([
                    'cpu_usage_percent'    => $cpuCores > 0 ? round($cpuUsage / ($cpuCores * 1e9) * 100, 1) : null,
                    'memory_usage_percent' => $memKi > 0    ? round($memUsageMi / $memKi * 100, 1) : null,
                    'pod_count'            => $node->getPodCount(),
                    'pod_capacity'         => (int)($capacity['pods'] ?? 110),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->debug("metrics-server non disponible: {$e->getMessage()}");
        }
    }

    private function detectNodeRole(array $labels, array $taints): NodeRole
    {
        // Labels standards Kubernetes
        if (isset($labels['node-role.kubernetes.io/control-plane'])
            || isset($labels['node-role.kubernetes.io/master'])
        ) {
            return NodeRole::MASTER;
        }

        if (isset($labels['node-role.kubernetes.io/etcd'])) {
            return NodeRole::ETCD;
        }

        if (isset($labels['node-role.kubernetes.io/ingress'])
            || isset($labels['kubernetes.io/role']) && $labels['kubernetes.io/role'] === 'ingress'
        ) {
            return NodeRole::INGRESS;
        }

        // Taint master
        foreach ($taints as $taint) {
            if (str_contains($taint['key'] ?? '', 'master')
                || str_contains($taint['key'] ?? '', 'control-plane')
            ) {
                return NodeRole::MASTER;
            }
        }

        return NodeRole::WORKER;
    }

    private function apiGet(string $path): array
    {
        if (empty($this->apiBase)) {
            throw new \RuntimeException('Kubernetes API URL non configurée');
        }

        $response = $this->httpClient->request('GET', $this->apiBase . $path, [
            'headers'     => $this->headers,
            'verify_peer' => false,
            'timeout'     => 15,
        ]);

        return $response->toArray();
    }

    /** Convertit "100m" ou "4" CPU en nanosecondes équivalentes */
    private function parseCpuNano(string $cpu): float
    {
        if (str_ends_with($cpu, 'n')) return (float)rtrim($cpu, 'n');
        if (str_ends_with($cpu, 'm')) return (float)rtrim($cpu, 'm') * 1e6;
        return (float)$cpu * 1e9;
    }

    /** Convertit "1024Ki" ou "1Gi" en Ki */
    private function parseMemoryKi(string $mem): float
    {
        if (str_ends_with($mem, 'Ki')) return (float)rtrim($mem, 'Ki');
        if (str_ends_with($mem, 'Mi')) return (float)rtrim($mem, 'Mi') * 1024;
        if (str_ends_with($mem, 'Gi')) return (float)rtrim($mem, 'Gi') * 1024 * 1024;
        return (float)$mem / 1024;
    }

    /**
     * Retourne un résumé du cluster.
     */
    public function getClusterSummary(Environment $env): array
    {
        $nodeRepo = $this->em->getRepository(KubernetesNode::class);
        $podRepo  = $this->em->getRepository(KubernetesPod::class);

        $nodes   = $nodeRepo->findBy(['environment' => $env]);
        $masters = array_filter($nodes, fn($n) => $n->isMaster());
        $workers = array_filter($nodes, fn($n) => $n->isWorker());
        $ready   = array_filter($nodes, fn($n) => $n->isReady());

        return [
            'total_nodes'   => count($nodes),
            'master_nodes'  => count($masters),
            'worker_nodes'  => count($workers),
            'ready_nodes'   => count($ready),
            'nodes'         => $nodes,
            'last_sync'     => $nodes ? max(array_map(fn($n) => $n->getLastSyncAt(), $nodes)) : null,
        ];
    }
}
