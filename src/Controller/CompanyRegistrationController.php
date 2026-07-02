<?php
namespace App\Controller;

use App\Entity\LocalUser;
use App\Service\CompanyProvisioningService;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', name: 'register_')]
class CompanyRegistrationController extends AbstractController
{
    public function __construct(
        private readonly CompanyProvisioningService $provisioning,
        private readonly CompanyRepository          $companyRepo,
    ) {}

    /** Page d'inscription d'une nouvelle entreprise */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Si admin global, rediriger directement vers le dashboard
        if ($user instanceof LocalUser && $user->isGlobalAdmin()) {
            return $this->redirectToRoute('dashboard');
        }
        
        // Si utilisateur avec entreprise, rediriger vers le dashboard
        if ($user && $user->getCompany() !== null) {
            return $this->redirectToRoute('dashboard');
        }
        
        return $this->render('company/register.html.twig');
    }

    /** Traitement du formulaire d'inscription */
    #[Route('', name: 'submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $user = $this->getUser();
        
        // Si admin global, refuser la création d'instance
        if ($user instanceof LocalUser && $user->isGlobalAdmin()) {
            $this->addFlash('error', 'En tant qu\'administrateur global, vous n\'avez pas besoin de créer une instance.');
            return $this->redirectToRoute('dashboard');
        }
        
        // Si utilisateur avec entreprise, rediriger vers le dashboard
        if ($user && $user->getCompany() !== null) {
            return $this->redirectToRoute('dashboard');
        }

        $data = $request->request->all();

        // Validations basiques
        $errors = $this->validateRegistrationData($data);
        if (!empty($errors)) {
            return $this->render('company/register.html.twig', [
                'errors' => $errors,
                'data'   => $data,
            ]);
        }

        // Vérifier unicité du slug/nom
        $slug = $this->sanitizeSlug($data['company_name'] ?? '');
        if ($this->companyRepo->findOneBySlugPrefix($slug)) {
            return $this->render('company/register.html.twig', [
                'errors' => ['company_name' => 'Ce nom d\'entreprise est déjà utilisé.'],
                'data'   => $data,
            ]);
        }

        try {
            $result = $this->provisioning->provision([
                'company_name'       => $data['company_name'],
                'description'        => $data['description'] ?? null,
                'brand_color'        => $data['brand_color'] ?? '#185FA5',
                'admin_username'     => $data['admin_username'],
                'admin_email'        => $data['admin_email'],
                'admin_password'     => $data['admin_password'],
                'admin_display_name' => $data['admin_display_name'] ?? null,
                'ldap_host'          => $data['ldap_host'] ?? null,
                'ldap_port'          => $data['ldap_port'] ?? 389,
                'ldap_base_dn'       => $data['ldap_base_dn'] ?? null,
                'ldap_bind_dn'       => $data['ldap_bind_dn'] ?? null,
                'ldap_bind_password' => $data['ldap_bind_password'] ?? null,
                'ldap_user_base_dn'  => $data['ldap_user_base_dn'] ?? null,
                'ldap_group_base_dn' => $data['ldap_group_base_dn'] ?? null,
                'rca_enabled'        => isset($data['rca_enabled']),
                'rca_config'         => [
                    'backend'      => $data['rca_backend'] ?? 'pyrca',
                    'api_url'      => $data['rca_api_url'] ?? null,
                    'api_key'      => $data['rca_api_key'] ?? null,
                    'model'        => $data['rca_model'] ?? 'bayesian',
                    'auto_analyze' => isset($data['rca_auto_analyze']),
                ],
                'kg_enabled'      => isset($data['kg_enabled']),
                'kg_config'       => [
                    'backend'  => $data['kg_backend'] ?? 'neo4j',
                    'uri'      => $data['kg_uri'] ?? null,
                    'user'     => $data['kg_user'] ?? null,
                    'password' => $data['kg_password'] ?? null,
                    'database' => $data['kg_database'] ?? 'obstack',
                ],
                'enabled_modules' => $data['enabled_modules'] ?? [],
            ]);

            return $this->render('company/register_success.html.twig', [
                'company'       => $result['company'],
                'super_admin'   => $result['super_admin'],
                'default_env'   => $result['default_env'],
                'agent_token'   => $result['agent_token'],
                'install_script'=> $result['install_script'],
            ]);

        } catch (\Throwable $e) {
            return $this->render('company/register.html.twig', [
                'errors' => ['global' => 'Erreur lors de la création: ' . $e->getMessage()],
                'data'   => $data,
            ]);
        }
    }

    /** API: vérifier la disponibilité d'un nom d'entreprise */
    #[Route('/check-name', name: 'check_name', methods: ['POST'])]
    public function checkName(Request $request): JsonResponse
    {
        $name = $request->request->get('name', '');
        $slug = $this->sanitizeSlug($name);
        $taken = $slug && $this->companyRepo->findOneBySlugPrefix($slug);

        return $this->json(['available' => !$taken, 'slug' => $slug]);
    }

    private function validateRegistrationData(array $data): array
    {
        $errors = [];
        if (empty($data['company_name']))    $errors['company_name']    = 'Le nom est requis.';
        if (empty($data['admin_username']))  $errors['admin_username']  = 'L\'identifiant est requis.';
        if (empty($data['admin_email']))     $errors['admin_email']     = 'L\'email est requis.';
        if (empty($data['admin_password']))  $errors['admin_password']  = 'Le mot de passe est requis.';
        if (strlen($data['admin_password'] ?? '') < 8) {
            $errors['admin_password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if (!filter_var($data['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Email invalide.';
        }
        return $errors;
    }

    private function sanitizeSlug(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
