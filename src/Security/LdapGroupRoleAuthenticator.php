<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Routing\RouterInterface;
use Psr\Log\LoggerInterface;

/**
 * Authentificateur LDAP avec mappage automatique des groupes vers les rôles Symfony.
 *
 * Groupes LDAP reconnus:
 *   cn=obstack-admins,ou=groups,dc=company,dc=local  → ROLE_ADMIN
 *   cn=obstack-operators,ou=groups,...                → ROLE_OPERATOR
 *   cn=obstack-users,ou=groups,...                    → ROLE_USER (défaut)
 */
class LdapGroupRoleAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly Ldap            $ldap,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
        private readonly string          $ldapBaseDn,
        private readonly string          $ldapBindDn,
        private readonly string          $ldapBindPassword,
        private readonly string          $ldapUserBaseDn,
        private readonly string          $ldapGroupBaseDn,
        private readonly string          $ldapAdminGroup,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->attributes->get('_route') === 'app_login';
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('username', '');
        $password = $request->request->get('password', '');
        $csrf     = $request->request->get('_csrf_token', '');

        return new Passport(
            new UserBadge($username, function (string $identifier) use ($password) {
                return $this->loadLdapUser($identifier, $password);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrf),
                new RememberMeBadge(),
            ]
        );
    }

    private function loadLdapUser(string $username, string $password): LdapUser
    {
        try {
            // Connexion en lecture pour chercher l'utilisateur
            $this->ldap->bind($this->ldapBindDn, $this->ldapBindPassword);

            $query  = $this->ldap->query(
                $this->ldapUserBaseDn,
                "(&(objectClass=inetOrgPerson)(uid={$username}))"
            );
            $result = $query->execute();

            if (count($result) === 0) {
                throw new AuthenticationException("Utilisateur LDAP '{$username}' non trouvé.");
            }

            $entry      = $result[0];
            $displayName = $entry->getAttribute('cn')[0] ?? $username;
            $email       = $entry->getAttribute('mail')[0] ?? null;
            $dn          = $entry->getDn();

            // Vérifier le mot de passe en tentant un bind avec les credentials utilisateur
            $this->ldap->bind($dn, $password);

            // Récupérer les groupes de l'utilisateur
            $roles = $this->fetchUserRoles($username);

            $this->logger->info("Connexion LDAP réussie: {$username} — rôles: " . implode(', ', $roles));

            return new LdapUser($username, $displayName, $email, $roles, $dn);

        } catch (LdapException $e) {
            $this->logger->warning("Erreur LDAP pour {$username}: {$e->getMessage()}");
            throw new AuthenticationException("Erreur de connexion LDAP: {$e->getMessage()}");
        }
    }

    /**
     * Récupère les rôles Symfony depuis les groupes LDAP de l'utilisateur.
     */
    private function fetchUserRoles(string $username): array
    {
        $roles = ['ROLE_USER'];

        try {
            // Chercher les groupes dont l'utilisateur est membre
            $groupQuery = $this->ldap->query(
                $this->ldapGroupBaseDn,
                "(&(objectClass=groupOfNames)(member=uid={$username},{$this->ldapUserBaseDn}))"
            );
            $groups = $groupQuery->execute();

            foreach ($groups as $group) {
                $groupDn   = $group->getDn();
                $groupName = strtolower($group->getAttribute('cn')[0] ?? '');

                if (str_contains($groupDn, 'obstack-admins')
                    || str_contains($groupName, 'admin')
                ) {
                    $roles[] = 'ROLE_ADMIN';
                }

                if (str_contains($groupDn, 'obstack-operators')
                    || str_contains($groupName, 'operator')
                ) {
                    $roles[] = 'ROLE_OPERATOR';
                }
            }

            // Fallback: chercher via memberOf attribute
            $this->ldap->bind($this->ldapBindDn, $this->ldapBindPassword);
            $userQuery = $this->ldap->query(
                $this->ldapUserBaseDn,
                "(uid={$username})",
                ['filter' => ['memberOf']]
            );
            $users = $userQuery->execute();

            if (count($users) > 0) {
                $memberOf = $users[0]->getAttribute('memberOf') ?? [];
                foreach ($memberOf as $groupDn) {
                    if (str_contains($groupDn, 'admin')) {
                        $roles[] = 'ROLE_ADMIN';
                    }
                    if (str_contains($groupDn, 'operator')) {
                        $roles[] = 'ROLE_OPERATOR';
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->logger->warning("Impossible de récupérer les groupes LDAP: {$e->getMessage()}");
        }

        return array_unique($roles);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $targetPath = $request->getSession()->get('_security.' . $firewallName . '.target_path');

        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->set('auth_error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
