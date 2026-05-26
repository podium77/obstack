<?php
namespace App\Enum;

enum OsType: string {
    case DEBIAN     = 'debian';
    case UBUNTU     = 'ubuntu';
    case REDHAT     = 'redhat';
    case CENTOS     = 'centos';
    case ROCKYLINUX = 'rockylinux';

    public function getLabel(): string {
        return match($this) {
            self::DEBIAN     => 'Debian',
            self::UBUNTU     => 'Ubuntu',
            self::REDHAT     => 'Red Hat Enterprise Linux',
            self::CENTOS     => 'CentOS',
            self::ROCKYLINUX => 'Rocky Linux',
        };
    }
    public function getPackageManager(): string {
        return match($this) {
            self::DEBIAN, self::UBUNTU => 'apt',
            default                    => 'dnf',
        };
    }
}
