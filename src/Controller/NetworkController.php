<?php
namespace App\Controller;

use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reseau', name: 'network_')]
#[IsGranted('ROLE_USER')]
class NetworkController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $environments = $this->tenant->getAccessibleEnvironments();

        return $this->render('network/index.html.twig', [
            'environments' => $environments,
        ]);
    }
}
