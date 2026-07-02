<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 10: Add executionTime field to audit logs for performance tracking
 */
final class Version20260702000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add executionTime field to audit_logs table for performance metrics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_logs ADD COLUMN execution_time INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_audit_execution_time ON audit_logs (execution_time)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_audit_execution_time ON audit_logs');
        $this->addSql('ALTER TABLE audit_logs DROP COLUMN execution_time');
    }
}
