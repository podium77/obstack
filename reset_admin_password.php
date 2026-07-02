<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Doctrine\ORM\EntityManager;

// Charger les variables d'environnement
$dotenv = new Dotenv();
$dotenv->loadEnv('.env');

// Créer une connexion à la base de données
$dsn = getenv('DATABASE_URL');
if (!$dsn) {
    die("DATABASE_URL non défini dans .env\n");
}

// Extraire les paramètres PostgreSQL
$dsn = str_replace('postgresql://', '', $dsn);
[$host, $rest] = explode('/', $dsn, 2);
[$db, $rest] = explode('?', $rest . '?', 2);
[$user, $pass] = explode(':', str_replace('@', '', preg_replace('/.*@/', '', $dsn)), 2);
[$user] = explode(':', $dsn, 2);

// Parser le DSN correctement
if (preg_match('|postgresql://([^:]+):([^@]+)@([^:]+):(\d+)/([^?]+)|', getenv('DATABASE_URL'), $m)) {
    [$full, $db_user, $db_pass, $db_host, $db_port, $db_name] = $m;
} else {
    die("Impossible de parser DATABASE_URL\n");
}

try {
    // Connexion à PostgreSQL
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hash du mot de passe
    $hash = '$2y$12$9UJtQFiTl6gj0DxZ.HNv.O1GDawOLaDHmXFAQ7ConRt0mNvjRcJ1W';
    
    // Mettre à jour le mot de passe de l'admin
    $stmt = $pdo->prepare('UPDATE local_users SET password = :password WHERE username = :username');
    $stmt->execute([
        ':password' => $hash,
        ':username' => 'admin'
    ]);
    
    echo "✓ Mot de passe de l'admin réinitialisé avec succès !\n";
    echo "  Identifiant : admin\n";
    echo "  Mot de passe : SecurePassword123\n";
    
} catch (Exception $e) {
    echo "✗ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
