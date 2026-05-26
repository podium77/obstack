<?php
namespace App\Controller;

use App\Service\Monitoring\JaegerService;
use App\Service\Monitoring\LokiService;
use App\Service\Monitoring\OpenTelemetryService;
use App\Service\Monitoring\PrometheusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class InstanceModulesController extends AbstractController
{
    public function __construct(
        private readonly PrometheusService $prometheusService,
        private readonly OpenTelemetryService $openTelemetryService,
        private readonly LokiService $lokiService,
        private readonly JaegerService $jaegerService,
    ) {}

    #[Route('/instance/{id}/modules', name: 'instance_modules_index')]
    public function index(int $id): Response
    {
        $modules = [
            ['label' => 'Prometheus', 'route' => 'instance_modules_prometheus'],
            ['label' => 'OpenTelemetry + eBPF', 'route' => 'instance_modules_opentelemetry'],
            ['label' => 'Loki', 'route' => 'instance_modules_loki'],
            ['label' => 'Jaeger', 'route' => 'instance_modules_jaeger'],
        ];

        return $this->render('instance_modules/index.html.twig', [
            'instanceId' => $id,
            'modules' => $modules,
        ]);
    }

    #[Route('/instance/{id}/modules/prometheus', name: 'instance_modules_prometheus')]
    public function prometheus(int $id): Response
    {
        return $this->render('instance_modules/prometheus.html.twig', ['data' => $this->prometheusService->getDashboardData($id)]);
    }

    #[Route('/instance/{id}/modules/opentelemetry', name: 'instance_modules_opentelemetry')]
    public function opentelemetry(int $id): Response
    {
        return $this->render('instance_modules/opentelemetry.html.twig', ['data' => $this->openTelemetryService->getDashboardData($id)]);
    }

    #[Route('/instance/{id}/modules/loki', name: 'instance_modules_loki')]
    public function loki(int $id): Response
    {
        return $this->render('instance_modules/loki.html.twig', ['data' => $this->lokiService->getDashboardData($id)]);
    }

    #[Route('/instance/{id}/modules/jaeger', name: 'instance_modules_jaeger')]
    public function jaeger(int $id): Response
    {
        return $this->render('instance_modules/jaeger.html.twig', ['data' => $this->jaegerService->getDashboardData($id)]);
    }
}
