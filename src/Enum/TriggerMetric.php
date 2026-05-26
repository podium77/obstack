<?php
namespace App\Enum;

enum TriggerMetric: string {
    case CPU_PERCENT    = 'cpu_percent';
    case MEMORY_PERCENT = 'memory_percent';
    case DISK_PERCENT   = 'disk_percent';
    case LATENCY_MS     = 'latency_ms';
    case TOMCAT_STATUS  = 'tomcat_status';
    case DB_STATUS      = 'db_status';
    case UPTIME_HOURS   = 'uptime_hours';
    case LOAD_AVERAGE   = 'load_average';

    public function getLabel(): string {
        return match($this) {
            self::CPU_PERCENT    => 'CPU (%)',
            self::MEMORY_PERCENT => 'Mémoire (%)',
            self::DISK_PERCENT   => 'Disque (%)',
            self::LATENCY_MS     => 'Latence (ms)',
            self::TOMCAT_STATUS  => 'Statut Tomcat',
            self::DB_STATUS      => 'Statut BDD',
            self::UPTIME_HOURS   => 'Uptime (heures)',
            self::LOAD_AVERAGE   => 'Charge système',
        };
    }
    public function getUnit(): string {
        return match($this) {
            self::CPU_PERCENT, self::MEMORY_PERCENT, self::DISK_PERCENT => '%',
            self::LATENCY_MS   => 'ms',
            self::UPTIME_HOURS => 'h',
            default            => '',
        };
    }
    public function extractValue(\App\Entity\MetricSnapshot $snapshot): ?float {
        return match($this) {
            self::CPU_PERCENT    => $snapshot->getCpuPercent(),
            self::MEMORY_PERCENT => $snapshot->getMemoryPercent(),
            self::DISK_PERCENT   => $snapshot->getMaxDiskPercent(),
            self::LATENCY_MS     => $snapshot->getLatencies()['internal_ms'] ?? null,
            self::TOMCAT_STATUS  => $snapshot->getTomcatStatus() === 'running' ? 1.0 : 0.0,
            self::DB_STATUS      => $snapshot->getDbStatus()     === 'running' ? 1.0 : 0.0,
            self::UPTIME_HOURS   => $snapshot->getUptimeHours(),
            self::LOAD_AVERAGE   => $snapshot->getLoadAverage()[0] ?? null,
        };
    }
}
