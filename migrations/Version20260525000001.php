<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne modules à la table agent_tokens pour stocker les modules activés par agent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_tokens ADD COLUMN IF NOT EXISTS modules JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_tokens DROP COLUMN IF EXISTS modules');
    }
}
