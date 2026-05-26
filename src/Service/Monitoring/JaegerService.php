<?php
namespace App\Service\Monitoring;

class JaegerService implements MonitoringModuleInterface
{
    public function getModuleName(): string
    {
        return 'jaeger';
    }

    public function getDashboardData(int $instanceId): array
    {
        return [
            'module' => 'jaeger',
            'label' => 'Jaeger',
            'instanceId' => $instanceId,
            'panels' => [
                [
                    'id' => 'distributed-traces',
                    'title' => 'Traces Distribuées',
                    'type' => 'graph',
                    'unit' => 'traces/min',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'latency-analysis',
                    'title' => 'Analyse de Latence',
                    'type' => 'graph',
                    'unit' => 'ms',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'service-calls',
                    'title' => 'Appels par Service',
                    'type' => 'stat',
                    'value' => 0,
                    'status' => 'no-data',
                ],
                [
                    'id' => 'error-traces',
                    'title' => 'Traces en Erreur',
                    'type' => 'stat',
                    'value' => 0,
                    'severity' => 'error',
                    'status' => 'ok',
                ],
            ],
        ];
    }
}
