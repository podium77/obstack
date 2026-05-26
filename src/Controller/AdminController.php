<?php
namespace App\Controller;

use App\Entity\CompanyUser;
use App\Entity\EnvironmentUser;
use App\Enum\UserEnvironmentRole;
use App\Repository\ApplicationRepository;
use App\Repository\CompanyRepository;
use App\Repository\CompanyUserRepository;
use App\Repository\EnvironmentRepository;
use App\Repository\RemediationLogRepository;
use App\Repository\AlertRepository;
use App\Service\CompanyProvisioningService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_SUPERADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly TenantContext              $tenant,
        private readonly CompanyUserRepository      $userRepo,
        private readonly EnvironmentRepository      $envRepo,
        private readonly ApplicationRepository      $appRepo,
        private readonly AlertRepository            $alertRepo,
        private readonly RemediationLogRepository   $logRepo,
        private readonly CompanyProvisioningService $provisioning,
        private readonly EntityManagerInterface     $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CompanyRepository          $companyRepo,
    ) {}

    // ─── Tableau de bord admin ────────────────────────────────────────
    #[Route('', name: 'index')]
    public function index(): Response
    {
        $company = $this->tenant->getCompany();

        // Superadmin global → vue toutes companies
        if (!$company && $this->isGranted('ROLE_SUPERADMIN')) {
            $allCompanies = $this->companyRepo->findAll();
            $apps = $this->appRepo->findAllActive();

            return $this->render('admin/index_superadmin.html.twig', [
                'companies'  => $allCompanies,
                'apps'       => $apps,
                'alertCount' => count($this->alertRepo->findActiveAlerts(1000)),
                'logCount'   => count($this->logRepo->findRecent(1000)),
            ]);
        }

        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        $environments = $this->envRepo->findActiveByCompany($company);
        $apps = $this->appRepo->findAllActiveByCompany($company);

        return $this->render('admin/index.html.twig', [
            'company'      => $company,
            'users'        => $this->userRepo->findByCompany($company),
            'environments' => $environments,
            'apps'         => $apps,
            'alertCount'   => count($this->alertRepo->findActiveAlerts(1000)),
            'logCount'     => count($this->logRepo->findRecent(1000)),
            'rcaEnabled'   => $company->isRcaEnabled(),
            'kgEnabled'    => $company->isKgEnabled(),
        ]);
    }

    // ─── Gestion des utilisateurs de l'entreprise ────────────────────
    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        $company = $this->tenant->getCompany();

        if (!$company && $this->isGranted('ROLE_SUPERADMIN')) {
            // Superadmin global → tous les utilisateurs
            return $this->render('admin/users.html.twig', [
                'company' => null,
                'users'   => $this->userRepo->findAll(),
                'envs'    => $this->envRepo->findAll(),
                'roles'   => UserEnvironmentRole::cases(),
            ]);
        }

        if (!$company) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('admin/users.html.twig', [
            'company' => $company,
            'users'   => $this->userRepo->findByCompany($company),
            'envs'    => $this->envRepo->findActiveByCompany($company),
            'roles'   => UserEnvironmentRole::cases(),
        ]);
    }

    #[Route('/users/new', name: 'user_new')]
    #[Route('/users/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'])]
    public function userForm(Request $request, ?CompanyUser $user = null): Response
    {
        $company = $this->tenant->getCompany();

        if (!$company && !$this->isGranted('ROLE_SUPERADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $isNew   = ($user === null);

        if ($isNew) {
            $user = new CompanyUser();
            if ($company) {
                $user->setCompany($company);
            }
        } elseif ($company && $user->getCompany() !== $company) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Empêcher la modification du superadmin existant
            if ($user->isSuperAdmin() && !$isNew) {
                $user->setEmail($data['email'] ?? $user->getEmail());
                $user->setDisplayName($data['display_name'] ?? $user->getDisplayName());
                if (!empty($data['password'])) {
                    $user->setPassword($this->hasher->hashPassword($user, $data['password']));
                }
                $this->em->flush();
                $this->addFlash('success', 'Superadmin mis à jour.');
                return $this->redirectToRoute('admin_users');
            }

            $user->setUsername($data['username'] ?? $user->getUsername());
            $user->setDisplayName($data['display_name'] ?? null);
            $user->setEmail($data['email'] ?? null);
            $user->setType($data['type'] ?? CompanyUser::TYPE_LOCAL);
            $user->setActive(isset($data['active']));

            // Accès global (délégation depuis superadmin)
            $user->setGlobalAccess(isset($data['global_access']) && !$user->isSuperAdmin());

            if (!empty($data['password'])) {
                $user->setPassword($this->hasher->hashPassword($user, $data['password']));
            }

            if ($user->isLdap()) {
                $user->setLdapDn($data['ldap_dn'] ?? null);
            }

            $this->em->persist($user);

            // Assigner des environnements
            if (!empty($data['env_roles'])) {
                foreach ($data['env_roles'] as $envId => $roleValue) {
                    if (empty($roleValue)) continue;
                    $env  = $this->envRepo->find((int)$envId);
                    $role = UserEnvironmentRole::tryFrom($roleValue);
                    if ($env && $role && $env->getCompany() === $company) {
                        try {
                            $this->provisioning->addUserToEnvironment(
                                $env, $user, $role, $this->tenant->getUser()
                            );
                        } catch (\Exception $e) {
                            $this->addFlash('error', $e->getMessage());
                        }
                    }
                }
            }

            $this->em->flush();
            $this->addFlash('success', $isNew
                ? "Utilisateur \"{$user->getUsername()}\" créé."
                : "Utilisateur mis à jour."
            );
            return $this->redirectToRoute('admin_users');
        }

        $envs = $company ? $this->envRepo->findActiveByCompany($company) : $this->envRepo->findAll();

        // Accès actuels par env
        $currentAccess = [];
        foreach ($user->getEnvironmentAccesses() as $eu) {
            if ($eu->isActive()) {
                $currentAccess[$eu->getEnvironment()->getId()] = $eu->getRole()->value;
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'user'          => $user,
            'isNew'         => $isNew,
            'company'       => $company,
            'envs'          => $envs,
            'roles'         => UserEnvironmentRole::cases(),
            'currentAccess' => $currentAccess,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(CompanyUser $user, Request $request): JsonResponse
    {
        $company = $this->tenant->getCompany();

        if (!$company && !$this->isGranted('ROLE_SUPERADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($company && $user->getCompany() !== $company) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }
        if ($user->isSuperAdmin()) {
            return $this->json(['error' => 'Le superadmin ne peut pas être supprimé.'], 400);
        }
        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token invalide.'], 403);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // ─── Paramètres de l'entreprise ──────────────────────────────────
    #[Route('/settings', name: 'settings')]
    public function settings(Request $request): Response
    {
        $company = $this->tenant->getCompany();

        if (!$company) {
            if (!$this->isGranted('ROLE_SUPERADMIN')) {
                throw $this->createAccessDeniedException();
            }
            return $this->redirectToRoute('admin_index');
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            // Infos générales
            $company->setDescription($data['description'] ?? null);
            $company->setBrandColor($data['brand_color'] ?? '#185FA5');

            // LDAP
            if (!empty($data['ldap_host'])) {
                $company->setLdapHost($data['ldap_host']);
                $company->setLdapPort((int)($data['ldap_port'] ?? 389));
                $company->setLdapBaseDn($data['ldap_base_dn'] ?? null);
                $company->setLdapBindDn($data['ldap_bind_dn'] ?? null);
                if (!empty($data['ldap_bind_password'])) {
                    $company->setLdapBindPassword($data['ldap_bind_password']);
                }
                $company->setLdapUserBaseDn($data['ldap_user_base_dn'] ?? null);
                $company->setLdapGroupBaseDn($data['ldap_group_base_dn'] ?? null);
            } else {
                $company->setLdapHost(null);
            }

            // PyRCA
            $company->setRcaEnabled(isset($data['rca_enabled']));
            if ($company->isRcaEnabled()) {
                $company->setRcaConfig(array_merge($company->getRcaConfig(), [
                    'api_url'      => $data['rca_api_url'] ?? null,
                    'api_key'      => $data['rca_api_key'] ?? null,
                    'model'        => $data['rca_model'] ?? 'bayesian',
                    'auto_analyze' => isset($data['rca_auto_analyze']),
                ]));
            }

            // Knowledge Graph
            $company->setKgEnabled(isset($data['kg_enabled']));
            if ($company->isKgEnabled()) {
                $company->setKgConfig(array_merge($company->getKgConfig(), [
                    'uri'      => $data['kg_uri'] ?? null,
                    'user'     => $data['kg_user'] ?? null,
                    'database' => $data['kg_database'] ?? 'obstack',
                ]));
                if (!empty($data['kg_password'])) {
                    $conf = $company->getKgConfig();
                    $conf['password'] = $data['kg_password'];
                    $company->setKgConfig($conf);
                }
            }

            $this->em->flush();
            $this->addFlash('success', 'Paramètres enregistrés.');
            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings.html.twig', ['company' => $company]);
    }

    // ─── Test connexion LDAP ─────────────────────────────────────────
    #[Route('/settings/test-ldap', name: 'test_ldap', methods: ['POST'])]
    public function testLdap(Request $request): JsonResponse
    {
        $company = $this->tenant->getCompany();

        if (!$company) {
            if (!$this->isGranted('ROLE_SUPERADMIN')) {
                throw $this->createAccessDeniedException();
            }
            return $this->json(['success' => false, 'message' => 'Aucun compte entreprise associé.'], 403);
        }

        if (!$company->hasLdap()) {
            return $this->json(['success' => false, 'message' => 'LDAP non configuré.']);
        }

        try {
            $adapter = new \Symfony\Component\Ldap\Adapter\ExtLdap\Adapter([
                'host' => $company->getLdapHost(),
                'port' => $company->getLdapPort() ?? 389,
            ]);
            $ldap = new \Symfony\Component\Ldap\Ldap($adapter);
            $ldap->bind($company->getLdapBindDn(), $company->getLdapBindPassword());

            $q = $ldap->query($company->getLdapUserBaseDn() ?? $company->getLdapBaseDn(), '(objectClass=inetOrgPerson)');
            $count = count($q->execute());

            return $this->json([
                'success' => true,
                'message' => "Connexion LDAP réussie. {$count} utilisateur(s) trouvé(s).",
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur LDAP: ' . $e->getMessage(),
            ]);
        }
    }

    // ─── Régénération du token maître d'un environnement ─────────────
    #[Route('/env/{id}/regen-token', name: 'env_regen_token', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function regenEnvToken(int $id): JsonResponse
    {
        $env = $this->envRepo->find($id);
        if (!$env || !$this->tenant->canAccessEnvironment($env)) {
            return $this->json(['error' => 'Introuvable.'], 404);
        }

        $env->regenerateMasterToken();
        $this->em->flush();

        return $this->json(['success' => true, 'new_token' => $env->getMasterToken()]);
    }
}
