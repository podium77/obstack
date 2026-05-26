<?php
namespace App\Twig\Components;

use App\Enum\AlertSeverity;
use Symfony\UX\TwigComponent\Component;

class AlertBadgeComponent extends Component
{
    public AlertSeverity $severity;
    public bool $small = false;
}
