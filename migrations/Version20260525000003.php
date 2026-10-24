<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rétablit la relation OneToOne sur agent_tokens.application_id conformément au mapping AgentToken';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_tokens DROP CONSTRAINT IF EXISTS fk_agent_tokens_application_id');
        $this->addSql('ALTER TABLE agent_tokens DROP CONSTRAINT IF EXISTS fk_f4bd46a83e030acd');
        $this->addSql('DROP INDEX IF EXISTS idx_agent_tokens_application_id');
        $this->addSql('DROP INDEX IF EXISTS idx_f4bd46a83e030acd');
        $this->addSql('ALTER TABLE agent_tokens ADD CONSTRAINT FK_F4BD46A83E030ACD FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_F4BD46A83E030ACD ON agent_tokens (application_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_tokens DROP CONSTRAINT IF EXISTS FK_F4BD46A83E030ACD');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_F4BD46A83E030ACD');
        $this->addSql('ALTER TABLE agent_tokens ADD CONSTRAINT fk_agent_tokens_application_id FOREIGN KEY (application_id) REFERENCES applications (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_agent_tokens_application_id ON agent_tokens (application_id)');
    }
}
