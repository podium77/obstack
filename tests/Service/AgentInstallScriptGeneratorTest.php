<?php

namespace App\Tests\Service;

use App\Service\AgentInstallScriptGenerator;
use PHPUnit\Framework\TestCase;

class AgentInstallScriptGeneratorTest extends TestCase
{
    private AgentInstallScriptGenerator $generator;

    protected function setUp(): void
    {
        if (!
            is_string(shell_exec('command -v gpg 2>/dev/null')) ||
            trim(shell_exec('command -v gpg 2>/dev/null')) === ''
        ) {
            self::markTestSkipped('GPG is required for fingerprint validation tests.');
        }

        $this->generator = new AgentInstallScriptGenerator('http://localhost', 'v1', '', '', '');
    }

    public function testValidatePublicKeyUrlAcceptsMatchingFingerprint(): void
    {
        $publicKeyPath = $this->createTemporaryPublicKey();
        $fingerprint = $this->getPublicKeyFingerprint($publicKeyPath);

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $method->invoke($this->generator, $fileUrl, $fingerprint);

        $this->assertTrue(true, 'La validation du fingerprint a réussi pour une clé publique valide.');
    }

    public function testValidatePublicKeyUrlRejectsNonMatchingFingerprint(): void
    {
        $publicKeyPath = $this->createTemporaryPublicKey();

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint de clé publique incompatible');

        $method->invoke($this->generator, $fileUrl, '0000000000000000000000000000000000000000');
    }

    public function testValidatePublicKeyUrlRejectsNonHexadecimalFingerprint(): void
    {
        $publicKeyPath = $this->createTemporaryPublicKey();

        $method = new \ReflectionMethod(AgentInstallScriptGenerator::class, 'validatePublicKeyUrl');
        $method->setAccessible(true);

        $fileUrl = $this->pathToFileUrl($publicKeyPath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fingerprint de clé publique incompatible');

        $method->invoke($this->generator, $fileUrl, 'ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ');
    }

    public function testValidatePublicKeyUrlRejectsTooShortFingerprint(): void
    {
        $publicKeyPath = $this->createTemporaryPublicKey();

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

    private function createTemporaryPublicKey(): string
    {
        $tmpDir = sys_get_temp_dir() . '/obstack_test_pubkey_' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            $this->fail('Impossible de créer le répertoire temporaire pour la clé publique.');
        }

        putenv('GNUPGHOME=' . $tmpDir);
        $fullName = 'Test User <test@example.com>';
        $command = sprintf(
            'gpg --batch --quick-generate-key %s rsa2048 sign 0 2>/dev/null',
            escapeshellarg($fullName)
        );

        $output = null;
        $returnVar = null;
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            $this->fail('Impossible de générer la clé GPG temporaire pour les tests.');
        }

        $pubKeyPath = $tmpDir . '/pubkey.asc';
        $exportCommand = sprintf(
            'gpg --batch --yes --armor --export %s > %s 2>/dev/null',
            escapeshellarg($fullName),
            escapeshellarg($pubKeyPath)
        );

        exec($exportCommand, $output, $returnVar);
        if ($returnVar !== 0 || !is_file($pubKeyPath)) {
            $this->fail('Impossible d’exporter la clé publique GPG temporaire.');
        }

        return $pubKeyPath;
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
