<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise l’index partiel des métriques pour correspondre au mapping Doctrine';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_snapshots_severity');
        $this->addSql("CREATE INDEX idx_snapshots_severity ON metric_snapshots (application_id, severity, collected_at) WHERE severity != 'ok'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_snapshots_severity');
        $this->addSql("CREATE INDEX idx_snapshots_severity ON metric_snapshots (application_id, severity, collected_at DESC) WHERE severity != 'ok'");
    }
}