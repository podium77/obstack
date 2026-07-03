<?php

namespace App\Service\AI\Hypervisor;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Interface pour la connexion à différents hyperviseurs
 * 
 * Chaque implémentation gère la communication avec un fournisseur 
 * d'infrastructure spécifique (Proxmox, VMware, Nutanix).
 */
interface HypervisorConnectorInterface
{
    /**
     * Tester la connexion à l'hyperviseur
     */
    public function testConnection(): bool;

    /**
     * Créer une machine virtuelle
     */
    public function createVirtualMachine(array $config): string; // Retourne l'ID de la VM

    /**
     * Récupérer l'IP de la machine virtuelle
     */
    public function getVirtualMachineIP(string $vmId): ?string;

    /**
     * Exécuter une commande SSH sur la VM
     */
    public function executeCommand(string $vmId, string $command): string;

    /**
     * Arrêter une machine virtuelle
     */
    public function stopVirtualMachine(string $vmId): bool;

    /**
     * Supprimer une machine virtuelle
     */
    public function deleteVirtualMachine(string $vmId): bool;

    /**
     * Récupérer les ressources disponibles (CPU, RAM, Disk)
     */
    public function getAvailableResources(): array;

    /**
     * Récupérer le statut de la connexion pour logging
     */
    public function getConnectionStatus(): array;
}
