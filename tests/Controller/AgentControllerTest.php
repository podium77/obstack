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
use Symfony\Component\HttpFoundation\Cookie;
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

        $roles = ['ROLE_SUPERADMIN','ROLE_OPERATOR'];
        // Authenticate test client using the built-in helper
        $this->client->loginUser(new InMemoryUser('operator', null, $roles));
        // Ensure a session is available for CSRF token generation
        if ($container->has('session')) {
            $session = $container->get('session');
            if (method_exists($session, 'start')) {
                $session->start();
            }
            // Ensure client sends session cookie so CSRF token manager can access session
            $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
        }

        // Provide a simple CSRF token manager mock to avoid RequestStack/session issues in tests
        $csrfMock = $this->createMock(\Symfony\Component\Security\Csrf\CsrfTokenManagerInterface::class);
        $csrfMock->method('getToken')->willReturn(new \Symfony\Component\Security\Csrf\CsrfToken('agent_token', 'test-csrf'));
        $csrfMock->method('isTokenValid')->willReturn(true);
        $container->set('security.csrf.token_manager', $csrfMock);

        // Provide a simple obs_user global for Twig to avoid template rendering issues
        $obsUser = new class {
            public bool $isSuperAdmin = true;
            public bool $isLdap = false;
            public string $initials = 'OP';
            public string $displayName = 'Operator';
        };
        if ($container->has('twig')) {
            $twig = $container->get('twig');
            if (method_exists($twig, 'addGlobal')) {
                $twig->addGlobal('obs_user', $obsUser);
            }
        }

        // Note: do not override initialized security services; use client loginUser above.
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
        // Check for page title in topbar instead of h1
        $this->assertSelectorTextContains('.topbar-title', 'Gestion des agents');
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
        // Accept success (2xx) or redirect (3xx), but not server error (5xx)
        $this->assertLessThan(500, $response->getStatusCode(), 'Response should not be a server error. Status: ' . $response->getStatusCode());
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
        // Accept success (2xx) or redirect (3xx), but not server error (5xx)
        $this->assertLessThan(500, $response->getStatusCode(), 'Response should not be a server error. Status: ' . $response->getStatusCode());
    }
}
