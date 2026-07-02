<?php

namespace App\Tests\Service;

use App\Service\PasswordEncryptionService;
use PHPUnit\Framework\TestCase;

class PasswordEncryptionServiceTest extends TestCase
{
    private PasswordEncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordEncryptionService(
            'test-encryption-key-that-is-secure-enough-for-testing',
        );
    }

    public function testEncryptionAndDecryption(): void
    {
        $password = 'MySecurePassword123!';

        // Encrypt
        $encrypted = $this->service->encrypt($password);

        // Encrypted should be different from plaintext
        $this->assertNotEquals($password, $encrypted);

        // Decrypt
        $decrypted = $this->service->decrypt($encrypted);

        // Decrypted should match original
        $this->assertEquals($password, $decrypted);
    }

    public function testDifferentEncryptionsForSamePassword(): void
    {
        $password = 'SamePassword123!';

        $encrypted1 = $this->service->encrypt($password);
        $encrypted2 = $this->service->encrypt($password);

        // Different encryption due to random IV
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to same password
        $this->assertEquals($password, $this->service->decrypt($encrypted1));
        $this->assertEquals($password, $this->service->decrypt($encrypted2));
    }

    public function testHashingAndVerification(): void
    {
        $password = 'PasswordToHash123!';

        $hash = $this->service->hash($password);

        // Hash should not be same as password
        $this->assertNotEquals($password, $hash);

        // Verification should work
        $this->assertTrue($this->service->verify($password, $hash));

        // Wrong password should fail
        $this->assertFalse($this->service->verify('WrongPassword', $hash));
    }

    public function testInvalidBase64Handling(): void
    {
        $this->expectException(\Exception::class);
        $this->service->decrypt('invalid@#$%base64!!!');
    }
}
