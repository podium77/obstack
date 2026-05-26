<?php
namespace App\Remediation;

use App\Agent\SshClient;
use App\Agent\SshConnection;
use App\Entity\Application;
use App\Entity\RemediationLog;
use App\Entity\RemediationPolicy;
use App\Enum\RemediationAction;
use App\Repository\RemediationLogRepository;
use App\Service\NotificationService;
use App\RCA\KnowledgeGraphService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class RemediationEngine
{
    public function __construct(
        private readonly SshClient                $ssh,
        private readonly NotificationService      $notification,
        private readonly RemediationLogRepository $logRepository,
        private readonly KnowledgeGraphService    $kg,
        private readonly EntityManagerInterface   $em,
        private readonly LoggerInterface          $logger,
    ) {}

    /**
     * Exécute une action de remédiation sur une application.
     *
     * @param Application        $app      L'application cible
     * @param RemediationAction  $action   L'action à exécuter
     * @param RemediationPolicy|null $policy  La politique déclencheuse (null = manuel)
     * @param string|null        $triggeredBy  Nom utilisateur (null = automatique)
     */
    public function execute(
        Application        $app,
        RemediationAction  $action,
        ?RemediationPolicy $policy      = null,
        ?string            $triggeredBy = null,
        array              $extraParams = [],
    ): RemediationLog {
        $startTime = microtime(true);
        $log       = new RemediationLog($app, $action);
        $log->setPolicy($policy);
        $log->setTriggeredBy($triggeredBy);
        $log->setAutomatic($triggeredBy === null);

        $this->logger->info("Remédiation: {$action->value} sur {$app->getName()} [{$app->getEnvironment()->getCompany()->getSlug()}]");

        try {
            $conn = $this->ssh->connect($app);
            $log->addStep("SSH établi vers {$app->getHostAddress()}");

            match($action) {
                RemediationAction::TOMCAT_RESTART  => $this->tomcatRestart($conn, $app, $log),
                RemediationAction::TOMCAT_STOP     => $this->tomcatStop($conn, $app, $log),
                RemediationAction::TOMCAT_START    => $this->tomcatStart($conn, $app, $log),
                RemediationAction::DB_RESTART      => $this->dbRestart($conn, $app, $log),
                RemediationAction::DB_STOP         => $this->dbStop($conn, $app, $log),
                RemediationAction::DB_START        => $this->dbStart($conn, $app, $log),
                RemediationAction::DB_BACKUP       => $this->dbBackup($conn, $app, $log),
                RemediationAction::DB_REPAIR       => $this->dbRepairOrRestore($conn, $app, $log),
                RemediationAction::DB_RESTORE      => $this->dbRestore($conn, $app, $log),
                RemediationAction::MEMORY_FREE     => $this->freeMemory($conn, $app, $log),
                RemediationAction::DISK_FREE       => $this->freeDisk($conn, $app, $log),
                RemediationAction::CACHE_CLEAR     => $this->clearCaches($conn, $app, $log),
                RemediationAction::SERVER_REBOOT   => $this->serverReboot($conn, $app, $log, $extraParams),
            };

            $log->setSuccess(true);
            $log->addStep("Action terminée avec succès.");

            if ($policy) {
                $policy->recordExecution();
                $this->em->flush();
            }

        } catch (\Throwable $e) {
            $this->logger->error("Remédiation échouée [{$app->getName()}]: {$e->getMessage()}");
            $log->setSuccess(false);
            $log->setErrorMessage($e->getMessage());
            $log->addStep("ERREUR: {$e->getMessage()}");
        }

        $log->setDurationSeconds((int)round(microtime(true) - $startTime));
        $log->setSummary($log->buildSummary());

        $this->logRepository->save($log, true);
        $this->notification->sendRemediationSummary($log);

        // Enregistrer dans le Knowledge Graph si activé
        $company = $app->getEnvironment()->getCompany();
        if ($company->isKgEnabled()) {
            $this->kg->recordRemediation($log, $company);
        }

        return $log;
    }

    // ─── Tomcat ───────────────────────────────────────────────────────

    private function tomcatRestart(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getTomcatConfig()['service_name'] ?? 'tomcat9';
        $log->addStep("Arrêt de {$svc}...");
        $conn->sudo("systemctl stop {$svc}");
        sleep(3);
        $log->addStep("Démarrage de {$svc}...");
        $conn->sudo("systemctl start {$svc}");
        sleep(5);
        $status = trim($conn->sudo("systemctl is-active {$svc}", true));
        if ($status !== 'active') {
            throw new \RuntimeException("Tomcat non démarré après restart. Statut: {$status}");
        }
        $log->addStep("Tomcat redémarré et actif.");
    }

    private function tomcatStop(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getTomcatConfig()['service_name'] ?? 'tomcat9';
        if (trim($conn->sudo("systemctl is-active {$svc}", true)) !== 'active') {
            $log->addStep("Tomcat déjà arrêté.");
            return;
        }
        $log->addStep("Arrêt de Tomcat ({$svc})...");
        $conn->sudo("systemctl stop {$svc}");
        $log->addStep("Tomcat arrêté.");
    }

    private function tomcatStart(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getTomcatConfig()['service_name'] ?? 'tomcat9';
        if (trim($conn->sudo("systemctl is-active {$svc}", true)) === 'active') {
            $log->addStep("Tomcat déjà en cours d'exécution.");
            return;
        }
        $log->addStep("Démarrage de Tomcat ({$svc})...");
        $conn->sudo("systemctl start {$svc}");
        sleep(5);
        $status = trim($conn->sudo("systemctl is-active {$svc}", true));
        if ($status !== 'active') {
            throw new \RuntimeException("Tomcat non démarré. Statut: {$status}");
        }
        $log->addStep("Tomcat démarré avec succès.");
    }

    // ─── Base de données ──────────────────────────────────────────────

    private function dbRestart(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getDbConfig()['service_name'] ?? $app->getDbType()?->getDefaultServiceName() ?? 'postgresql';
        $log->addStep("Arrêt BDD ({$svc})...");
        $conn->sudo("systemctl stop {$svc}");
        sleep(5);
        $log->addStep("Démarrage BDD ({$svc})...");
        $conn->sudo("systemctl start {$svc}");
        sleep(8);
        $status = trim($conn->sudo("systemctl is-active {$svc}", true));
        if ($status !== 'active') {
            throw new \RuntimeException("BDD non démarrée. Statut: {$status}");
        }
        $log->addStep("Base de données redémarrée.");
    }

    private function dbStop(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getDbConfig()['service_name'] ?? $app->getDbType()?->getDefaultServiceName() ?? 'postgresql';
        if (trim($conn->sudo("systemctl is-active {$svc}", true)) !== 'active') {
            $log->addStep("BDD déjà arrêtée.");
            return;
        }
        $log->addStep("Arrêt de la BDD ({$svc})...");
        $conn->sudo("systemctl stop {$svc}");
        $log->addStep("BDD arrêtée.");
    }

    private function dbStart(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $svc = $app->getDbConfig()['service_name'] ?? $app->getDbType()?->getDefaultServiceName() ?? 'postgresql';
        if (trim($conn->sudo("systemctl is-active {$svc}", true)) === 'active') {
            $log->addStep("BDD déjà en cours d'exécution.");
            return;
        }
        $log->addStep("Démarrage de la BDD ({$svc})...");
        $conn->sudo("systemctl start {$svc}");
        sleep(8);
        $status = trim($conn->sudo("systemctl is-active {$svc}", true));
        if ($status !== 'active') {
            throw new \RuntimeException("BDD non démarrée. Statut: {$status}");
        }
        $log->addStep("BDD démarrée.");
    }

    private function dbBackup(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $cfg       = $app->getDbConfig();
        $backupDir = $cfg['backup_dir'] ?? '/var/backups/db';
        $dbType    = $app->getDbType();

        if (!$dbType) {
            throw new \RuntimeException("Type de BDD non configuré pour {$app->getName()}");
        }

        $log->addStep("Préparation du répertoire {$backupDir}...");
        $conn->sudo("mkdir -p {$backupDir}");

        $cmd = $dbType->getBackupCommand($cfg);
        $log->addStep("Lancement de la sauvegarde {$dbType->getLabel()}...");
        $output = $conn->sudo($cmd);
        $log->addStep("Sauvegarde terminée. " . substr($output, 0, 150));

        // Nettoyage des anciennes sauvegardes selon la rétention
        $retention = $cfg['backup_retention_days'] ?? 7;
        $conn->sudo("find {$backupDir} -mtime +{$retention} \\( -name '*.bak' -o -name '*.sql.gz' \\) -delete 2>/dev/null || true", true);
        $log->addStep("Anciennes sauvegardes (>{$retention}j) supprimées.");
    }

    private function dbRepairOrRestore(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $cfg       = $app->getDbConfig();
        $dbType    = $app->getDbType();

        if (!$dbType) throw new \RuntimeException("Type de BDD non configuré.");

        $log->addStep("Tentative de réparation...");
        try {
            $output = $conn->sudo($dbType->getRepairCommand($cfg));

            // Vérification du succès de la réparation
            $hasErrors = stripos($output, 'error') !== false
                || stripos($output, 'ora-') !== false
                || stripos($output, 'failed') !== false;
            if ($hasErrors) {
                throw new \RuntimeException("Réparation signale des erreurs: " . substr($output, 0, 200));
            }
            $log->addStep("Réparation réussie.");
        } catch (\RuntimeException $e) {
            $log->addStep("Réparation échouée ({$e->getMessage()}). Restauration depuis sauvegarde...");
            $this->dbRestore($conn, $app, $log);
        }
    }

    private function dbRestore(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $cfg       = $app->getDbConfig();
        $backupDir = $cfg['backup_dir'] ?? '/var/backups/db';

        // Trouver la sauvegarde la plus récente
        $dbType    = $app->getDbType();

        $lastBackup = trim($conn->exec(
            "ls -t {$backupDir}/*.bak {$backupDir}/*.sql.gz 2>/dev/null | head -1",
            true
        ));
        if (empty($lastBackup)) {
            throw new \RuntimeException("Aucune sauvegarde disponible dans {$backupDir}");
        }

        $log->addStep("Restauration depuis: {$lastBackup}");
        $this->dbStop($conn, $app, $log);

        // Restauration selon le type de BDD
        match($dbType?->value) {
            'oracle' => $conn->sudo(sprintf(
                'ORACLE_HOME=%s ORACLE_SID=%s rman target / <<\'EOF\'%sstartup mount;restore database;recover database;alter database open resetlogs;%sEOF',
                $cfg['oracle_home'] ?? '/opt/oracle/product/19c/dbhome_1',
                $cfg['oracle_sid']  ?? 'ORCL',
                "\n", "\n"
            )),
            'postgresql' => $conn->sudo("gunzip -c {$lastBackup} | psql postgres"),
            'mysql', 'mariadb' => $conn->sudo("gunzip -c {$lastBackup} | mysql"),
            default => throw new \RuntimeException("Restauration non supportée pour ce type de BDD."),
        };

        $log->addStep("Restauration terminée.");
        $this->dbStart($conn, $app, $log);
    }

    // ─── Système ──────────────────────────────────────────────────────

    private function freeMemory(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        // Libération du cache page
        $before = trim($conn->exec("free -m | grep Mem | awk '{print \$3}'"));
        $log->addStep("Mémoire utilisée avant: {$before} MB");

        $log->addStep("Synchronisation disques (sync)...");
        $conn->sudo("sync");

        $log->addStep("Libération des caches page, dentries, inodes...");
        $conn->sudo("echo 3 > /proc/sys/vm/drop_caches");

        // Libération swap si > 50% utilisé
        $swapUsed  = (int)trim($conn->exec("free -m | grep Swap | awk '{print \$3}'"));
        $swapTotal = (int)trim($conn->exec("free -m | grep Swap | awk '{print \$2}'"));
        if ($swapTotal > 0 && ($swapUsed / $swapTotal) > 0.5) {
            $log->addStep("Recycle du swap ({$swapUsed}/{$swapTotal} MB utilisé)...");
            $conn->sudo("swapoff -a && swapon -a");
        }

        $after  = trim($conn->exec("free -m | grep Mem | awk '{print \$3}'"));
        $freed  = (int)$before - (int)$after;
        $log->addStep("Libéré: {$freed} MB (avant: {$before} MB → après: {$after} MB)");
    }

    private function freeDisk(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        $cfg        = $app->getDbConfig();
        $backupDir  = $cfg['backup_dir'] ?? '/var/backups';
        $retention  = $cfg['backup_retention_days'] ?? 7;

        // Lister les fichiers à supprimer
        $log->addStep("Recherche des fichiers > {$retention}j dans {$backupDir}...");
        $files = $conn->exec(
            "find {$backupDir} -type f -mtime +{$retention} \\( -name '*.bak' -o -name '*.sql.gz' -o -name '*.tar.gz' \\) 2>/dev/null",
            true
        );

        if (trim($files) === '') {
            $log->addStep("Aucun fichier à supprimer.");
        } else {
            $count   = substr_count($files, "\n") + 1;
            $sizeBef = trim($conn->exec("du -sm {$backupDir} | awk '{print \$1}'", true));
            $conn->sudo("find {$backupDir} -type f -mtime +{$retention} \\( -name '*.bak' -o -name '*.sql.gz' -o -name '*.tar.gz' \\) -delete 2>/dev/null || true");
            $sizeAft = trim($conn->exec("du -sm {$backupDir} | awk '{print \$1}'", true));
            $freed   = (int)$sizeBef - (int)$sizeAft;
            $log->addStep("{$count} fichier(s) supprimé(s), ~{$freed} MB libérés.");
        }

        // Nettoyage des logs Tomcat de plus de 30 jours
        $logsDir = $app->getTomcatConfig()['logs_dir'] ?? '/opt/tomcat/logs';
        $logCount = trim($conn->exec("find {$logsDir} -name '*.log' -mtime +30 2>/dev/null | wc -l", true));
        if ((int)$logCount > 0) {
            $conn->sudo("find {$logsDir} -name '*.log' -mtime +30 -delete 2>/dev/null || true");
            $log->addStep("{$logCount} ancien(s) fichier(s) log Tomcat supprimé(s).");
        }

        // Nettoyage des fichiers temporaires /tmp
        $conn->sudo("find /tmp -mtime +7 -delete 2>/dev/null || true");
        $log->addStep("Fichiers /tmp > 7j nettoyés.");

        // Affichage de l'espace disque final
        $diskInfo = trim($conn->exec("df -h / | tail -1 | awk '{print \$4\" disponible sur \"\$2}'"));
        $log->addStep("Disque disponible: {$diskInfo}");
    }

    private function clearCaches(SshConnection $conn, Application $app, RemediationLog $log): void
    {
        // Cache Tomcat (work directory)
        $tomcatCfg = $app->getTomcatConfig();
        $workDir   = str_replace('webapps', 'work', $tomcatCfg['webapps_dir'] ?? '/opt/tomcat/webapps');

        $log->addStep("Nettoyage du répertoire work Tomcat ({$workDir})...");
        $conn->sudo("find {$workDir} -type f \\( -name '*.class' -o -name '*.java' \\) -delete 2>/dev/null || true");

        // Cache système
        $log->addStep("Libération cache système...");
        $conn->sudo("sync && echo 1 > /proc/sys/vm/drop_caches");

        // Cache Oracle si applicable (shared pool + buffer cache)
        if ($app->getDbType()?->value === 'oracle') {
            $cfg  = $app->getDbConfig();
            $oh   = $cfg['oracle_home'] ?? '/opt/oracle/product/19c/dbhome_1';
            $sid  = $cfg['oracle_sid']  ?? 'ORCL';
            $sql  = "ORACLE_HOME={$oh} ORACLE_SID={$sid} sqlplus -S / as sysdba <<'EOF'\nALTER SYSTEM FLUSH SHARED_POOL;\nALTER SYSTEM FLUSH BUFFER_CACHE;\nEXIT;\nEOF";
            $conn->sudo($sql, true);
            $log->addStep("Cache Oracle (shared pool + buffer cache) vidé.");
        }

        $log->addStep("Nettoyage des caches terminé.");
    }

    private function serverReboot(SshConnection $conn, Application $app, RemediationLog $log, array $params): void
    {
        $uptimeSec  = (int)trim($conn->exec("awk '{print int(\$1)}' /proc/uptime"));
        $uptimeH    = round($uptimeSec / 3600, 1);
        $threshold  = $app->getUptimeRestartThresholdHours();
        $schedules  = $app->getUptimeRestartSchedule();

        $log->addStep("Uptime actuel: {$uptimeH}h");

        // Vérification du seuil
        if ($threshold !== null && $uptimeH < $threshold) {
            $log->addStep("Redémarrage annulé: uptime {$uptimeH}h < seuil {$threshold}h.");
            return;
        }

        // Vérification de la fenêtre horaire
        if (!empty($schedules) && !($params['force'] ?? false)) {
            $now       = new \DateTimeImmutable();
            $inWindow  = false;
            foreach ($schedules as $window) {
                $wStart = \DateTimeImmutable::createFromFormat('H:i', $window);
                $wEnd   = $wStart->modify('+30 minutes');
                if ($now >= $wStart && $now <= $wEnd) {
                    $inWindow = true;
                    break;
                }
            }
            if (!$inWindow) {
                $next = $schedules[0];
                $conn->sudo("echo 'shutdown -r now' | at {$next} 2>/dev/null || true", true);
                $log->addStep("Hors fenêtre autorisée. Planifié pour {$next}.");
                return;
            }
        }

        // Sauvegarde BDD préventive avant reboot
        $log->addStep("Sauvegarde BDD préventive avant reboot...");
        try {
            $this->dbBackup($conn, $app, $log);
        } catch (\Throwable $e) {
            $log->addStep("Avertissement sauvegarde: {$e->getMessage()}");
        }

        $log->addStep("Redémarrage du serveur dans 1 minute...");
        $conn->sudo("shutdown -r +1 'Redémarrage obstack'");
    }
}
