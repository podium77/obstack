<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class LdapUser implements UserInterface
{
    public function __construct(
        private readonly string  $username,
        private readonly string  $displayName,
        private readonly ?string $email,
        private readonly array   $roles,
        private readonly string  $dn,
    ) {}

    public function getUserIdentifier(): string { return $this->username; }

    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, ['ROLE_USER']));
    }

    public function eraseCredentials(): void {}

    public function getUsername(): string    { return $this->username; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getEmail(): ?string      { return $this->email; }
    public function getDn(): string          { return $this->dn; }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles);
    }
}
