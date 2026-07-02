<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for field-level encryption and decryption
 */
class EncryptionService
{
    private string $encryptionKey;
    private string $encryptionAlgorithm = 'AES-256-GCM';

    public function __construct(string $encryptionKey)
    {
        if (strlen($encryptionKey) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters');
        }
        $this->encryptionKey = $encryptionKey;
    }

    /**
     * Encrypt a value
     *
     * @param mixed $value Value to encrypt
     * @return string Encrypted value (base64-encoded)
     */
    public function encrypt(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $data = is_string($value) ? $value : json_encode($value);
        
        // Generate a random IV
        $iv = openssl_random_pseudo_bytes(16);
        
        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            hash('sha256', $this->encryptionKey, true),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Return IV + encrypted data, base64-encoded
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value
     *
     * @param string $encrypted Encrypted value (base64-encoded)
     * @return mixed Decrypted value
     */
    public function decrypt(string $encrypted): mixed
    {
        if (empty($encrypted)) {
            return null;
        }

        try {
            $data = base64_decode($encrypted, true);
            if ($data === false) {
                throw new \RuntimeException('Invalid base64 data');
            }

            // Extract IV (first 16 bytes)
            $iv = substr($data, 0, 16);
            $encrypted_data = substr($data, 16);

            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted_data,
                'AES-256-CBC',
                hash('sha256', $this->encryptionKey, true),
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($decrypted === false) {
                throw new \RuntimeException('Decryption failed');
            }

            // Try to decode as JSON
            $json_decoded = json_decode($decrypted, true);
            return $json_decoded !== null ? $json_decoded : $decrypted;
        } catch (\Exception $e) {
            throw new \RuntimeException('Decryption error: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt multiple fields in an array
     *
     * @param array<string, mixed> $data Data to encrypt
     * @param array<string> $fieldsToEncrypt Fields to encrypt
     * @return array<string, mixed> Data with encrypted fields
     */
    public function encryptFields(array $data, array $fieldsToEncrypt): array
    {
        $result = $data;
        foreach ($fieldsToEncrypt as $field) {
            if (isset($result[$field])) {
                $result[$field] = $this->encrypt($result[$field]);
                $result["{$field}_encrypted"] = true;
            }
        }
        return $result;
    }

    /**
     * Decrypt multiple fields in an array
     *
     * @param array<string, mixed> $data Data to decrypt
     * @param array<string> $fieldsToDecrypt Fields to decrypt
     * @return array<string, mixed> Data with decrypted fields
     */
    public function decryptFields(array $data, array $fieldsToDecrypt): array
    {
        $result = $data;
        foreach ($fieldsToDecrypt as $field) {
            if (isset($result[$field]) && isset($result["{$field}_encrypted"])) {
                $result[$field] = $this->decrypt((string)$result[$field]);
                unset($result["{$field}_encrypted"]);
            }
        }
        return $result;
    }

    /**
     * Get encryption metadata
     *
     * @return array<string, mixed> Metadata
     */
    public function getMetadata(): array
    {
        return [
            'algorithm' => 'AES-256-CBC',
            'keyLength' => strlen($this->encryptionKey),
            'ivLength' => 16,
            'encoding' => 'base64',
        ];
    }

    /**
     * Rotate encryption key
     *
     * @param string $oldData Encrypted data with old key
     * @param string $newKey New encryption key
     * @return string Re-encrypted data
     */
    public function rotateKey(string $oldData, string $newKey): string
    {
        // Decrypt with old key
        $decrypted = $this->decrypt($oldData);
        
        // Create new service with new key
        $newService = new self($newKey);
        
        // Re-encrypt with new key
        return $newService->encrypt($decrypted);
    }

    /**
     * Hash a value (one-way)
     *
     * @param mixed $value Value to hash
     * @return string Hashed value
     */
    public function hash(mixed $value): string
    {
        $data = is_string($value) ? $value : json_encode($value);
        return hash('sha256', $data . $this->encryptionKey);
    }

    /**
     * Verify a hash
     *
     * @param mixed $value Value to verify
     * @param string $hash Hash to compare against
     * @return bool True if hashes match
     */
    public function verifyHash(mixed $value, string $hash): bool
    {
        return hash_equals($this->hash($value), $hash);
    }
}
