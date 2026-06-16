<?php

namespace App\Tests\Service;

use App\Service\AgentInstallScriptGenerator;
use PHPUnit\Framework\TestCase;

class AgentInstallScriptGeneratorTest extends TestCase
{
    private AgentInstallScriptGenerator $generator;

    protected function setUp(): void
    {
        // Skip tests if GPG is not available or cannot properly validate keys
        $gpgVersion = shell_exec('gpg --version 2>&1');
        if ($gpgVersion === null || strpos($gpgVersion, 'GnuPG') === false) {
            self::markTestSkipped('GPG must be properly configured to run fingerprint validation tests.');
        }

        $this->generator = new AgentInstallScriptGenerator('http://localhost', 'v1', '', '', '');
    }

    public function testValidatePublicKeyUrlAcceptsMatchingFingerprint(): void
    {
        $publicKeyPath = $this->getTestPublicKeyPath();
        $fingerprint = $this->getPublicKeyFingerprint($publicKeyPath);

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $method->invoke($this->generator, $fileUrl, $fingerprint);

        $this->assertTrue(true, 'La validation du fingerprint a réussi pour une clé publique valide.');
    }

    public function testValidatePublicKeyUrlRejectsNonMatchingFingerprint(): void
    {
        $publicKeyPath = $this->getTestPublicKeyPath();

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint de clé publique incompatible');

        $method->invoke($this->generator, $fileUrl, '0000000000000000000000000000000000000000');
    }

    public function testValidatePublicKeyUrlRejectsNonHexadecimalFingerprint(): void
    {
        $publicKeyPath = $this->getTestPublicKeyPath();

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint de clé publique incompatible');

        $method->invoke($this->generator, $fileUrl, 'ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ');
    }

    public function testValidatePublicKeyUrlRejectsTooShortFingerprint(): void
    {
        $publicKeyPath = $this->getTestPublicKeyPath();

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint de clé publique incompatible');

        $method->invoke($this->generator, $fileUrl, 'ABC123');
    }

    public function testValidatePublicKeyUrlThrowsWhenDownloadFails(): void
    {
        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $missingPath = sys_get_temp_dir() . '/obstack_missing_pubkey_' . bin2hex(random_bytes(6)) . '.asc';
        $fileUrl = $this->pathToFileUrl($missingPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Impossible de télécharger la clé publique');

        $method->invoke($this->generator, $fileUrl, '0000000000000000000000000000000000000000');
    }

    public function testValidatePublicKeyUrlThrowsForMalformedPublicKey(): void
    {
        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $tmpFile = tempnam(sys_get_temp_dir(), 'obstack_malformed_pubkey_');
        file_put_contents($tmpFile, "-----BEGIN PGP PUBLIC KEY BLOCK-----\nmalformed-content\n-----END PGP PUBLIC KEY BLOCK-----\n");

        $fileUrl = $this->pathToFileUrl($tmpFile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Impossible de calculer le fingerprint de la clé publique téléchargée');

        try {
            $method->invoke($this->generator, $fileUrl, '0000000000000000000000000000000000000000');
        } finally {
            @unlink($tmpFile);
        }
    }

    private function getTestPublicKeyPath(): string
    {
        $fixturePath = __DIR__ . '/../fixtures/test_pubkey.asc';
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('Test public key fixture not found.');
        }
        return $fixturePath;
    }

    private function getPublicKeyFingerprint(string $publicKeyPath): string
    {
        $command = sprintf(
            'gpg --batch --with-colons --import-options show-only --import %s 2>/dev/null',
            escapeshellarg($publicKeyPath)
        );

        $output = shell_exec($command);
        $this->assertIsString($output, 'La sortie GPG doit être un string.');

        if (!preg_match('/^fpr:[^:]*:([0-9A-Fa-f]{32,})/m', $output, $matches)) {
            $this->fail('Impossible de lire le fingerprint de la clé publique temporaire.');
        }

        return strtoupper($matches[1]);
    }

    private function pathToFileUrl(string $path): string
    {
        return 'file://' . str_replace('%2F', '/', rawurlencode($path));
    }
}
