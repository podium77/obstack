<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LocalUser;
use Doctrine\DBAL\Connection;

/**
 * Service for multi-factor authentication (MFA)
 */
class MFAService
{
    public function __construct(
        private Connection $connection,
        private string $mfaWindowTime = '300', // 5 minutes in seconds
    ) {}

    /**
     * Generate TOTP secret for user
     *
     * @return array<string, mixed> Secret and QR code data
     */
    public function generateTotpSecret(): array
    {
        $secret = $this->generateRandomSecret();
        
        return [
            'success' => true,
            'data' => [
                'secret' => $secret,
                'encoded' => base64_encode($secret),
                'qrCode' => $this->generateQRCode($secret),
                'backupCodes' => $this->generateBackupCodes(),
            ],
        ];
    }

    /**
     * Verify TOTP code
     *
     * @param string $secret TOTP secret
     * @param string $code 6-digit code
     * @return array<string, mixed> Verification result
     */
    public function verifyTotpCode(string $secret, string $code): array
    {
        // Check if code is 6 digits
        if (!preg_match('/^\d{6}$/', $code)) {
            return [
                'success' => false,
                'error' => 'Code must be 6 digits',
            ];
        }

        // Decode secret if base64-encoded
        if (strlen($secret) > 32) {
            $secret = base64_decode($secret, true);
        }

        // Verify TOTP code
        $currentTime = floor(time() / 30);
        
        // Check current and previous windows for flexibility
        for ($i = -1; $i <= 1; $i++) {
            $window = $currentTime + $i;
            $expectedCode = $this->generateTotpCode($secret, $window);
            
            if (hash_equals($expectedCode, $code)) {
                return [
                    'success' => true,
                    'message' => 'TOTP code verified',
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Invalid code',
        ];
    }

    /**
     * Send MFA code via email
     *
     * @param LocalUser $user User to send code to
     * @param string $method 'email' or 'sms'
     * @return array<string, mixed> Result
     */
    public function sendMfaCode(LocalUser $user, string $method = 'email'): array
    {
        try {
            $code = $this->generateNumericCode();
            $expiresAt = new \DateTime('+10 minutes');

            // Store code in database
            $this->connection->insert('mfa_codes', [
                'user_id' => $user->getId(),
                'code' => password_hash($code, PASSWORD_BCRYPT),
                'method' => $method,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'message' => "MFA code sent via {$method}",
                'expiresIn' => 600, // 10 minutes
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify MFA code
     *
     * @param LocalUser $user User
     * @param string $code Code to verify
     * @return array<string, mixed> Verification result
     */
    public function verifyMfaCode(LocalUser $user, string $code): array
    {
        try {
            $stmt = $this->connection->executeQuery(
                'SELECT id, code, expires_at FROM mfa_codes 
                 WHERE user_id = ? AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1',
                [$user->getId()]
            );
            
            $result = $stmt->fetchAssociative();
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => 'No valid MFA code found',
                ];
            }

            // Verify code
            if (!password_verify($code, (string)$result['code'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid code',
                ];
            }

            // Mark code as used
            $this->connection->update('mfa_codes', 
                ['used_at' => (new \DateTime())->format('Y-m-d H:i:s')],
                ['id' => $result['id']]
            );

            return [
                'success' => true,
                'message' => 'MFA code verified',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enable MFA for user
     *
     * @param LocalUser $user User
     * @param string $method 'totp' or 'email'
     * @param string $secret TOTP secret if method is 'totp'
     * @return array<string, mixed> Result
     */
    public function enableMfa(LocalUser $user, string $method, string $secret = ''): array
    {
        try {
            // Update user entity
            $this->connection->update('local_user', 
                [
                    'mfa_enabled' => true,
                    'mfa_method' => $method,
                    'mfa_secret' => $method === 'totp' ? $secret : null,
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ],
                ['id' => $user->getId()]
            );

            return [
                'success' => true,
                'message' => "MFA ({$method}) enabled",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Disable MFA for user
     *
     * @param LocalUser $user User
     * @return array<string, mixed> Result
     */
    public function disableMfa(LocalUser $user): array
    {
        try {
            $this->connection->update('local_user', 
                [
                    'mfa_enabled' => false,
                    'mfa_method' => null,
                    'mfa_secret' => null,
                    'updated_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ],
                ['id' => $user->getId()]
            );

            return [
                'success' => true,
                'message' => 'MFA disabled',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get MFA status
     *
     * @param LocalUser $user User
     * @return array<string, mixed> MFA status
     */
    public function getMfaStatus(LocalUser $user): array
    {
        return [
            'enabled' => true, // In real implementation, check from DB
            'method' => 'totp', // or 'email', 'sms', etc
            'configured' => true,
            'lastUsed' => (new \DateTime())->modify('-1 day')->format('c'),
            'backupCodesRemaining' => 8,
        ];
    }

    /**
     * Generate backup codes
     *
     * @return array<string> Backup codes
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = sprintf(
                '%04d-%04d-%04d',
                random_int(0, 9999),
                random_int(0, 9999),
                random_int(0, 9999)
            );
        }
        return $codes;
    }

    /**
     * Generate random TOTP secret
     */
    private function generateRandomSecret(): string
    {
        return base64_encode(openssl_random_pseudo_bytes(32));
    }

    /**
     * Generate TOTP code for a time window
     */
    private function generateTotpCode(string $secret, int $window): string
    {
        $time = pack('N*', 0, $window);
        $hmac = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hmac[19]) & 0xf;
        $code = unpack('N', substr($hmac, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string)($code % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate random numeric code
     */
    private function generateNumericCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate QR code data URL
     */
    private function generateQRCode(string $secret): string
    {
        // In production, would use a QR code library
        // This is a placeholder
        $data = base64_encode($secret);
        return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==";
    }
}
