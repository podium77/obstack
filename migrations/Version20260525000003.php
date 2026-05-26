<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move agent_token_id FK from agent_tokens to applications table for OneToOne relationship';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_agent_tokens_application_id');
        
        // Add agent_token_id FK to applications table
        if (!$this->hasApplicationAgentTokenColumn($schema)) {
            $this->addSql('ALTER TABLE applications ADD COLUMN agent_token_id INTEGER');
            $this->addSql('ALTER TABLE applications ADD CONSTRAINT fk_applications_agent_token FOREIGN KEY (agent_token_id) REFERENCES agent_tokens(id) ON DELETE SET NULL');
            $this->addSql('CREATE UNIQUE INDEX uniq_applications_agent_token_id ON applications(agent_token_id)');
        }
        
        // Drop the old FK from agent_tokens if it exists
        try {
            $this->addSql('ALTER TABLE agent_tokens DROP CONSTRAINT IF EXISTS fk_agent_tokens_application_id');
            $this->addSql('ALTER TABLE agent_tokens DROP COLUMN IF EXISTS application_id');
        } catch (\Exception $e) {
            // Column might not exist
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_applications_agent_token_id');
        $this->addSql('ALTER TABLE applications DROP CONSTRAINT IF EXISTS fk_applications_agent_token');
        $this->addSql('ALTER TABLE applications DROP COLUMN IF EXISTS agent_token_id');
        
        $this->addSql('ALTER TABLE agent_tokens ADD COLUMN application_id INTEGER');
        $this->addSql('ALTER TABLE agent_tokens ADD CONSTRAINT fk_agent_tokens_application_id FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL');
    }

    private function hasApplicationAgentTokenColumn(Schema $schema): bool
    {
        if (!$schema->hasTable('applications')) {
            return false;
        }
        return $schema->getTable('applications')->hasColumn('agent_token_id');
    }
}
