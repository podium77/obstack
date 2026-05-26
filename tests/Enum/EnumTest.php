<?php
namespace App\Tests\Enum;

use App\Enum\RemediationAction;
use App\Enum\RcaStatus;
use App\Enum\TriggerMetric;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function testRcaStatusLabels(): void
    {
        $this->assertEquals('En attente', RcaStatus::PENDING->getLabel());
        $this->assertEquals('Terminé', RcaStatus::COMPLETED->getLabel());
    }

    public function testRemediationActionLabels(): void
    {
        $this->assertEquals('Redémarrer Tomcat', RemediationAction::TOMCAT_RESTART->getLabel());
        $this->assertEquals('Redémarrer la BDD', RemediationAction::DB_RESTART->getLabel());
    }

    public function testTriggerMetricLabelsAndUnits(): void
    {
        $this->assertEquals('CPU (%)', TriggerMetric::CPU_PERCENT->getLabel());
        $this->assertEquals('%', TriggerMetric::CPU_PERCENT->getUnit());

        $this->assertEquals('Latence (ms)', TriggerMetric::LATENCY_MS->getLabel());
        $this->assertEquals('ms', TriggerMetric::LATENCY_MS->getUnit());
    }
}
