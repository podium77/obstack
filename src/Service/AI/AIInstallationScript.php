<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;

/**
 * AIInstallationScript
 * 
 * Génère les scripts de déploiement et configuration d'Ollama sur Debian Trixie Core
 * Automatise complètement le processus :
 * - Installation Ollama
 * - Configuration systemd (démarrage auto + restart on failure)
 * - Téléchargement du modèle
 * - Validation service
 */
class AIInstallationScript
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * Générer le script d'installation Ollama complet
     */
    public function generateOllamaInstallationScript(string $aiModel): string
    {
        return <<<'SCRIPT'
#!/bin/bash
set -e

# Script d'installation et configuration d'Ollama
# Debian Trixie Core - Installation complètement automatisée

echo "=== Ollama Installation & Configuration Script ==="
echo "Model: %AI_MODEL%"
echo "Timestamp: $(date -u +\%%Y-\%%m-\%%dT\%%H:\%%M:\%%SZ)"

# 1. Mise à jour système
echo "[1/6] Updating system packages..."
apt-get update -qq
apt-get install -y -qq curl wget sudo

# 2. Installation d'Ollama
echo "[2/6] Installing Ollama..."
curl -fsSL https://ollama.ai/install.sh | sh || {
    echo "ERROR: Ollama installation failed"
    exit 1
}

# 3. Créer utilisateur ollama s'il n'existe pas
echo "[3/6] Setting up ollama user..."
if ! id -u ollama > /dev/null 2>&1; then
    useradd -r -s /bin/false -d /var/lib/ollama -m ollama
fi

# 4. Créer les répertoires et configurer permissions
echo "[4/6] Setting up directories..."
mkdir -p /var/lib/ollama
mkdir -p /var/log/ollama
chown -R ollama:ollama /var/lib/ollama
chown -R ollama:ollama /var/log/ollama
chmod 755 /var/lib/ollama

# 5. Créer le fichier de configuration systemd
echo "[5/6] Configuring systemd service..."
cat > /etc/systemd/system/ollama.service << 'EOF'
[Unit]
Description=Ollama Service
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=ollama
Group=ollama
WorkingDirectory=/var/lib/ollama

# Commande de démarrage
ExecStart=/usr/local/bin/ollama serve
ExecReload=/bin/kill -HUP $MAINPID

# Politique de redémarrage automatique
Restart=always
RestartSec=10s
StartLimitInterval=600s
StartLimitBurst=3

# Limites de ressources
MemoryMax=90%
CPUAccounting=yes

# Logs
StandardOutput=journal
StandardError=journal
SyslogIdentifier=ollama

# Timeouts
TimeoutStartSec=180
TimeoutStopSec=30

# Sécurité
PrivateTmp=yes
ProtectHome=yes
ProtectSystem=strict
ReadWritePaths=/var/lib/ollama /var/log/ollama

[Install]
WantedBy=multi-user.target
EOF

# Recharger systemd
systemctl daemon-reload
systemctl enable ollama.service

# 6. Démarrer le service
echo "[6/6] Starting Ollama service..."
systemctl restart ollama

# Attendre que le service soit opérationnel
echo "Waiting for Ollama service to become ready..."
max_attempts=30
attempt=0
while [ $attempt -lt $max_attempts ]; do
    if curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
        echo "✓ Ollama service is ready"
        break
    fi
    attempt=$((attempt + 1))
    echo "  Attempt $attempt/$max_attempts..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "ERROR: Ollama service did not become ready"
    systemctl status ollama
    exit 1
fi

# 7. Télécharger le modèle
echo ""
echo "=== Downloading AI Model: %AI_MODEL% ==="
sudo -u ollama /usr/local/bin/ollama pull %AI_MODEL%

# Vérifier que le modèle est disponible
echo ""
echo "Verifying model installation..."
models=$(curl -s http://localhost:11434/api/tags | grep -o '%AI_MODEL%' | wc -l)
if [ $models -eq 0 ]; then
    echo "ERROR: Model %AI_MODEL% not found after download"
    exit 1
fi
echo "✓ Model %AI_MODEL% successfully installed"

# 8. Afficher le statut final
echo ""
echo "=== Installation Summary ==="
echo "Status: $(systemctl is-active ollama)"
echo "API URL: http://localhost:11434"
echo "Enabled: $(systemctl is-enabled ollama)"
echo ""
echo "✓ Installation completed successfully!"
echo "✓ Ollama will automatically start on boot"
echo "✓ Service will auto-restart if it crashes"
echo ""
echo "Available models:"
curl -s http://localhost:11434/api/tags | grep -o '"name":"[^"]*"' | cut -d'"' -f4

exit 0
SCRIPT;

        // Remplacer le placeholder du modèle
        return str_replace('%AI_MODEL%', $aiModel, $this->cleanScript($script));
    }

    /**
     * Générer un script de vérification du service
     */
    public function generateHealthCheckScript(): string
    {
        return <<<'SCRIPT'
#!/bin/bash

echo "=== Ollama Health Check ==="

# Vérifier que le service est actif
if ! systemctl is-active --quiet ollama; then
    echo "ERROR: Ollama service is not running"
    systemctl status ollama
    exit 1
fi

# Vérifier que l'API répond
if ! curl -s http://localhost:11434/api/tags > /dev/null 2>&1; then
    echo "ERROR: Ollama API is not responding"
    exit 1
fi

# Lister les modèles disponibles
echo "Available models:"
curl -s http://localhost:11434/api/tags | grep -o '"name":"[^"]*"' | cut -d'"' -f4

# Vérifier les logs récents
echo ""
echo "Recent logs (last 5 lines):"
journalctl -u ollama -n 5 --no-pager

echo ""
echo "✓ Health check passed"
exit 0
SCRIPT;
    }

    /**
     * Générer un script de nettoyage (uninstall)
     */
    public function generateCleanupScript(): string
    {
        return <<<'SCRIPT'
#!/bin/bash

echo "=== Ollama Cleanup Script ==="

# Arrêter le service
echo "Stopping Ollama service..."
systemctl stop ollama
systemctl disable ollama

# Supprimer le fichier de service
echo "Removing systemd service..."
rm -f /etc/systemd/system/ollama.service
systemctl daemon-reload

# Désinstaller Ollama
echo "Uninstalling Ollama..."
rm -f /usr/local/bin/ollama

# Supprimer l'utilisateur et les fichiers
echo "Removing ollama user and data..."
userdel -r ollama 2>/dev/null || true
rm -rf /var/lib/ollama
rm -rf /var/log/ollama

echo "✓ Cleanup completed"
exit 0
SCRIPT;
    }

    /**
     * Valider un script shell
     */
    public function validateScript(string $script): bool
    {
        // Vérifications basiques
        $checks = [
            'set -e' => 'Error handling enabled',
            'echo' => 'Has output statements',
            'systemctl' => 'Uses systemd',
            '/etc/systemd/system/ollama.service' => 'Creates service file',
        ];

        $issues = [];
        foreach ($checks as $pattern => $description) {
            if (strpos($script, $pattern) === false) {
                $issues[] = "Missing: $description ($pattern)";
            }
        }

        if (!empty($issues)) {
            $this->logger->warning('Script validation issues', $issues);
            return false;
        }

        return true;
    }

    /**
     * Nettoyer le script (trim, remove extra spaces)
     */
    private function cleanScript(string $script): string
    {
        return trim($script);
    }
}
