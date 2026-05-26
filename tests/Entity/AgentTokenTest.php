<?php
namespace App\Tests\Entity;

use App\Entity\AgentToken;
use App\Entity\Environment;
use PHPUnit\Framework\TestCase;

class AgentTokenTest extends TestCase
{
    public function testTokenGeneration(): void
    {
        $token = new AgentToken();
        $token->setName('Test Token');

        $environment = new Environment();
        $environment->setName('Production');
        $token->setEnvironment($environment);

        $this->assertNotNull($token->getToken());
        $this->assertEquals(64, strlen($token->getToken())); // bin2hex(random_bytes(32)) = 64 chars
    }

    public function testRegenerateToken(): void
    {
        $token = new AgentToken();
        $oldToken = $token->getToken();
        $token->regenerateToken();
        $newToken = $token->getToken();

        $this->assertNotEquals($oldToken, $newToken);
    }

    public function testIsValid(): void
    {
        $token = new AgentToken();
        $token->setName('Test Token');
        $token->setIsActive(true);

        $this->assertTrue($token->isValid());

        $token->setIsActive(false);
        $this->assertFalse($token->isValid());

        $token->setIsActive(true);
        $token->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->assertFalse($token->isValid());
    }

    public function testRecordHeartbeat(): void
    {
        $token = new AgentToken();
        $token->recordHeartbeat('192.168.1.1', 'server1');

        $this->assertEquals('192.168.1.1', $token->getDetectedIp());
        $this->assertEquals('server1', $token->getDetectedHostname());
        $this->assertNotNull($token->getLastHeartbeatAt());
    }

    public function testRevoke(): void
    {
        $token = new AgentToken();
        $token->setIsActive(true);
        $token->revoke();

        $this->assertFalse($token->isActive());
        $this->assertNotNull($token->getExpiresAt());
    }

    public function testLifecycleCallbacks(): void
    {
        $token = new AgentToken();
        $this->assertNotNull($token->getCreatedAt());

        // Simuler une mise à jour
        $token->setName('Updated Token');
        // En réalité, le PreUpdate est appelé par Doctrine, mais on peut vérifier que updatedAt est null initialement
        $this->assertNull($token->getUpdatedAt());
    }
}
