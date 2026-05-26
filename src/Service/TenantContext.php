<?php

namespace App\Service;

use App\Entity\CompanyUser;
use App\Entity\Environment;
use App\Enum\UserEnvironmentRole;

class TenantContext
{
    private ?string $tenantId = null;
    private ?CompanyUser $user = null;
    private ?Environment $currentEnvironment = null;

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setUser(?CompanyUser $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?CompanyUser
    {
        return $this->user;
    }

    public function getAccessibleEnvironments(): array
    {
        if (!$this->user) {
            return [];
        }

        $company = $this->user->getCompany();
        if (!$company) {
            return [];
        }

        if ($this->user->isSuperAdmin() || $this->user->hasGlobalAccess()) {
            return $company->getEnvironments()->toArray();
        }

        $environments = [];
        foreach ($this->user->getEnvironmentAccesses() as $access) {
            $environment = $access->getEnvironment();
            if ($environment !== null && $access->isActive()) {
                $environments[] = $environment;
            }
        }

        return $environments;
    }

    public function canAccessEnvironment(Environment $env): bool
    {
        return $this->user ? $this->user->hasAccessToEnvironment($env) : false;
    }

    public function canAdmin(Environment $env): bool
    {
        if (!$this->user) {
            return false;
        }

        $role = $this->user->getRoleInEnvironment($env);
        return $role ? $role->canAdmin() : false;
    }

    public function canOperate(Environment $env): bool
    {
        if (!$this->user) {
            return false;
        }

        $role = $this->user->getRoleInEnvironment($env);
        return $role ? $role->canOperate() : false;
    }

    public function getRoleInEnvironment(Environment $env): ?UserEnvironmentRole
    {
        return $this->user ? $this->user->getRoleInEnvironment($env) : null;
    }

    public function setCurrentEnvironment(Environment $env): void
    {
        $this->currentEnvironment = $env;
    }

    public function getCurrentEnvironment(): ?Environment
    {
        return $this->currentEnvironment;
    }

    public function getCompany()
    {
        return $this->user?->getCompany();
    }

    public function isSuperAdmin(): bool
    {
        return $this->user?->isSuperAdmin() ?? false;
    }
}
