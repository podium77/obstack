<?php

namespace App\Service;

use Exception;

/**
 * Service de chiffrement/déchiffrement des mots de passe.
 * 
 * Utilise OpenSSL pour chiffrer les mots de passe des connexions de bases de données.
 * La clé de chiffrement doit être définie dans la variable d'environnement APP_ENCRYPTION_KEY.
 */
class PasswordEncryptionService
{
    private const CIPHER = 'AES-256-CBC';
    private const ALGORITHM = 'sha256';

    public function __construct(
        private string $encryptionKey,
    ) {
        if (empty($this->encryptionKey)) {
            throw new Exception('APP_ENCRYPTION_KEY environment variable is required');
        }
    }

    /**
     * Chiffre un mot de passe.
     */
    public function encrypt(string $plaintext): string
    {
        // Générer un IV aléatoire
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));

        // Chiffrer avec la clé dérivée
        $key = hash(self::ALGORITHM, $this->encryptionKey, true);
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
        );

        // Retourner IV + encrypted (encodé en base64)
        return base64_encode($iv . $encrypted);
    }

    /**
     * Déchiffre un mot de passe chiffré.
     */
    public function decrypt(string $ciphertext): string
    {
        try {
            $decoded = base64_decode($ciphertext, true);
            if ($decoded === false) {
                throw new Exception('Invalid base64 encoding');
            }

            // Extraire l'IV et le contenu chiffré
            $ivLength = openssl_cipher_iv_length(self::CIPHER);
            $iv = substr($decoded, 0, $ivLength);
            $encrypted = substr($decoded, $ivLength);

            // Déchiffrer
            $key = hash(self::ALGORITHM, $this->encryptionKey, true);
            $plaintext = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
            );

            if ($plaintext === false) {
                throw new Exception('Decryption failed');
            }

            return $plaintext;
        } catch (\Exception $e) {
            throw new Exception('Password decryption error: ' . $e->getMessage());
        }
    }

    /**
     * Hache un mot de passe pour vérification (sans déchiffrement).
     */
    public function hash(string $plaintext): string
    {
        return hash(self::ALGORITHM, $plaintext . $this->encryptionKey);
    }

    /**
     * Vérifie un mot de passe contre un hash.
     */
    public function verify(string $plaintext, string $hash): bool
    {
        return hash_equals($this->hash($plaintext), $hash);
    }
}
