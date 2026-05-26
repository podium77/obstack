<?php
namespace App\Service\Monitoring;

class LokiService implements MonitoringModuleInterface
{
    public function getModuleName(): string
    {
        return 'loki';
    }

    public function getDashboardData(int $instanceId): array
    {
        return [
            'module' => 'loki',
            'label' => 'Loki',
            'instanceId' => $instanceId,
            'panels' => [
                [
                    'id' => 'recent-logs',
                    'title' => 'Logs Récents',
                    'type' => 'logs',
                    'entries' => [],
                    'status' => 'no-data',
                ],
                [
                    'id' => 'error-logs',
                    'title' => 'Erreurs & Exceptions',
                    'type' => 'stat',
                    'value' => 0,
                    'severity' => 'error',
                    'status' => 'ok',
                ],
                [
                    'id' => 'log-volume',
                    'title' => 'Volume de Logs',
                    'type' => 'graph',
                    'unit' => 'logs/min',
                    'data' => ['series' => [], 'datapoints' => []],
                    'status' => 'no-data',
                ],
            ],
        ];
    }
}
