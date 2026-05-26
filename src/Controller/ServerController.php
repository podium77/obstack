<?php
namespace App\Controller;

use App\Repository\ApplicationRepository;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/serveurs', name: 'servers_')]
#[IsGranted('ROLE_USER')]
class ServerController extends AbstractController
{
    public function __construct(
        private readonly TenantContext         $tenant,
        private readonly ApplicationRepository $appRepo,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $environments = $this->tenant->getAccessibleEnvironments();
        $servers = [];

        foreach ($environments as $env) {
            $apps = $this->appRepo->findAllActiveByEnvironment($env);
            foreach ($apps as $app) {
                $servers[] = [
                    'app' => $app,
                    'environment' => $env,
                ];
            }
        }

        return $this->render('server/index.html.twig', [
            'servers'      => $servers,
            'environments' => $environments,
        ]);
    }
}
