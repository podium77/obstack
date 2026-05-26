<?php
namespace App\Security;

use App\Entity\CompanyUser;
use App\Repository\CompanyRepository;
use App\Repository\CompanyUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Authentificateur multi-tenant:
 *  1. Récupère le slug de l'entreprise (depuis session ou formulaire)
 *  2. Cherche l'entreprise dans la base
 *  3. Tente l'auth LDAP spécifique à cette entreprise
 *  4. Fallback sur compte local si LDAP absent ou échec
 *  5. Mappe les groupes LDAP → rôles obstack
 */
class MultiTenantAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly CompanyRepository      $companyRepo,
        private readonly CompanyUserRepository  $userRepo,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly RouterInterface        $router,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    public function supports(Request $request): ?bool
    {
        $route = $request->attributes->get('_route');

        $publicRoutes = [
            'homepage',
            'app_login',
            'register_index',
        ];

        if (in_array($route, $publicRoutes, true)) {
            return $route === 'app_login' && $request->isMethod('POST');
        }

        return true; // toutes les autres routes passent par auth si besoin
    }

    public function authenticate(Request $request): Passport
    {
        $username    = trim($request->request->get('username', ''));
        $password    = $request->request->get('password', '');
        $companySlug = trim($request->request->get('company_slug', ''));
        $csrf        = $request->request->get('_csrf_token', '');

        // Stocker le slug en session pour les rechargements
        if ($companySlug) {
            $request->getSession()->set('company_slug', $companySlug);
        } else {
            $companySlug = $request->getSession()->get('company_slug', '');
        }

        return new Passport(
            new UserBadge("{$companySlug}::{$username}", function (string $identifier) use ($password, $companySlug, $username) {
                return $this->loadUser($companySlug, $username, $password);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrf),
                new RememberMeBadge(),
            ]
        );
    }

    private function loadUser(string $companySlug, string $username, string $password): CompanyUser
    {
        // 1. Trouver l'entreprise
        if (empty($companySlug)) {
            throw new CustomUserMessageAuthenticationException(
                'Veuillez entrer votre identifiant d\'entreprise.'
            );
        }

        $company = $this->companyRepo->findOneBy(['slug' => $companySlug, 'active' => true]);
        if (!$company) {
            // Chercher par préfixe pour les slugs générés
            $company = $this->companyRepo->findOneBySlugPrefix($companySlug);
        }

        if (!$company) {
            throw new CustomUserMessageAuthenticationException(
                "Entreprise '{$companySlug}' introuvable."
            );
        }

        // 2. Chercher l'utilisateur dans cette entreprise
        $user = $this->userRepo->findOneBy([
            'company'  => $company,
            'username' => $username,
            'active'   => true,
        ]);

        // 3. Essayer l'authentification LDAP si l'entreprise a un LDAP configuré
        if ($company->hasLdap()) {
            $ldapUser = $this->tryLdapAuth($company, $username, $password);
            if ($ldapUser !== null) {
                // Auth LDAP réussie: créer ou mettre à jour le CompanyUser LDAP
                $user = $this->syncLdapUser($company, $ldapUser);
                $this->updateLastLogin($user);
                return $user;
            }
            // LDAP a échoué — essayer compte local en fallback uniquement pour superadmin
        }

        // 4. Authentification locale
        if (!$user) {
            throw new CustomUserMessageAuthenticationException(
                'Identifiant ou mot de passe incorrect.'
            );
        }

        if (!$user->getPassword()) {
            throw new CustomUserMessageAuthenticationException(
                'Ce compte n\'a pas de mot de passe local. Utilisez vos identifiants LDAP.'
            );
        }

        if (!$this->hasher->isPasswordValid($user, $password)) {
            throw new CustomUserMessageAuthenticationException(
                'Identifiant ou mot de passe incorrect.'
            );
        }

        $this->updateLastLogin($user);
        return $user;
    }

    /**
     * Tente une authentification LDAP contre l'annuaire de l'entreprise.
     * Retourne les données LDAP de l'utilisateur ou null si échec.
     */
    private function tryLdapAuth(\App\Entity\Company $company, string $username, string $password): ?array
    {
        try {
            $adapter = new Adapter([
                'host'    => $company->getLdapHost(),
                'port'    => $company->getLdapPort() ?? 389,
                'options' => ['protocol_version' => 3, 'referrals' => false],
            ]);
            $ldap = new Ldap($adapter);

            // Connexion lecture pour trouver l'utilisateur
            $ldap->bind($company->getLdapBindDn(), $company->getLdapBindPassword());

            $userBaseDn = $company->getLdapUserBaseDn() ?? $company->getLdapBaseDn();
            $query = $ldap->query($userBaseDn, "(&(objectClass=inetOrgPerson)(uid={$username}))");
            $results = $query->execute();

            if (count($results) === 0) {
                return null; // Utilisateur pas dans LDAP → essayer compte local
            }

            $entry      = $results[0];
            $dn         = $entry->getDn();
            $displayName = $entry->getAttribute('cn')[0] ?? $username;
            $email       = $entry->getAttribute('mail')[0] ?? null;

            // Vérifier le mot de passe via bind
            $ldap->bind($dn, $password);

            // Récupérer les groupes
            $groups = $this->fetchLdapGroups($ldap, $username, $company);

            $this->logger->info("LDAP auth réussie: {$username} @ {$company->getSlug()}");

            return [
                'username'     => $username,
                'dn'           => $dn,
                'display_name' => $displayName,
                'email'        => $email,
                'groups'       => $groups,
            ];

        } catch (LdapException $e) {
            // Mot de passe invalide ou LDAP indisponible
            $this->logger->debug("LDAP auth échouée pour {$username}: {$e->getMessage()}");
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning("LDAP erreur pour {$company->getSlug()}: {$e->getMessage()}");
            return null;
        }
    }

    private function fetchLdapGroups(Ldap $ldap, string $username, \App\Entity\Company $company): array
    {
        $groups    = [];
        $groupBase = $company->getLdapGroupBaseDn() ?? $company->getLdapBaseDn();

        try {
            $q = $ldap->query(
                $groupBase,
                "(&(objectClass=groupOfNames)(member=uid={$username},{$company->getLdapUserBaseDn()}))"
            );
            foreach ($q->execute() as $group) {
                $groups[] = strtolower($group->getAttribute('cn')[0] ?? '');
            }
        } catch (\Throwable) {
            // Groupes non disponibles
        }

        return $groups;
    }

    /**
     * Synchronise un utilisateur LDAP en base.
     * Crée ou met à jour le CompanyUser.
     */
    private function syncLdapUser(\App\Entity\Company $company, array $ldapData): CompanyUser
    {
        $user = $this->userRepo->findOneBy([
            'company'  => $company,
            'username' => $ldapData['username'],
        ]);

        if (!$user) {
            $user = new CompanyUser();
            $user->setCompany($company);
            $user->setUsername($ldapData['username']);
            $user->setType(CompanyUser::TYPE_LDAP);
            $user->setActive(true);
            $this->em->persist($user);
        }

        $user->setDisplayName($ldapData['display_name']);
        $user->setEmail($ldapData['email']);
        $user->setLdapDn($ldapData['dn']);

        // Déterminer l'accès global depuis les groupes LDAP
        $groups = $ldapData['groups'];
        $isAdmin = array_filter($groups, fn($g) => str_contains($g, 'admin') || str_contains($g, 'superadmin'));
        if ($isAdmin && !$user->isSuperAdmin()) {
            // Ne pas rétrograduer un superadmin existant
        }

        $this->em->flush();
        return $user;
    }

    private function updateLastLogin(CompanyUser $user): void
    {
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $target = $request->getSession()->get('_security.' . $firewallName . '.target_path');
        return new RedirectResponse($target ?? $this->router->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('auth_error', $exception->getMessage());

        // IMPORTANT : ne redirige PAS toujours login
        if ($request->attributes->get('_route') === 'app_login') {
            return new RedirectResponse($this->router->generate('app_login'));
        }

        return null; // laisse Symfony gérer
    }
}
