<?php
namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Component;

class MetricCardComponent extends Component
{
    public string $label;
    public string|float|null $value;
    public string $unit = '';
    public string $icon = '';
    public bool $isCritical = false;
    public bool $isWarning = false;
}
