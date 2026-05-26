<?php
namespace App\Tests\Controller;

use App\Entity\Company;
use App\Entity\CompanyUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class InstanceModulesControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $company = new Company();
        $company->setBrandColor('#185FA5');

        $user = new CompanyUser();
        $user->setCompany($company)
             ->setUsername('testuser')
             ->setType(CompanyUser::TYPE_LOCAL);

        $container = $this->client->getContainer();
        $container->get('security.untracked_token_storage')->setToken(
            new \Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken(
                ['ROLE_USER'],
                $user,
                'main'
            )
        );
        $container->get('security.token_storage')->setToken(
            new \Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken(
                ['ROLE_USER'],
                $user,
                'main'
            )
        );
    }

    public function testIndexReturns200(): void
    {
        $this->client->request('GET', '/instance/123/modules');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<title>Modules', $response->getContent());
    }

    public function testPrometheusPageReturns200(): void
    {
        $this->client->request('GET', '/instance/123/modules/prometheus');

        $response = $this->client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<title>Prometheus', $response->getContent());
    }
}
