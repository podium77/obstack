<?php
namespace App\Service;

use App\Entity\Alert;
use App\RCA\PyRcaService;
use App\Repository\MetricSnapshotRepository;
use App\RCA\RcaResult;

class RcaService
{
    public function __construct(
        private readonly PyRcaService $pyRcaService,
        private readonly MetricSnapshotRepository $snapshotRepo,
    ) {}

    public function analyzeAlert(Alert $alert): RcaResult
    {
        $company = $alert->getApplication()->getEnvironment()->getCompany();
        $snapshots = $this->snapshotRepo->findHistoryForApp($alert->getApplication(), 24);

        return $this->pyRcaService->analyzeAlert($alert, $snapshots, $company);
    }
}
