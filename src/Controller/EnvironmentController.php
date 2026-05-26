<?php
namespace App\Controller;

use App\Entity\AgentToken;
use App\Entity\Environment;
use App\Entity\EnvironmentUser;
use App\Enum\EnvironmentType;
use App\Enum\UserEnvironmentRole;
use App\Repository\CompanyRepository;
use App\Repository\CompanyUserRepository;
use App\Repository\EnvironmentRepository;
use App\Service\AgentInstallScriptGenerator;
use App\Service\CompanyProvisioningService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/environments', name: 'env_')]
#[IsGranted('ROLE_USER')]
class EnvironmentController extends AbstractController
{
    public function __construct(
        private readonly TenantContext               $tenant,
        private readonly CompanyProvisioningService  $provisioning,
        private readonly AgentInstallScriptGenerator $scriptGen,
        private readonly CompanyUserRepository       $userRepo,
        private readonly EntityManagerInterface      $em,
        private readonly CompanyRepository           $companyRepo,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->render('environment/index.html.twig', [
            'environments' => $this->tenant->getAccessibleEnvironments(),
            'user'         => $this->tenant->getUser(),
        ]);
    }

    #[Route('/new', name: 'new')]
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function form(Request $request, ?Environment $env = null): Response
    {
        $isNew   = $env === null;
        $user    = $this->tenant->getUser();
        $company = $user?->getCompany() ?? null;

        // Superadmin global sans company → sélection de company requise
        if (!$company && $this->isGranted('ROLE_SUPERADMIN')) {
            $companyId = $request->query->getInt('company_id') ?: $request->request->getInt('company_id');
            if ($companyId) {
                $company = $this->companyRepo->find($companyId);
            }
            if (!$company) {
                $companies = $this->companyRepo->findAll();
                if (count($companies) === 1) {
                    $company = $companies[0];
                } else {
                    return $this->render('environment/select_company.html.twig', [
                        'companies' => $companies,
                        'redirect'  => 'env_new',
                    ]);
                }
            }
        }

        if (!$company) {
            $this->addFlash('error', 'Aucune entreprise associée.');
            return $this->redirectToRoute('register_index');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            if ($isNew) {
                try {
                    $env = $this->provisioning->createEnvironment($company, $user, $data);
                    $this->addFlash('success', "Environnement \"{$env->getName()}\" créé.");
                    return $this->redirectToRoute('env_show', ['id' => $env->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $env->setName($data['name'] ?? $env->getName());
                $env->setDescription($data['description'] ?? null);
                $env->setType(EnvironmentType::from($data['type'] ?? 'development'));
                $env->setColor($data['color'] ?? '#185FA5');
                if (!empty($data['kubernetes_api_url'])) {
                    $env->setKubernetesApiUrl($data['kubernetes_api_url']);
                    $env->setKubeconfig($data['kubeconfig'] ?? null);
                    $env->setKubernetesNamespace($data['kubernetes_namespace'] ?? 'default');
                    $env->setKubernetesEnabled(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Environnement mis à jour.");
                return $this->redirectToRoute('env_show', ['id' => $env->getId()]);
            }
        }

        return $this->render('environment/form.html.twig', [
            'env'      => $env,
            'isNew'    => $isNew,
            'envTypes' => EnvironmentType::cases(),
            'envCount' => $company->getEnvironments()->count(),
            'maxEnv'   => $company->getMaxEnvironments(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Environment $env): Response
    {
        // Superadmin global peut voir tous les environnements
        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAccessEnvironment($env)) {
            throw $this->createAccessDeniedException();
        }

        $this->tenant->setCurrentEnvironment($env);

        return $this->render('environment/show.html.twig', [
            'env'          => $env,
            'role'         => $this->tenant->getRoleInEnvironment($env),
            'canAdmin'     => $this->isGranted('ROLE_SUPERADMIN') || $this->tenant->canAdmin($env),
            'canOperate'   => $this->isGranted('ROLE_SUPERADMIN') || $this->tenant->canOperate($env),
            'agentTokens'  => $env->getAgentTokens()->toArray(),
            'applications' => $env->getApplications()->filter(fn($a) => $a->isActive())->toArray(),
            'k8sEnabled'   => $env->isKubernetesEnabled(),
        ]);
    }

    // ----------------------------------------------------------------
    // TOKENS AGENTS
    // ----------------------------------------------------------------

    #[Route('/{id}/tokens/new', name: 'token_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function newToken(Environment $env, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAccessEnvironment($env)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $name = $request->request->get('name', 'Nouveau token');
        $user = $this->tenant->getUser();

        $token = new AgentToken();
        $token->setName($name);
        $token->setCreatedByUser($user);
        $token->generate($env);

        $this->scriptGen->generate($token, $env, $env->getCompany());

        $this->em->persist($token);
        $this->em->flush();

        return $this->json([
            'success'        => true,
            'token_id'       => $token->getId(),
            'token'          => $token->getToken(),
            'masked_token'   => $token->getMaskedToken(),
            'install_script' => $token->getInstallScript(),
        ]);
    }

    #[Route('/{envId}/tokens/{tokenId}/revoke', name: 'token_revoke', methods: ['POST'])]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function revokeToken(int $envId, int $tokenId): JsonResponse
    {
        $token = $this->em->find(AgentToken::class, $tokenId);
        if (!$token || $token->getEnvironment()->getId() !== $envId) {
            return $this->json(['error' => 'Token introuvable.'], 404);
        }
        $token->setIsActive(false);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{envId}/tokens/{tokenId}/script', name: 'token_script', requirements: ['envId' => '\d+', 'tokenId' => '\d+'])]
    public function downloadScript(int $envId, int $tokenId): Response
    {
        $token = $this->em->find(AgentToken::class, $tokenId);
        if (!$token || $token->getEnvironment()->getId() !== $envId) {
            throw $this->createNotFoundException();
        }
        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAccessEnvironment($token->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        $script   = $token->getInstallScript()
            ?? $this->scriptGen->generate($token, $token->getEnvironment(), $token->getEnvironment()->getCompany());
        $filename = "install-agent-{$token->getEnvironment()->getSlug()}.sh";

        return new Response($script, 200, [
            'Content-Type'        => 'application/x-sh',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ----------------------------------------------------------------
    // GESTION DES UTILISATEURS DE L'ENVIRONNEMENT
    // ----------------------------------------------------------------

    #[Route('/{id}/users', name: 'users', requirements: ['id' => '\d+'])]
    public function users(Environment $env): Response
    {
        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAdmin($env)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('environment/users.html.twig', [
            'env'             => $env,
            'users'           => $env->getEnvironmentUsers()->toArray(),
            'roles'           => UserEnvironmentRole::cases(),
            'allCompanyUsers' => $this->userRepo->findByCompany($env->getCompany()),
        ]);
    }

    #[Route('/{id}/users/add', name: 'user_add', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addUser(Environment $env, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAdmin($env)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $userId = (int)$request->request->get('user_id');
        $role   = UserEnvironmentRole::from($request->request->get('role', 'viewer'));
        $user   = $this->userRepo->find($userId);

        if (!$user || $user->getCompany() !== $env->getCompany()) {
            return $this->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        try {
            $this->provisioning->addUserToEnvironment(
                $env, $user, $role, $this->tenant->getUser()
            );
            return $this->json([
                'success' => true,
                'message' => "{$user->getDisplayName()} ajouté avec le rôle {$role->getLabel()}.",
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{envId}/users/{userId}/remove', name: 'user_remove', methods: ['POST'])]
    public function removeUser(int $envId, int $userId): JsonResponse
    {
        $env  = $this->em->find(Environment::class, $envId);
        $user = $this->userRepo->find($userId);

        if (!$env || !$user) {
            return $this->json(['error' => 'Introuvable.'], 404);
        }

        if (!$this->isGranted('ROLE_SUPERADMIN') && !$this->tenant->canAdmin($env)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return $this->json(['error' => 'Impossible de retirer le superadmin.'], 400);
        }

        $envUser = $this->em->getRepository(EnvironmentUser::class)->findOneBy([
            'environment' => $env,
            'user'        => $user,
        ]);

        if ($envUser) {
            $envUser->setActive(false);
            $this->em->flush();
        }

        return $this->json(['success' => true]);
    }
}
