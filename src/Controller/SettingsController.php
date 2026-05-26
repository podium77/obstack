<?php
namespace App\Controller;

use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parametres', name: 'settings_')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $company = $this->tenant->getCompany();

        if (!$company && !$this->tenant->isSuperAdmin()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('settings/index.html.twig', [
            'company' => $company,
        ]);
    }
}
