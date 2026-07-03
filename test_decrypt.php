<?php
require_once 'vendor/autoload.php';

use Symfony\Component\DotEnv\DotEnv;

// Charger les variables d'env
$dotenv = new DotEnv();
$dotenv->loadEnv('.env');

$encryptionKey = $_ENV['APP_ENCRYPTION_KEY'] ?? '';
echo "🔑 Clé de chiffrement: " . substr($encryptionKey, 0, 16) . "...\n\n";

// Mot de passe chiffré depuis la base
$encryptedPassword = 'F+xgbNPh9Nim+/TtLKBi6rGU4433PnbjVLxQ9BJKelk=';
echo "🔐 Mot de passe chiffré: $encryptedPassword\n\n";

// Déchiffrer
try {
    $decrypted = openssl_decrypt(
        base64_decode($encryptedPassword),
        'AES-256-CBC',
        hash('sha256', $encryptionKey, true),
        0,
        substr(hash('sha256', $encryptionKey, true), 0, 16)
    );
    
    echo "✅ Mot de passe déchiffré: $decrypted\n";
} catch (Exception $e) {
    echo "❌ Erreur de déchiffrement: " . $e->getMessage() . "\n";
}
