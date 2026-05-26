<?php
namespace App\Service;

use App\Entity\Company;
use App\Entity\CompanyUser;
use App\Entity\Environment;
use App\Entity\EnvironmentUser;
use App\Enum\EnvironmentType;
use App\Enum\UserEnvironmentRole;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gère le provisionnement complet d'une nouvelle entreprise:
 *  1. Création de l'entreprise (Company)
 *  2. Création du superadmin
 *  3. Création de l'environnement par défaut (opérationnel immédiatement)
 *  4. Association superadmin ↔ environnement par défaut
 *  5. Génération du token agent initial
 */
class CompanyProvisioningService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly AgentInstallScriptGenerator $scriptGenerator,
        private readonly LoggerInterface             $logger,
    ) {}

    /**
     * Crée une nouvelle instance entreprise complète.
     *
     * @param array $data {
     *   company_name, company_slug?, description?,
     *   brand_color?, logo?,
     *   admin_username, admin_email, admin_password,
     *   admin_display_name?,
     *   ldap_host?, ldap_port?, ldap_base_dn?,
     *   ldap_bind_dn?, ldap_bind_password?,
     *   ldap_user_base_dn?, ldap_group_base_dn?,
     *   rca_enabled?, kg_enabled?,
     *   rca_config?, kg_config?
     * }
     */
    public function provision(array $data): array
    {
        $this->em->beginTransaction();

        try {
            // 1. Créer l'entreprise
            $company = $this->createCompany($data);

            // 2. Créer le superadmin
            $superAdmin = $this->createSuperAdmin($company, $data);

            // 3. Créer l'environnement par défaut
            $defaultEnv = $this->createDefaultEnvironment($company, $superAdmin);

            // 4. Générer le token agent initial pour l'environnement par défaut
            $agentToken = $this->createInitialAgentToken(
                $defaultEnv,
                $superAdmin,
                $data['enabled_modules'] ?? []
            );

            $this->em->persist($company);
            $this->em->persist($superAdmin);
            $this->em->persist($defaultEnv);
            $this->em->persist($agentToken);
            $this->em->flush();
            $this->em->commit();

            $this->logger->info("Entreprise provisionnée: {$company->getName()} (slug: {$company->getSlug()})");

            return [
                'success'       => true,
                'company'       => $company,
                'super_admin'   => $superAdmin,
                'default_env'   => $defaultEnv,
                'agent_token'   => $agentToken,
                'install_script'=> $agentToken->getInstallScript(),
            ];

        } catch (\Throwable $e) {
            $this->em->rollback();
            $this->logger->error("Échec du provisionnement: {$e->getMessage()}");
            throw $e;
        }
    }

    private function createCompany(array $data): Company
    {
        $company = new Company();
        $company->setName($data['company_name']);
        $company->setDescription($data['description'] ?? null);
        $company->setBrandColor($data['brand_color'] ?? '#185FA5');

        // Slug personnalisé ou généré
        if (!empty($data['company_slug'])) {
            $company->setSlug($this->sanitizeSlug($data['company_slug']));
        } else {
            $company->generateSlug();
        }

        // Configuration LDAP
        if (!empty($data['ldap_host'])) {
            $company->setLdapHost($data['ldap_host']);
            $company->setLdapPort((int)($data['ldap_port'] ?? 389));
            $company->setLdapBaseDn($data['ldap_base_dn'] ?? null);
            $company->setLdapBindDn($data['ldap_bind_dn'] ?? null);
            $company->setLdapBindPassword($data['ldap_bind_password'] ?? null);
            $company->setLdapUserBaseDn($data['ldap_user_base_dn'] ?? null);
            $company->setLdapGroupBaseDn($data['ldap_group_base_dn'] ?? null);
        }

        // PyRCA
        if (!empty($data['rca_enabled'])) {
            $company->setRcaEnabled(true);
            if (!empty($data['rca_config'])) {
                $company->setRcaConfig(array_merge($company->getRcaConfig(), $data['rca_config']));
            }
        }

        // Knowledge Graph
        if (!empty($data['kg_enabled'])) {
            $company->setKgEnabled(true);
            if (!empty($data['kg_config'])) {
                $company->setKgConfig(array_merge($company->getKgConfig(), $data['kg_config']));
            }
        }

        return $company;
    }

    private function createSuperAdmin(Company $company, array $data): CompanyUser
    {
        $user = new CompanyUser();
        $user->setCompany($company);
        $user->setUsername($data['admin_username']);
        $user->setEmail($data['admin_email'] ?? null);
        $user->setDisplayName($data['admin_display_name'] ?? $data['admin_username']);
        $user->setType(CompanyUser::TYPE_SUPERADMIN);
        $user->setGlobalAccess(true);
        $user->setActive(true);

        // Hachage du mot de passe
        $hashed = $this->hasher->hashPassword($user, $data['admin_password']);
        $user->setPassword($hashed);

        return $user;
    }

    private function createDefaultEnvironment(Company $company, CompanyUser $admin): Environment
    {
        $env = new Environment();
        $env->setCompany($company);
        $env->setName('Environnement par défaut');
        $env->setSlug('default');
        $env->setType(EnvironmentType::DEFAULT);
        $env->setIsDefault(true);
        $env->setColor($company->getBrandColor());
        $env->setDescription('Environnement initial — opérationnel immédiatement après l\'inscription.');
        $env->setActive(true);

        // Associer le superadmin à l'environnement par défaut
        $envUser = new EnvironmentUser();
        $envUser->setEnvironment($env);
        $envUser->setUser($admin);
        $envUser->setRole(UserEnvironmentRole::OWNER);
        $envUser->setGrantedBy($admin);

        $this->em->persist($envUser);

        return $env;
    }

    private function createInitialAgentToken(Environment $env, CompanyUser $user, array $modules = []): \App\Entity\AgentToken
    {
        $token = new \App\Entity\AgentToken();
        $token->setName("Token agent initial — {$env->getName()}");
        $token->setEnvironment($env);
        $token->regenerateToken();
        $token->setModules($modules);

        // Générer le script d'installation
        $this->scriptGenerator->generate($token, $env, $env->getCompany());

        return $token;
    }

    /**
     * Crée un nouvel environnement pour une entreprise existante.
     */
    public function createEnvironment(
        Company     $company,
        CompanyUser $createdBy,
        array       $data
    ): Environment {
        // Vérifier la limite
        $envCount = $company->getEnvironments()->count();
        if ($envCount >= $company->getMaxEnvironments()) {
            throw new \RuntimeException(
                "Limite d'environnements atteinte ({$company->getMaxEnvironments()})"
            );
        }

        $env = new Environment();
        $env->setCompany($company);
        $env->setName($data['name']);
        $env->setSlug($this->sanitizeSlug($data['slug'] ?? $data['name']));
        $env->setType(\App\Enum\EnvironmentType::from($data['type'] ?? 'development'));
        $env->setColor($data['color'] ?? '#185FA5');
        $env->setDescription($data['description'] ?? null);
        $env->setActive(true);

        // Config Kubernetes optionnelle
        if (!empty($data['kubernetes_api_url'])) {
            $env->setKubernetesApiUrl($data['kubernetes_api_url']);
            $env->setKubeconfig($data['kubeconfig'] ?? null);
            $env->setKubernetesNamespace($data['kubernetes_namespace'] ?? 'default');
            $env->setKubernetesEnabled(true);
        }

        // Associer le créateur comme propriétaire
        $envUser = new EnvironmentUser();
        $envUser->setEnvironment($env);
        $envUser->setUser($createdBy);
        $envUser->setRole(UserEnvironmentRole::OWNER);
        $envUser->setGrantedBy($createdBy);

        $this->em->persist($env);
        $this->em->persist($envUser);
        $this->em->flush();

        return $env;
    }

    /**
     * Ajoute un utilisateur LDAP à un environnement.
     * Règle: max 1 utilisateur LOCAL (non-LDAP) par environnement (hors superadmin).
     */
    public function addUserToEnvironment(
        Environment         $env,
        CompanyUser         $user,
        UserEnvironmentRole $role,
        CompanyUser         $grantedBy
    ): EnvironmentUser {
        // Vérifier si l'utilisateur a déjà accès
        $existing = $this->em->getRepository(EnvironmentUser::class)->findOneBy([
            'environment' => $env,
            'user'        => $user,
        ]);
        if ($existing) {
            $existing->setRole($role);
            $existing->setActive(true);
            $this->em->flush();
            return $existing;
        }

        // Règle: max 1 utilisateur local non-LDAP par environnement
        if ($user->isLocal() && !$user->isSuperAdmin()) {
            $localCount = $this->countLocalUsersInEnvironment($env);
            if ($localCount >= 1) {
                throw new \RuntimeException(
                    "Un seul utilisateur local (non-LDAP) est autorisé par environnement. " .
                    "Utilisez un compte LDAP ou supprimez l'utilisateur local existant."
                );
            }
        }

        $envUser = new EnvironmentUser();
        $envUser->setEnvironment($env);
        $envUser->setUser($user);
        $envUser->setRole($role);
        $envUser->setGrantedBy($grantedBy);

        $this->em->persist($envUser);
        $this->em->flush();

        return $envUser;
    }

    private function countLocalUsersInEnvironment(Environment $env): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(eu.id)')
            ->from(EnvironmentUser::class, 'eu')
            ->join('eu.user', 'u')
            ->where('eu.environment = :env')
            ->andWhere('u.type = :type')
            ->andWhere('eu.active = true')
            ->setParameter('env', $env)
            ->setParameter('type', CompanyUser::TYPE_LOCAL)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function sanitizeSlug(string $input): string
    {
        $slug = strtolower(trim($input));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
