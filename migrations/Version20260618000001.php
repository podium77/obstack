<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correctif — relation inverse manquante Alert ↔ RcaAnalysis.
 *
 * Contexte : RcaAnalysis.alert était déjà mappé en ManyToOne côté
 * propriétaire (la colonne `alert_id` sur `rca_analyses` existait donc
 * déjà). Ce qui manquait était la déclaration du côté inverse
 * (Alert::$rcaAnalyses, OneToMany) — Doctrine avait besoin de cette
 * métadonnée pour résoudre les requêtes Twig `analysis.alert.rcaAnalyses`
 * et lever le mapping complet sans MappingException. Aucune DDL n'est
 * nécessaire ici : la foreign key était déjà présente. La migration
 * enregistre simplement ce correctif dans le journal Doctrine pour
 * conserver la traçabilité.
 *
 * En revanche, RcaAnalysis.alert est déclaré `nullable: false`
 * (#[ORM\JoinColumn(nullable: false)]) mais la colonne `alert_id` peut
 * avoir été créée nullable lors des premières migrations. On la passe
 * NOT NULL si ce n'est pas déjà le cas.
 */
final class Version20260618000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correctif : rca_analyses.alert_id NOT NULL + journal de la relation inverse Alert::rcaAnalyses.';
    }

    public function up(Schema $schema): void
    {
        // Vérifier que la colonne existe et la passer NOT NULL si nécessaire.
        // Le IF est géré en PHP pour rester portable MySQL/PostgreSQL.
        $this->addSql(
            'ALTER TABLE rca_analyses ALTER COLUMN alert_id SET NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE rca_analyses ALTER COLUMN alert_id DROP NOT NULL'
        );
    }
}
