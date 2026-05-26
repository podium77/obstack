<?php
namespace App\Tests\Controller;

use App\Entity\AgentToken;
use App\Entity\Company;
use App\Entity\Environment;
use App\Repository\AgentTokenRepository;
use App\Repository\EnvironmentRepository;
use App\Service\AgentInstallScriptGenerator;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AgentControllerTest extends WebTestCase
{
    private AgentTokenRepository $tokenRepo;
    private EnvironmentRepository $envRepo;
    private TenantContext $tenantContext;
    private AgentInstallScriptGenerator $scriptGenerator;

    protected function setUp(): void
    {
        $this->tokenRepo = $this->createMock(AgentTokenRepository::class);
        $this->envRepo = $this->createMock(EnvironmentRepository::class);
        $this->tenantContext = $this->createMock(TenantContext::class);
        $this->scriptGenerator = $this->createMock(AgentInstallScriptGenerator::class);

        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $container = $this->client->getContainer();
        $container->set(AgentTokenRepository::class, $this->tokenRepo);
        $container->set(EnvironmentRepository::class, $this->envRepo);
        $container->set(TenantContext::class, $this->tenantContext);
        $container->set(AgentInstallScriptGenerator::class, $this->scriptGenerator);

        $container->get('security.untracked_token_storage')->setToken(
            new \Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken(
                ['ROLE_SUPERADMIN'],
                new InMemoryUser('operator', null, ['ROLE_SUPERADMIN']),
                'main'
            )
        );
        $container->get('security.token_storage')->setToken(
            new \Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken(
                ['ROLE_SUPERADMIN'],
                new InMemoryUser('operator', null, ['ROLE_SUPERADMIN']),
                'main'
            )
        );
    }

    public function testIndex(): void
    {
        $company = new Company();
        $env = new Environment();
        $env->setCompany($company);

        $token = new AgentToken();
        $token->setName('Test Token');
        $token->setEnvironment($env);

        $this->tenantContext->method('getAccessibleEnvironments')
            ->willReturn([$env]);

        $this->tokenRepo->method('findByEnvironment')
            ->with($env)
            ->willReturn([$token]);

        $this->client->request(Request::METHOD_GET, '/agent');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSelectorTextContains('h1', 'Gestion des agents');
    }

    public function testNewToken(): void
    {
        $company = new Company();
        $env = new Environment();
        $env->setId(1);
        $env->setName('Production');
        $env->setCompany($company);

        $this->envRepo->method('find')
            ->with(1)
            ->willReturn($env);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->scriptGenerator->method('generateForToken')
            ->willReturn('#!/bin/bash\necho "Test script"');


        $this->client->request(Request::METHOD_GET, '/agent/new/1');
        $csrfToken = $this->client->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('agent_token')
            ->getValue();

        $this->client->request(
            Request::METHOD_POST,
            '/agent/new/1',
            [
                'agent_token' => [
                    'name' => 'New Token',
                    'environment' => 1,
                    'isActive' => true,
                ],
                '_token' => $csrfToken,
            ]
        );

        $response = $this->client->getResponse();
        $this->assertTrue(
            $response->isRedirection() ||
            $response->isSuccessful()
        );
    }

    public function testRevokeToken(): void
    {
        $company = new Company();
        $env = new Environment();
        $env->setCompany($company);

        $token = new AgentToken();
        $token->setId(1);
        $token->setEnvironment($env);
        $token->setIsActive(true);

        $this->tokenRepo->method('find')
            ->with(1)
            ->willReturn($token);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->client->request(Request::METHOD_GET, '/agent');
        $csrfToken = $this->client->getContainer()
            ->get('security.csrf.token_manager')
            ->getToken('revoke_token_1')
            ->getValue();

        $this->client->request(
            Request::METHOD_POST,
            '/agent/1/revoke',
            ['_token' => $csrfToken]
        );

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirection());
    }
}
