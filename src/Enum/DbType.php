<?php
namespace App\Enum;

enum DbType: string {
    case ORACLE     = 'oracle';
    case POSTGRESQL = 'postgresql';
    case MYSQL      = 'mysql';
    case MARIADB    = 'mariadb';
    case MONGODB    = 'mongodb';
    case MSSQL      = 'mssql';

    public function getLabel(): string {
        return match($this) {
            self::ORACLE     => 'Oracle Database',
            self::POSTGRESQL => 'PostgreSQL',
            self::MYSQL      => 'MySQL',
            self::MARIADB    => 'MariaDB',
            self::MONGODB    => 'MongoDB',
            self::MSSQL      => 'SQL Server',
        };
    }
    public function getDefaultServiceName(): string {
        return match($this) {
            self::ORACLE     => 'oracle',
            self::POSTGRESQL => 'postgresql',
            self::MYSQL      => 'mysql',
            self::MARIADB    => 'mariadb',
            self::MONGODB    => 'mongod',
            self::MSSQL      => 'mssql-server',
        };
    }
    public function getDefaultPort(): int {
        return match($this) {
            self::ORACLE     => 1521,
            self::POSTGRESQL => 5432,
            self::MYSQL, self::MARIADB => 3306,
            self::MONGODB    => 27017,
            self::MSSQL      => 1433,
        };
    }
    public function getBackupCommand(array $cfg): string {
        $dir = $cfg['backup_dir'] ?? '/var/backups/db';
        $ts  = '$(date +%Y%m%d_%H%M%S)';
        return match($this) {
            self::ORACLE => sprintf(
                'ORACLE_HOME=%s ORACLE_SID=%s rman target / <<\'EOF\'%srun{backup database format \'%s/db_%%T_%%U.bak\';}%sEOF',
                $cfg['oracle_home'] ?? '/opt/oracle/product/19c/dbhome_1',
                $cfg['oracle_sid']  ?? 'ORCL',
                "\n", $dir, "\n"
            ),
            self::POSTGRESQL => "pg_dumpall | gzip > {$dir}/pg_{$ts}.sql.gz",
            self::MYSQL      => "mysqldump --all-databases | gzip > {$dir}/mysql_{$ts}.sql.gz",
            self::MARIADB    => "mariadb-dump --all-databases | gzip > {$dir}/mariadb_{$ts}.sql.gz",
            self::MONGODB    => "mongodump --out {$dir}/mongo_{$ts}/",
            default          => "echo 'Backup not supported for this DB type'",
        };
    }
    public function getRepairCommand(array $cfg): string {
        return match($this) {
            self::ORACLE => sprintf(
                'ORACLE_HOME=%s ORACLE_SID=%s rman target / <<\'EOF\'%srecover database;%sEOF',
                $cfg['oracle_home'] ?? '/opt/oracle/product/19c/dbhome_1',
                $cfg['oracle_sid']  ?? 'ORCL',
                "\n", "\n"
            ),
            self::MYSQL, self::MARIADB => 'mysqlcheck --all-databases --auto-repair -u root',
            self::POSTGRESQL           => 'pg_dumpall > /dev/null && echo OK',
            default                    => "echo 'Repair not supported'",
        };
    }
}
