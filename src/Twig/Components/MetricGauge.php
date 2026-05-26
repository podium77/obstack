<?php
namespace App\Twig\Components;

use App\Entity\Application;
use App\Repository\MetricSnapshotRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Composant Twig Live : jauge temps réel d'une métrique.
 * Usage Twig: <twig:MetricGauge :appId="app.id" metric="cpu" />
 */
#[AsLiveComponent]
class MetricGauge
{
    use DefaultActionTrait;

    #[LiveProp]
    public int $appId = 0;

    /** cpu | memory | disk | latency */
    #[LiveProp]
    public string $metric = 'cpu';

    public function __construct(
        private readonly MetricSnapshotRepository $snapshotRepo,
    ) {}

    public function getSnapshot(): ?array
    {
        $app  = $this->getApplication();
        if (!$app) return null;

        $snap = $this->snapshotRepo->findLatestForApp($app);
        if (!$snap) return null;

        return match($this->metric) {
            'cpu'     => [
                'value'   => $snap->getCpuPercent(),
                'label'   => 'CPU',
                'unit'    => '%',
                'warn'    => 75,
                'crit'    => 90,
                'sub'     => 'Load: ' . implode(' / ', $snap->getLoadAverage()),
            ],
            'memory'  => [
                'value'   => $snap->getMemoryPercent(),
                'label'   => 'Mémoire',
                'unit'    => '%',
                'warn'    => 70,
                'crit'    => 85,
                'sub'     => "{$snap->getMemoryUsedMb()} / {$snap->getMemoryTotalMb()} MB",
            ],
            'disk'    => [
                'value'   => $snap->getMaxDiskPercent(),
                'label'   => 'Disque',
                'unit'    => '%',
                'warn'    => 75,
                'crit'    => 90,
                'sub'     => count($snap->getDiskStats()) . ' volume(s)',
            ],
            'latency' => [
                'value'   => $snap->getLatencies()['internal_ms'] ?? null,
                'label'   => 'Latence LAN',
                'unit'    => 'ms',
                'warn'    => 100,
                'crit'    => 500,
                'sub'     => 'Réseau interne',
            ],
            default => null,
        };
    }

    public function getSeverityClass(): string
    {
        $data = $this->getSnapshot();
        if (!$data || $data['value'] === null) return 'ok';
        if ($data['value'] >= $data['crit']) return 'critical';
        if ($data['value'] >= $data['warn']) return 'warn';
        return 'ok';
    }

    private function getApplication(): ?Application
    {
        /** @var \App\Repository\ApplicationRepository $repo */
        $repo = $this->snapshotRepo->getEntityManager()->getRepository(Application::class);
        return $repo->find($this->appId);
    }
}
