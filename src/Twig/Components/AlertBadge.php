<?php
namespace App\Twig\Components;

use App\Repository\AlertRepository;
use App\Service\TenantContext;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Composant badge alertes actives avec auto-refresh.
 * Usage: <twig:AlertBadge />
 */
#[AsLiveComponent]
class AlertBadge
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?int $environmentId = null;

    public function __construct(
        private readonly AlertRepository $alertRepo,
        private readonly TenantContext   $tenant,
    ) {}

    public function getCounts(): array
    {
        return $this->alertRepo->countActiveAlertsBySeverity();
    }

    public function getTotal(): int
    {
        $counts = $this->getCounts();
        return ($counts['critical'] ?? 0)
             + ($counts['error']    ?? 0)
             + ($counts['warning']  ?? 0);
    }

    public function hasCritical(): bool
    {
        $counts = $this->getCounts();
        return ($counts['critical'] ?? 0) > 0 || ($counts['error'] ?? 0) > 0;
    }
}
