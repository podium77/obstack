<?php
// src/Enum/NodeRole.php
namespace App\Enum;
enum NodeRole: string {
    case MASTER = 'master';
    case WORKER = 'worker';
    case ETCD   = 'etcd';
    case INGRESS = 'ingress';
    public function getLabel(): string {
        return match($this) {
            self::MASTER  => 'Control Plane (Master)',
            self::WORKER  => 'Worker Node',
            self::ETCD    => 'etcd Node',
            self::INGRESS => 'Ingress Node',
        };
    }
    public function getIcon(): string {
        return match($this) {
            self::MASTER  => 'ti-crown',
            self::WORKER  => 'ti-server',
            self::ETCD    => 'ti-database',
            self::INGRESS => 'ti-network',
        };
    }
}
