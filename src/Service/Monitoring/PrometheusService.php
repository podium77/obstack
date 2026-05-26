<?php
namespace App\Service\Monitoring;

class PrometheusService implements MonitoringModuleInterface
{
    public function getModuleName(): string
    {
        return 'prometheus';
    }

    public function getDashboardData(int $instanceId): array
    {
        return [
            'module' => 'prometheus',
            'label' => 'Prometheus',
            'instanceId' => $instanceId,
            'panels' => [
                [
                    'id' => 'cpu-usage',
                    'title' => 'Utilisation CPU',
                    'type' => 'graph',
                    'unit' => '%',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'memory-usage',
                    'title' => 'Utilisation Mémoire',
                    'type' => 'graph',
                    'unit' => 'GB',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'targets',
                    'title' => 'Targets Prometheus',
                    'type' => 'stat',
                    'value' => 0,
                    'status' => 'no-data',
                ],
                [
                    'id' => 'alerts',
                    'title' => 'Alertes Actives',
                    'type' => 'stat',
                    'value' => 0,
                    'status' => 'ok',
                ],
            ],
        ];
    }
}
