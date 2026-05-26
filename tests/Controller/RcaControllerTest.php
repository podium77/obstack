<?php
namespace App\Tests\Controller;

use App\Entity\Alert;
use App\Entity\Application;
use App\Entity\Company;
use App\Entity\CompanyUser;
use App\Entity\Environment;
use App\Entity\RcaAnalysis;
use App\Enum\RcaStatus;
use App\RCA\RcaResult;
use App\Repository\AlertRepository;
use App\Repository\RcaAnalysisRepository;
use App\Service\RcaService;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RcaControllerTest extends WebTestCase
{
    private AlertRepository $alertRepo;
    private RcaAnalysisRepository $rcaRepo;
    private RcaService $rcaService;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        $this->alertRepo = $this->createMock(AlertRepository::class);
        $this->rcaRepo = $this->createMock(RcaAnalysisRepository::class);
        $this->rcaService = $this->createMock(RcaService::class);
        $this->tenantContext = $this->createMock(TenantContext::class);

        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $container = $this->client->getContainer();
        $container->set(AlertRepository::class, $this->alertRepo);
        $container->set(RcaAnalysisRepository::class, $this->rcaRepo);
        $container->set(RcaService::class, $this->rcaService);
        $container->set(TenantContext::class, $this->tenantContext);

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
        $company->setRcaEnabled(true);

        $env = new Environment();
        $env->setCompany($company);

        $alert = new Alert();
        $alert->setTitle('Test Alert');

        $analysis = new RcaAnalysis();
        $analysis->setAlert($alert);
        $analysis->setStatus(RcaStatus::COMPLETED);

        $user = new CompanyUser();
        $user->setId(1)->setCompany($company)->setType(CompanyUser::TYPE_LOCAL)->setUsername('operator');

        $this->tenantContext->method('getUser')
            ->willReturn($user);

        $this->rcaRepo->method('findByCompany')
            ->with($company)
            ->willReturn([$analysis]);

        $this->client->request(Request::METHOD_GET, '/rca');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSelectorTextContains('h1', 'Analyses RCA');
    }

    public function testAnalyzeAlert(): void
    {
        $company = new Company();
        $company->setRcaEnabled(true);

        $env = new Environment();
        $env->setCompany($company);

        $app = new Application();
        $app->setEnvironment($env);

        $alert = new Alert();
        $alert->setId(1);
        $alert->setTitle('Test Alert');
        $alert->setApplication($app);

        $result = new RcaResult(
            success: true,
            rootCauses: [['cause' => 'High CPU usage']],
            confidence: 0.95,
            explanation: 'Test explanation',
            graphData: [],
            model: 'bayesian',
            analyzedAt: new \DateTimeImmutable(),
        );

        $this->alertRepo->method('find')
            ->with(1)
            ->willReturn($alert);

        $this->tenantContext->method('canAccessEnvironment')
            ->with($env)
            ->willReturn(true);

        $this->rcaService->method('analyzeAlert')
            ->with($alert)
            ->willReturn($result);

        $this->client->request(Request::METHOD_GET, '/rca/analyze/1');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSelectorTextContains('h1', 'Analyse RCA');
    }

    public function testSettings(): void
    {
        $company = new Company();
        $company->setRcaEnabled(true);
        $company->setRcaConfig([
            'api_url' => 'https://rca.example.com',
            'api_key' => 'test-key',
        ]);

        $user = new CompanyUser();
        $user->setId(1)->setCompany($company)->setType(CompanyUser::TYPE_LOCAL)->setUsername('operator');

        $this->tenantContext->method('getUser')
            ->willReturn($user);

        $this->client->request(Request::METHOD_GET, '/rca/settings');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSelectorTextContains('h1', 'Paramètres RCA');
    }
}
