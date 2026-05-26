<?php

namespace App\Entity;

use App\Repository\LocalUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LocalUserRepository::class)]
#[ORM\Table(name: 'local_users')]
#[ORM\UniqueConstraint(name: 'UNIQ_username', fields: ['username'])]
class LocalUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $username = '';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private ?string $password = null;

    /** Compte actif */
    #[ORM\Column]
    private bool $active = true;

    /** Source: local|ldap */
    #[ORM\Column(length: 20)]
    private string $source = 'local';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getId(): ?int { return $this->id; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $u): static { $this->username = $u; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): static { $this->email = $e; return $this; }

    public function getDisplayName(): ?string { return $this->displayName ?? $this->username; }
    public function setDisplayName(?string $d): static { $this->displayName = $d; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $s): static { $this->source = $s; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(?\DateTimeImmutable $v): static { $this->lastLoginAt = $v; return $this; }

    public function __toString(): string { return $this->getDisplayName() ?? $this->username; }
}
