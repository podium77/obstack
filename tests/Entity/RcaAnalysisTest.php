<?php
namespace App\Tests\Entity;

use App\Entity\Alert;
use App\Entity\Application;
use App\Entity\Environment;
use App\Entity\RcaAnalysis;
use App\Enum\RcaStatus;
use PHPUnit\Framework\TestCase;

class RcaAnalysisTest extends TestCase
{
    public function testInitialStatus(): void
    {
        $analysis = new RcaAnalysis();
        $this->assertEquals(RcaStatus::PENDING, $analysis->getStatus());
    }

    public function testAddRecommendation(): void
    {
        $analysis = new RcaAnalysis();
        $analysis->addRecommendation('Redémarrer le service Tomcat');

        $recommendations = $analysis->getRecommendations();
        $this->assertCount(1, $recommendations);
        $this->assertEquals('Redémarrer le service Tomcat', $recommendations[0]);
    }

    public function testAddTimelineEvent(): void
    {
        $analysis = new RcaAnalysis();
        $event = [
            'timestamp' => '2026-05-21T12:00:00+02:00',
            'title' => 'Détection de l\'alerte',
            'description' => 'CPU > 90% pendant 5 minutes',
        ];
        $analysis->addTimelineEvent($event);

        $timeline = $analysis->getTimeline();
        $this->assertCount(1, $timeline);
        $this->assertEquals($event, $timeline[0]);
    }

    public function testSetMetricsAtTrigger(): void
    {
        $analysis = new RcaAnalysis();
        $metrics = [
            'cpu_percent' => 95.5,
            'memory_percent' => 88.2,
        ];
        $analysis->setMetricsAtTrigger($metrics);

        $this->assertEquals($metrics, $analysis->getMetricsAtTrigger());
    }

    public function testLifecycleCallbacks(): void
    {
        $analysis = new RcaAnalysis();
        $this->assertNotNull($analysis->getCreatedAt());
        $this->assertNull($analysis->getUpdatedAt());
    }
}
