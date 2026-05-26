<?php
namespace App\Tests\Entity;

use App\Entity\Environment;
use App\Entity\RemediationPolicy;
use App\Enum\RemediationAction;
use App\Enum\TriggerMetric;
use PHPUnit\Framework\TestCase;

class RemediationPolicyTest extends TestCase
{
    public function testInitialValues(): void
    {
        $policy = new RemediationPolicy();

        $this->assertTrue($policy->isEnabled());
        $this->assertEquals(TriggerMetric::CPU_PERCENT, $policy->getTriggerMetric());
        $this->assertEquals(RemediationAction::TOMCAT_RESTART, $policy->getAction());
        $this->assertEquals(80.0, $policy->getThreshold());
        $this->assertEquals('gte', $policy->getOperator());
        $this->assertSame([], $policy->getActionParams());
    }

    public function testActionParamsCanBeUpdated(): void
    {
        $policy = new RemediationPolicy();
        $policy->setActionParams(['service' => 'tomcat9']);

        $this->assertEquals(['service' => 'tomcat9'], $policy->getActionParams());
    }

    public function testTriggerConditions(): void
    {
        $policy = new RemediationPolicy();
        $policy->setTriggerMetric(TriggerMetric::LATENCY_MS);
        $policy->setOperator('lt');
        $policy->setThreshold(25.0);

        $this->assertEquals(TriggerMetric::LATENCY_MS, $policy->getTriggerMetric());
        $this->assertEquals('lt', $policy->getOperator());
        $this->assertEquals(25.0, $policy->getThreshold());
    }
}
