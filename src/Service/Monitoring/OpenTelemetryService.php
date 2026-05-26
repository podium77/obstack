<?php
namespace App\Service\Monitoring;

class OpenTelemetryService implements MonitoringModuleInterface
{
    public function getModuleName(): string
    {
        return 'opentelemetry';
    }

    public function getDashboardData(int $instanceId): array
    {
        return [
            'module' => 'opentelemetry',
            'label' => 'OpenTelemetry + eBPF',
            'instanceId' => $instanceId,
            'panels' => [
                [
                    'id' => 'trace-latency',
                    'title' => 'Latence des Traces',
                    'type' => 'graph',
                    'unit' => 'ms',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'ebpf-metrics',
                    'title' => 'Métriques eBPF',
                    'type' => 'graph',
                    'unit' => 'events/s',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'span-count',
                    'title' => 'Total Spans',
                    'type' => 'stat',
                    'value' => 0,
                    'status' => 'no-data',
                ],
                [
                    'id' => 'error-rate',
                    'title' => 'Taux d\'Erreur',
                    'type' => 'stat',
                    'value' => 0,
                    'unit' => '%',
                    'status' => 'ok',
                ],
            ],
        ];
    }
}
