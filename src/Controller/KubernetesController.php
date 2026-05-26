<?php
namespace App\Controller;

use App\Entity\Environment;
use App\Kubernetes\KubernetesCollector;
use App\Repository\EnvironmentRepository;
use App\Repository\KubernetesNodeRepository;
use App\Repository\KubernetesPodRepository;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kubernetes', name: 'k8s_')]
#[IsGranted('ROLE_USER')]
class KubernetesController extends AbstractController
{
    public function __construct(
        private readonly KubernetesCollector    $collector,
        private readonly KubernetesNodeRepository $nodeRepo,
        private readonly KubernetesPodRepository  $podRepo,
        private readonly EnvironmentRepository    $envRepo,
        private readonly TenantContext          $tenant,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $environments = $this->tenant->getAccessibleEnvironments();

        return $this->render('kubernetes/index.html.twig', [
            'environments' => $environments,
        ]);
    }

    #[Route('/env/{id}', name: 'k8s_dashboard', requirements: ['id' => '\d+'])]
    public function dashboard(Environment $env): Response
    {
        if (!$this->tenant->canAccessEnvironment($env)) {
            throw $this->createAccessDeniedException();
        }

        if (!$env->isKubernetesEnabled()) {
            return $this->render('kubernetes/not_configured.html.twig', ['env' => $env]);
        }

        $summary = $this->collector->getClusterSummary($env);
        $nodes   = $this->nodeRepo->findByEnvironmentWithPods($env);

        return $this->render('kubernetes/dashboard.html.twig', [
            'env'     => $env,
            'summary' => $summary,
            'nodes'   => $nodes,
            'canAdmin'=> $this->tenant->canAdmin($env),
        ]);
    }

    #[Route('/env/{id}/node/{nodeId}', name: 'node_detail', requirements: ['id' => '\d+', 'nodeId' => '\d+'])]
    public function nodeDetail(Environment $env, int $nodeId): Response
    {
        if (!$this->tenant->canAccessEnvironment($env)) {
            throw $this->createAccessDeniedException();
        }

        $node = $this->nodeRepo->find($nodeId);
        if (!$node || $node->getEnvironment() !== $env) {
            throw $this->createNotFoundException();
        }

        return $this->render('kubernetes/node_detail.html.twig', [
            'env'  => $env,
            'node' => $node,
            'pods' => $node->getPods()->toArray(),
        ]);
    }

    #[Route('/env/{id}/sync', name: 'sync', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function sync(Environment $env): JsonResponse
    {
        if (!$this->tenant->canAdmin($env)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if (!$env->isKubernetesEnabled()) {
            return $this->json(['error' => 'Kubernetes non configuré pour cet environnement.'], 400);
        }

        try {
            $stats = $this->collector->syncCluster($env);
            return $this->json([
                'success' => true,
                'stats'   => $stats,
                'message' => "{$stats['nodes_synced']} node(s) et {$stats['pods_synced']} pod(s) synchronisés.",
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/env/{id}/api/nodes', name: 'api_nodes', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function apiNodes(Environment $env): JsonResponse
    {
        if (!$this->tenant->canAccessEnvironment($env)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $nodes = $this->nodeRepo->findByEnvironmentWithPods($env);
        $data  = [];

        foreach ($nodes as $node) {
            $metrics = $node->getCurrentMetrics();
            $data[]  = [
                'id'          => $node->getId(),
                'name'        => $node->getNodeName(),
                'role'        => $node->getRole()->value,
                'role_label'  => $node->getRole()->getLabel(),
                'status'      => $node->getStatus(),
                'internal_ip' => $node->getInternalIp(),
                'cpu_percent' => $metrics['cpu_usage_percent'] ?? null,
                'mem_percent' => $metrics['memory_usage_percent'] ?? null,
                'pod_count'   => $node->getPodCount(),
                'pod_capacity'=> $node->getPodCapacity(),
                'cpu_capacity'=> $node->getCpuCapacity(),
                'mem_gb'      => $node->getMemoryCapacityGb(),
                'k8s_version' => $node->getKubernetesVersion(),
                'runtime'     => $node->getContainerRuntime(),
                'conditions'  => $node->getConditions(),
                'labels'      => $node->getLabels(),
                'last_sync'   => $node->getLastSyncAt()?->format('c'),
            ];
        }

        return $this->json(['nodes' => $data]);
    }
}
