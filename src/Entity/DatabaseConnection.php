<?php

namespace App\Entity;

use App\Repository\DatabaseConnectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Connexion à une base de données externe.
 * 
 * Utilisée par la console d'administration système pour accéder
 * directement aux bases de données déclarées.
 *
 * Types supportés:
 *  - mysql
 *  - postgresql
 *  - neo4j
 *  - arangodb
 */
#[ORM\Entity(repositoryClass: DatabaseConnectionRepository::class)]
#[ORM\Table(name: 'database_connections')]
#[ORM\UniqueConstraint(name: 'uniq_connection_name', fields: ['name'])]
class DatabaseConnection
{
    const TYPE_MYSQL = 'mysql';
    const TYPE_POSTGRESQL = 'postgresql';
    const TYPE_NEO4J = 'neo4j';
    const TYPE_ARANGODB = 'arangodb';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Nom de la connexion (unique) */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $name = '';

    /** Description de la connexion */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    /** Type de moteur: mysql | postgresql | neo4j | arangodb */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(['mysql', 'postgresql', 'neo4j', 'arangodb'])]
    private string $type = self::TYPE_POSTGRESQL;

    /** Hôte/adresse du serveur */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $host = '';

    /** Port de connexion */
    #[ORM\Column]
    #[Assert\Range(min: 1, max: 65535)]
    private int $port = 5432;

    /** Nom de la base de données */
    #[ORM\Column(length: 255)]
    private ?string $database = null;

    /** Identifiant/utilisateur */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $username = '';

    /** Mot de passe chiffré */
    #[ORM\Column(type: 'text')]
    private string $encryptedPassword = '';

    /** Paramètres avancés (JSON) */
    #[ORM\Column(type: 'json')]
    private array $advancedOptions = [
        'ssl' => false,
        'timeout' => 30,
        'pool_size' => 5,
    ];

    /** Connexion active */
    #[ORM\Column]
    private bool $active = true;

    /** Testée avec succès */
    #[ORM\Column]
    private bool $tested = false;

    /** Dernier test de connexion */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function setDatabase(?string $database): self
    {
        $this->database = $database;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getEncryptedPassword(): string
    {
        return $this->encryptedPassword;
    }

    public function setEncryptedPassword(string $encryptedPassword): self
    {
        $this->encryptedPassword = $encryptedPassword;
        return $this;
    }

    public function getAdvancedOptions(): array
    {
        return $this->advancedOptions;
    }

    public function setAdvancedOptions(array $advancedOptions): self
    {
        $this->advancedOptions = $advancedOptions;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function isTested(): bool
    {
        return $this->tested;
    }

    public function setTested(bool $tested): self
    {
        $this->tested = $tested;
        return $this;
    }

    public function getLastTestedAt(): ?\DateTimeImmutable
    {
        return $this->lastTestedAt;
    }

    public function setLastTestedAt(?\DateTimeImmutable $lastTestedAt): self
    {
        $this->lastTestedAt = $lastTestedAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
