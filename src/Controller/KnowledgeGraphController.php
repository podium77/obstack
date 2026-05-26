<?php
namespace App\Controller;

use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/knowledge-graph', name: 'knowledge_graph_')]
#[IsGranted('ROLE_USER')]
class KnowledgeGraphController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $company = $this->tenant->getCompany();

        if (!$company && !$this->isGranted('ROLE_SUPERADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $environments = $this->tenant->getAccessibleEnvironments();

        return $this->render('knowledge_graph/index.html.twig', [
            'company'      => $company,
            'environments' => $environments,
        ]);
    }
}
