<?php
namespace App\Controller\Admin\API;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/admin/companies', name: 'api_admin_companies_')]
#[IsGranted('ROLE_SUPERADMIN')]
class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Liste toutes les companies
     * GET /api/admin/companies
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $companies = $this->companyRepo->findAll();
        $data = array_map(fn(Company $c) => [
            'id'    => $c->getId(),
            'name'  => $c->getName(),
            'slug'  => $c->getSlug(),
            'active' => $c->isActive(),
            'created' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
            'environments_count' => count($c->getEnvironments()),
            'users_count' => count($c->getCompanyUsers()),
        ], $companies);

        return $this->json(['companies' => $data]);
    }

    /**
     * Récupère une company spécifique
     * GET /api/admin/companies/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Company $company): JsonResponse
    {
        return $this->json([
            'id'    => $company->getId(),
            'name'  => $company->getName(),
            'slug'  => $company->getSlug(),
            'active' => $company->isActive(),
            'created' => $company->getCreatedAt()?->format('Y-m-d H:i:s'),
            'environments_count' => count($company->getEnvironments()),
            'users_count' => count($company->getCompanyUsers()),
        ]);
    }

    /**
     * Supprime une company
     * DELETE /api/admin/companies/{id}
     * 
     * ⚠️ DANGER: Supprime TOUT (environnements, applications, utilisateurs, etc.)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Company $company): JsonResponse
    {
        try {
            $companyName = $company->getName();
            $companyId = $company->getId();

            // Vérification: ne pas supprimer la dernière company
            $totalCompanies = count($this->companyRepo->findAll());
            if ($totalCompanies === 1) {
                return $this->json([
                    'success' => false,
                    'error' => 'Impossible de supprimer la dernière company du système.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Suppression en cascade (Doctrine gère les relations onDelete='CASCADE')
            $this->em->remove($company);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => "Company '{$companyName}' (ID: {$companyId}) supprimée avec succès.",
                'id' => $companyId,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
