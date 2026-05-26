<?php
namespace App\Tests\Controller;

use App\Entity\AgentToken;
use App\Entity\Application;
use App\Entity\Environment;
use App\Entity\Company;
use App\Repository\AgentTokenRepository;
use App\Repository\ApplicationRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private AgentTokenRepository $tokenRepo;
    private ApplicationRepository $appRepo;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tokenRepo = $this->createMock(AgentTokenRepository::class);
        $this->appRepo = $this->createMock(ApplicationRepository::class);
        $this->tenantContext = $this->createMock(TenantContext::class);

        $this->client = static::createClient();
        $container = $this->client->getContainer();
        $container->set(AgentTokenRepository::class, $this->tokenRepo);
        $container->set(ApplicationRepository::class, $this->appRepo);
        $container->set(TenantContext::class, $this->tenantContext);
    }

    public function testInstallScriptWithValidToken(): void
    {
        $company = new Company();
        $company->setSlug('test-company');

        $env = new Environment();
        $env->setSlug('production');
        $env->setCompany($company);

        $token = new AgentToken();
        $token->setToken('valid-token-123');
        $token->setEnvironment($env);
        $token->setInstallScript('#!/bin/bash\necho "Install script"');
        $token->setIsActive(true);

        $this->tokenRepo->method('findByToken')
            ->with('valid-token-123')
            ->willReturn($token);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/agent/install/valid-token-123'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/x-sh', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('install-obs-test-company-production.sh', $response->headers->get('Content-Disposition'));
        $this->assertEquals('#!/bin/bash\necho "Install script"', $response->getContent());
    }

    public function testInstallScriptWithInvalidToken(): void
    {
        $this->tokenRepo->method('findByToken')
            ->with('invalid-token')
            ->willReturn(null);

        $this->client->request(
            Request::METHOD_GET,
            '/api/v1/agent/install/invalid-token'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('Token invalide ou expiré', $response->getContent());
    }

    public function testRegisterAgent(): void
    {
        $company = new Company();
        $env = new Environment();
        $env->setCompany($company);

        $token = new AgentToken();
        $token->setToken('valid-token-123');
        $token->setEnvironment($env);
        $token->setIsActive(true);

        $app = new Application();
        $app->setEnvironment($env);
        $app->setName('Test App');

        $this->tokenRepo->method('findByToken')
            ->with('valid-token-123')
            ->willReturn($token);

        $this->appRepo->method('findByTokenAndEnv')
            ->with($token, $env)
            ->willReturn(null);

        $this->appRepo->method('find')
            ->willReturn($app);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->em->expects($this->once())
            ->method('persist');

        $this->em->expects($this->once())
            ->method('flush');

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/agent/register',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer valid-token-123'],
            json_encode([
                'hostname' => 'test-server',
                'hostname_short' => 'test',
                'os_id' => 'debian',
            ])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('application_id', $data);
    }

    public function testHeartbeat(): void
    {
        $company = new Company();
        $env = new Environment();
        $env->setCompany($company);

        $token = new AgentToken();
        $token->setToken('valid-token-123');
        $token->setEnvironment($env);
        $token->setIsActive(true);

        $this->tokenRepo->method('findByToken')
            ->with('valid-token-123')
            ->willReturn($token);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->em->expects($this->once())
            ->method('flush');

        $this->client->request(
            Request::METHOD_POST,
            '/api/v1/agent/heartbeat',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer valid-token-123'],
            json_encode(['hostname' => 'test-server'])
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('alive', $data['status']);
    }
}
