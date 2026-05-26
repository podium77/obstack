<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Données initiales pour obstack v2.
 * Cette migration peut être sautée si vous créez votre première entreprise via /register.
 */
final class Version20240201000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index de performance supplémentaires';
    }

    public function up(Schema $schema): void
    {
        // Index composites pour les requêtes fréquentes
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_remediation_logs_executed_at
            ON remediation_logs (executed_at DESC)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_alerts_created_resolved
            ON alerts (resolved, created_at DESC)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_applications_active_env
            ON applications (environment_id, active)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_k8s_nodes_role
            ON kubernetes_nodes (environment_id, role, status)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_k8s_pods_phase
            ON kubernetes_pods (node_id, phase)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_agent_tokens_active
            ON agent_tokens (environment_id, active, last_seen_at)');

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_env_users_active
            ON environment_users (environment_id, user_id, active)');

        // Partitionnement logique des metric_snapshots par date (pour PostgreSQL)
        // En production, utiliser un partitionnement natif avec pg_partman
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_snapshots_severity
            ON metric_snapshots (application_id, severity, collected_at DESC)
            WHERE severity != \'ok\'');

        // Vue pour les statistiques globales par environnement
        $this->addSql('CREATE OR REPLACE VIEW v_env_stats AS
            SELECT
                e.id                AS environment_id,
                e.name              AS environment_name,
                e.slug              AS environment_slug,
                c.id                AS company_id,
                c.name              AS company_name,
                COUNT(DISTINCT a.id) AS total_apps,
                COUNT(DISTINCT CASE WHEN a.active THEN a.id END) AS active_apps,
                COUNT(DISTINCT at.id) AS total_tokens,
                COUNT(DISTINCT CASE WHEN at.active AND at.last_seen_at > NOW() - INTERVAL \'3 minutes\'
                    THEN at.id END) AS online_agents,
                COUNT(DISTINCT CASE WHEN al.resolved = false THEN al.id END) AS active_alerts,
                COUNT(DISTINCT kn.id) AS k8s_nodes,
                MAX(ms.collected_at) AS last_metric_at
            FROM environments e
            JOIN companies c ON c.id = e.company_id
            LEFT JOIN applications a ON a.environment_id = e.id
            LEFT JOIN agent_tokens at ON at.environment_id = e.id
            LEFT JOIN alerts al ON al.application_id = a.id
            LEFT JOIN kubernetes_nodes kn ON kn.environment_id = e.id
            LEFT JOIN metric_snapshots ms ON ms.application_id = a.id
            WHERE e.active = true AND c.active = true
            GROUP BY e.id, e.name, e.slug, c.id, c.name');

        // Vue pour les agents en ligne par entreprise
        $this->addSql('CREATE OR REPLACE VIEW v_agent_status AS
            SELECT
                c.id                    AS company_id,
                c.name                  AS company_name,
                e.id                    AS environment_id,
                e.name                  AS environment_name,
                at.id                   AS token_id,
                at.name                 AS token_name,
                at.detected_hostname    AS hostname,
                at.last_seen_ip         AS ip,
                at.last_seen_at,
                at.active               AS token_active,
                CASE WHEN at.last_seen_at > NOW() - INTERVAL \'3 minutes\'
                    THEN true ELSE false END AS is_online,
                a.id                    AS application_id,
                a.name                  AS application_name,
                a.machine_type,
                a.os_type
            FROM agent_tokens at
            JOIN environments e ON e.id = at.environment_id
            JOIN companies c ON c.id = e.company_id
            LEFT JOIN applications a ON a.id = at.application_id
            WHERE at.active = true AND e.active = true AND c.active = true');

        // Fonction PostgreSQL pour purger les anciennes métriques
        $this->addSql("CREATE OR REPLACE FUNCTION purge_old_metrics(retention_days INTEGER DEFAULT 90)
            RETURNS INTEGER AS \$\$
            DECLARE
                deleted_count INTEGER;
            BEGIN
                DELETE FROM metric_snapshots
                WHERE collected_at < NOW() - (retention_days || ' days')::INTERVAL;
                GET DIAGNOSTICS deleted_count = ROW_COUNT;
                RETURN deleted_count;
            END;
            \$\$ LANGUAGE plpgsql");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW IF EXISTS v_env_stats');
        $this->addSql('DROP VIEW IF EXISTS v_agent_status');
        $this->addSql('DROP FUNCTION IF EXISTS purge_old_metrics');

        foreach ([
            'idx_remediation_logs_executed_at',
            'idx_alerts_created_resolved',
            'idx_applications_active_env',
            'idx_k8s_nodes_role',
            'idx_k8s_pods_phase',
            'idx_agent_tokens_active',
            'idx_env_users_active',
            'idx_snapshots_severity',
        ] as $idx) {
            $this->addSql("DROP INDEX IF EXISTS {$idx}");
        }
    }
}
