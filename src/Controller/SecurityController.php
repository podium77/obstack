<?php
namespace App\Controller;

use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepo,
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
            'saved_slug'    => $request->getSession()->get('company_slug'),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le firewall Symfony.');
    }

    /** API: détecter une entreprise par son slug (pour le formulaire de login) */
    #[Route('/api/company/detect', name: 'api_company_detect', methods: ['GET'])]
    public function detectCompany(Request $request): JsonResponse
    {
        $slug    = trim($request->query->get('slug', ''));
        $company = $this->companyRepo->findOneBy(['slug' => $slug, 'active' => true])
            ?? $this->companyRepo->findOneBySlugPrefix($slug);

        if (!$company) {
            return $this->json(['found' => false]);
        }

        return $this->json([
            'found'    => true,
            'name'     => $company->getName(),
            'slug'     => $company->getSlug(),
            'has_ldap' => $company->hasLdap(),
            'color'    => $company->getBrandColor(),
        ]);
    }
}
