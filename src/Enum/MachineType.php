<?php
namespace App\Enum;

enum MachineType: string {
    case PHYSICAL    = 'physical';
    case VM_VMWARE   = 'vm_vmware';
    case VM_KVM      = 'vm_kvm';
    case VM_HYPER_V  = 'vm_hyperv';
    case VM_XENPV    = 'vm_xen';
    case CONTAINER   = 'container';
    case CLOUD_AWS   = 'cloud_aws';
    case CLOUD_GCP   = 'cloud_gcp';
    case CLOUD_AZURE = 'cloud_azure';
    case UNKNOWN     = 'unknown';

    public function getLabel(): string {
        return match($this) {
            self::PHYSICAL    => 'Machine physique (Bare Metal)',
            self::VM_VMWARE   => 'Machine Virtuelle VMware',
            self::VM_KVM      => 'Machine Virtuelle KVM/QEMU',
            self::VM_HYPER_V  => 'Machine Virtuelle Hyper-V',
            self::VM_XENPV    => 'Machine Virtuelle Xen',
            self::CONTAINER   => 'Conteneur (LXC/Docker)',
            self::CLOUD_AWS   => 'Instance Cloud AWS (EC2)',
            self::CLOUD_GCP   => 'Instance Cloud GCP',
            self::CLOUD_AZURE => 'Instance Cloud Azure',
            self::UNKNOWN     => 'Inconnu',
        };
    }

    public function isVirtual(): bool {
        return $this !== self::PHYSICAL;
    }

    public function getIcon(): string {
        return match($this) {
            self::PHYSICAL             => 'ti-server-cog',
            self::VM_VMWARE, self::VM_KVM,
            self::VM_HYPER_V, self::VM_XENPV => 'ti-devices-pc',
            self::CONTAINER            => 'ti-box',
            self::CLOUD_AWS, self::CLOUD_GCP,
            self::CLOUD_AZURE          => 'ti-cloud-computing',
            default                    => 'ti-server',
        };
    }
}
