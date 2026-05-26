<?php
namespace App\Enum;

enum RemediationAction: string {
    case TOMCAT_RESTART  = 'tomcat_restart';
    case TOMCAT_STOP     = 'tomcat_stop';
    case TOMCAT_START    = 'tomcat_start';
    case DB_RESTART      = 'db_restart';
    case DB_STOP         = 'db_stop';
    case DB_START        = 'db_start';
    case DB_BACKUP       = 'db_backup';
    case DB_REPAIR       = 'db_repair';
    case DB_RESTORE      = 'db_restore';
    case MEMORY_FREE     = 'memory_free';
    case DISK_FREE       = 'disk_free_old_backups';
    case CACHE_CLEAR     = 'cache_clear';
    case SERVER_REBOOT   = 'server_reboot';

    public function getLabel(): string {
        return match($this) {
            self::TOMCAT_RESTART  => 'Redémarrer Tomcat',
            self::TOMCAT_STOP     => 'Arrêter Tomcat',
            self::TOMCAT_START    => 'Démarrer Tomcat',
            self::DB_RESTART      => 'Redémarrer la BDD',
            self::DB_STOP         => 'Arrêter la BDD',
            self::DB_START        => 'Démarrer la BDD',
            self::DB_BACKUP       => 'Sauvegarder la BDD',
            self::DB_REPAIR       => 'Réparer / Restaurer la BDD',
            self::DB_RESTORE      => 'Restaurer la BDD',
            self::MEMORY_FREE     => 'Libérer la mémoire vive',
            self::DISK_FREE       => 'Libérer l\'espace disque',
            self::CACHE_CLEAR     => 'Vider les caches',
            self::SERVER_REBOOT   => 'Redémarrer le serveur',
        };
    }
    public function getIcon(): string {
        return match($this) {
            self::TOMCAT_RESTART  => 'refresh',
            self::TOMCAT_STOP     => 'player-stop',
            self::TOMCAT_START    => 'player-play',
            self::DB_RESTART      => 'database',
            self::DB_STOP         => 'database-off',
            self::DB_START        => 'database-plus',
            self::DB_BACKUP       => 'device-floppy',
            self::DB_REPAIR       => 'tool',
            self::DB_RESTORE      => 'history',
            self::MEMORY_FREE     => 'memory',
            self::DISK_FREE       => 'trash',
            self::CACHE_CLEAR     => 'eraser',
            self::SERVER_REBOOT   => 'power',
        };
    }
    public function getCategory(): string {
        return match($this) {
            self::TOMCAT_RESTART, self::TOMCAT_STOP, self::TOMCAT_START => 'Tomcat',
            self::DB_RESTART, self::DB_STOP, self::DB_START,
            self::DB_BACKUP, self::DB_REPAIR, self::DB_RESTORE           => 'Base de données',
            default                                                       => 'Système',
        };
    }
    public function isDestructive(): bool {
        return in_array($this, [self::DB_RESTORE, self::DISK_FREE, self::SERVER_REBOOT, self::DB_STOP]);
    }
    public function requiresConfirmation(): bool {
        return in_array($this, [self::DB_RESTORE, self::SERVER_REBOOT, self::DB_STOP, self::TOMCAT_STOP]);
    }
}
