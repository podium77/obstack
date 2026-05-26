<?php
namespace App\Twig\Extension;

use App\Enum\AlertSeverity;
use App\Enum\MachineType;
use App\Enum\TechnologyType;
use App\Repository\AgentTokenRepository;
use App\Repository\ApplicationRepository;
use App\Service\TenantContext;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class obstackExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ApplicationRepository $appRepo,
        private readonly AgentTokenRepository $tokenRepo,
    ) {}

    public function getGlobals(): array
    {
        $user    = $this->tenant->getUser();
        $company = $user?->getCompany();

        return [
            'obs_user'       => $user,
            'obs_company'    => $company,
            'obs_envs'       => $user ? $this->tenant->getAccessibleEnvironments() : [],
            'obs_is_super'   => $user?->isSuperAdmin() ?? false,
            'obs_brand_color'=> $company?->getBrandColor() ?? '#185FA5',
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('severity_class', [$this, 'severityClass']),
            new TwigFunction('severity_icon',  [$this, 'severityIcon']),
            new TwigFunction('tech_icon',      [$this, 'techIcon']),
            new TwigFunction('machine_icon',   [$this, 'machineIcon']),
            new TwigFunction('can_access_env', [$this, 'canAccessEnv']),
            new TwigFunction('monitoring_module_links', [$this, 'getMonitoringModuleLinks']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('filesize',       [$this, 'formatFilesize']),
            new TwigFilter('duration',       [$this, 'formatDuration']),
            new TwigFilter('time_ago',       [$this, 'timeAgo']),
            new TwigFilter('percent_color',  [$this, 'percentColor']),
            new TwigFilter('tech_label',     [$this, 'techLabel']),
            new TwigFilter('machine_label',  [$this, 'machineLabel']),
        ];
    }

    // ─── Functions ────────────────────────────────────────────────────

    public function severityClass(string $severity): string
    {
        return match($severity) {
            'critical', 'error' => 'severity-critical',
            'warning'           => 'severity-warning',
            'info'              => 'severity-info',
            default             => 'severity-ok',
        };
    }

    public function severityIcon(string $severity): string
    {
        return match($severity) {
            'critical', 'error' => 'ti-alert-circle',
            'warning'           => 'ti-alert-triangle',
            'info'              => 'ti-info-circle',
            default             => 'ti-circle-check',
        };
    }

    public function techIcon(string $techType): string
    {
        $tech = TechnologyType::tryFrom($techType);
        return $tech ? $tech->getIcon() : 'ti-box';
    }

    public function machineIcon(string $machineType): string
    {
        $mt = MachineType::tryFrom($machineType);
        return $mt ? $mt->getIcon() : 'ti-server';
    }

    public function canAccessEnv(\App\Entity\Environment $env): bool
    {
        return $this->tenant->canAccessEnvironment($env);
    }

    public function getMonitoringModuleLinks(): array
    {
        $links = [];
        $seen = [];

        foreach ($this->loadMonitoringAgentTokens() as $token) {
            foreach ($token->getModules() as $module) {
                if (isset($seen[$module])) {
                    continue;
                }
                $link = $this->buildMonitoringLink($token, $module);
                if ($link !== null) {
                    $seen[$module] = true;
                    $links[] = $link;
                }
            }
        }

        if (!empty($links)) {
            return $links;
        }

        foreach ($this->loadMonitoringApplications() as $app) {
            $token = $app->getAgentToken();
            if (!$token) {
                continue;
            }

            foreach ($token->getModules() as $module) {
                if (isset($seen[$module])) {
                    continue;
                }
                $link = $this->buildMonitoringLink($token, $module, $app->getId());
                if ($link !== null) {
                    $seen[$module] = true;
                    $links[] = $link;
                }
            }
        }

        return $links;
    }

    private function buildMonitoringLink($token, string $module, ?int $instanceId = null): ?array
    {
        $instanceId = $instanceId ?? $token->getApplication()?->getId() ?? $token->getId();
        if ($instanceId === null) {
            return null;
        }

        $mapping = [
            'prometheus' => ['icon' => 'chart-line', 'label' => 'Prometheus'],
            'opentelemetry' => ['icon' => 'topology-star-3', 'label' => 'OpenTelemetry'],
            'loki' => ['icon' => 'file-text', 'label' => 'Loki'],
            'jaeger' => ['icon' => 'git-branch', 'label' => 'Jaeger'],
        ];

        if (!isset($mapping[$module])) {
            return null;
        }

        return [
            'route' => 'instance_modules_' . $module,
            'routeParams' => ['id' => $instanceId],
            'icon' => $mapping[$module]['icon'],
            'label' => $mapping[$module]['label'],
        ];
    }

    private function loadMonitoringApplications(): array
    {
        $env = $this->tenant->getCurrentEnvironment();
        if ($env !== null) {
            return $this->appRepo->findAllActiveByEnvironment($env);
        }

        $company = $this->tenant->getCompany();
        if ($company !== null) {
            return $this->appRepo->findAllActiveByCompany($company);
        }

        if ($this->tenant->isSuperAdmin()) {
            return $this->appRepo->findAllActive();
        }

        return [];
    }

    private function loadMonitoringAgentTokens(): array
    {
        $env = $this->tenant->getCurrentEnvironment();
        if ($env !== null) {
            return $this->tokenRepo->findByEnvironment($env);
        }

        $envs = $this->tenant->getAccessibleEnvironments();
        $tokens = [];
        foreach ($envs as $env) {
            array_push($tokens, ...$this->tokenRepo->findByEnvironment($env));
        }

        if (empty($tokens) && $this->tenant->getCompany() !== null) {
            return $this->tokenRepo->findByCompany($this->tenant->getCompany());
        }

        return $tokens;
    }

    private function buildMonitoringLinks(int $instanceId, array $modules): array
    {
        $mapping = [
            'prometheus' => ['icon' => 'chart-line', 'label' => 'Prometheus'],
            'opentelemetry' => ['icon' => 'topology-star-3', 'label' => 'OpenTelemetry'],
            'loki' => ['icon' => 'file-text', 'label' => 'Loki'],
            'jaeger' => ['icon' => 'git-branch', 'label' => 'Jaeger'],
        ];

        $links = [];
        foreach ($modules as $module) {
            if (!isset($mapping[$module])) {
                continue;
            }
            $links[] = [
                'route' => 'instance_modules_' . $module,
                'routeParams' => ['id' => $instanceId],
                'icon' => $mapping[$module]['icon'],
                'label' => $mapping[$module]['label'],
            ];
        }

        return $links;
    }

    // ─── Filters ──────────────────────────────────────────────────────

    public function formatFilesize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds >= 86400)  return round($seconds / 86400, 1) . 'j';
        if ($seconds >= 3600)   return round($seconds / 3600, 1) . 'h';
        if ($seconds >= 60)     return round($seconds / 60) . 'min';
        return $seconds . 's';
    }

    public function timeAgo(\DateTimeImmutable $dt): string
    {
        $diff = $dt->diff(new \DateTimeImmutable());
        if ($diff->days > 0)  return "il y a {$diff->days}j";
        if ($diff->h > 0)     return "il y a {$diff->h}h";
        if ($diff->i > 0)     return "il y a {$diff->i}min";
        return "à l'instant";
    }

    public function percentColor(float $value, float $warn = 75, float $critical = 90): string
    {
        if ($value >= $critical) return 'var(--color-critical)';
        if ($value >= $warn)     return 'var(--color-warn)';
        return 'var(--color-ok)';
    }

    public function techLabel(string $techType): string
    {
        $tech = TechnologyType::tryFrom($techType);
        return $tech ? $tech->getLabel() : ucfirst($techType);
    }

    public function machineLabel(string $machineType): string
    {
        $mt = MachineType::tryFrom($machineType);
        return $mt ? $mt->getLabel() : ucfirst($machineType);
    }
}
