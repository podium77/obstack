<?php
namespace App\Service\Monitoring;

interface MonitoringModuleInterface
{
    public function getModuleName(): string;

    public function getDashboardData(int $instanceId): array;
}
