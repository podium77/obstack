<?php
namespace App\Tests\Controller;

use App\Controller\EnvironmentController;
use App\Entity\Company;
use App\Entity\CompanyUser;
use App\Entity\Environment;
use App\Repository\CompanyRepository;
use App\Repository\CompanyUserRepository;
use App\Repository\EnvironmentRepository;
use App\Service\AgentInstallScriptGenerator;
use App\Service\CompanyProvisioningService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EnvironmentControllerTest extends TestCase
{
    public function testCreateEnvironmentUsesCompanySuperAdminWhenTenantUserNull(): void
    {
        $tenant = $this->createMock(TenantContext::class);
        $tenant->method('getUser')->willReturn(null);

        $provisioning = $this->createMock(CompanyProvisioningService::class);
        $scriptGen = $this->createMock(AgentInstallScriptGenerator::class);
        $userRepo = $this->createMock(CompanyUserRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $companyRepo = $this->createMock(CompanyRepository::class);
        $envRepo = $this->createMock(EnvironmentRepository::class);

        $company = new Company();
        $reflection = new \ReflectionClass($company);
        $prop = $reflection->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($company, 42);

        $superAdmin = $this->createMock(CompanyUser::class);

        $companyRepo->method('find')->with(123)->willReturn($company);
        $userRepo->method('findSuperAdmin')->with($company)->willReturn($superAdmin);

        $env = $this->createMock(Environment::class);

        $provisioning->expects($this->once())
            ->method('createEnvironment')
            ->with($this->equalTo($company), $this->equalTo($superAdmin), $this->isType('array'))
            ->willReturn($env);

        $controller = $this->getMockBuilder(EnvironmentController::class)
            ->setConstructorArgs([$tenant, $provisioning, $scriptGen, $userRepo, $em, $companyRepo])
            ->onlyMethods(['isGranted', 'addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->method('isGranted')->willReturn(true);
        $controller->method('redirectToRoute')->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/'));

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('company_id', 123);
        $request->request->set('name', 'test-env');

        $res = $controller->form($request, null);

        $this->assertInstanceOf(Response::class, $res);
    }

    public function testCreateEnvironmentAddsFlashWhenNoCompanyUserFound(): void
    {
        $tenant = $this->createMock(TenantContext::class);
        $tenant->method('getUser')->willReturn(null);

        $provisioning = $this->createMock(CompanyProvisioningService::class);
        $scriptGen = $this->createMock(AgentInstallScriptGenerator::class);
        $userRepo = $this->createMock(CompanyUserRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $companyRepo = $this->createMock(CompanyRepository::class);
        $envRepo = $this->createMock(EnvironmentRepository::class);

        $company = new Company();
        $reflection = new \ReflectionClass($company);
        $prop = $reflection->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($company, 42);

        $companyRepo->method('find')->with(123)->willReturn($company);
        $userRepo->method('findSuperAdmin')->with($company)->willReturn(null);

        $controller = $this->getMockBuilder(EnvironmentController::class)
            ->setConstructorArgs([$tenant, $provisioning, $scriptGen, $userRepo, $em, $companyRepo])
            ->onlyMethods(['isGranted', 'addFlash', 'redirectToRoute', 'render'])
            ->getMock();

        $controller->method('isGranted')->willReturn(true);

        $controller->expects($this->once())->method('addFlash')->with('error', $this->stringContains('Aucun CompanyUser'));
        $controller->method('redirectToRoute')->willReturn(new \Symfony\Component\HttpFoundation\RedirectResponse('/'));
        $controller->method('render')->willReturn(new Response('form'));

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('company_id', 123);
        $request->request->set('name', 'test-env');

        $res = $controller->form($request, null);

        $this->assertInstanceOf(Response::class, $res);
    }
}
