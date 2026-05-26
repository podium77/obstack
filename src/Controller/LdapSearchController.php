<?php
namespace App\Controller;

use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_SUPERADMIN')]
class LdapSearchController extends AbstractController
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Recherche des utilisateurs dans l'annuaire LDAP de l'entreprise.
     * Utilisé par le formulaire d'import d'utilisateurs.
     */
    #[Route('/ldap-search', name: 'ldap_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query   = trim($request->query->get('q', ''));
        $company = $this->tenant->getCompany();

        if (!$company && !$this->tenant->isSuperAdmin()) {
            throw $this->createAccessDeniedException();
        }

        if (!$company->hasLdap()) {
            return $this->json(['error' => 'LDAP non configuré.'], 400);
        }

        if (strlen($query) < 2) {
            return $this->json(['users' => []]);
        }

        try {
            $adapter = new Adapter([
                'host'    => $company->getLdapHost(),
                'port'    => $company->getLdapPort() ?? 389,
                'options' => ['protocol_version' => 3, 'referrals' => false],
            ]);
            $ldap = new Ldap($adapter);
            $ldap->bind($company->getLdapBindDn(), $company->getLdapBindPassword());

            $userBase = $company->getLdapUserBaseDn() ?? $company->getLdapBaseDn();

            // Recherche par uid, cn (nom) ou mail
            $filter = "(&(objectClass=inetOrgPerson)(|(uid=*{$query}*)(cn=*{$query}*)(mail=*{$query}*)))";
            $results = $ldap->query($userBase, $filter)->execute();

            $users = [];
            foreach ($results as $entry) {
                $uid         = $entry->getAttribute('uid')[0]  ?? '';
                $cn          = $entry->getAttribute('cn')[0]   ?? $uid;
                $mail        = $entry->getAttribute('mail')[0] ?? null;
                $dn          = $entry->getDn();

                // Vérifier que l'utilisateur n'existe pas déjà dans la plateforme
                $users[] = [
                    'uid'         => $uid,
                    'displayName' => $cn,
                    'email'       => $mail,
                    'dn'          => $dn,
                ];
            }

            return $this->json([
                'users' => array_slice($users, 0, 20), // Limiter à 20 résultats
                'total' => count($users),
            ]);

        } catch (\Throwable $e) {
            $this->logger->warning("LDAP search failed: {$e->getMessage()}");
            return $this->json(['error' => 'Erreur de recherche LDAP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Récupère les groupes LDAP disponibles dans l'annuaire.
     */
    #[Route('/ldap-groups', name: 'ldap_groups', methods: ['GET'])]
    public function groups(): JsonResponse
    {
        $company = $this->tenant->getCompany();

        if (!$company && !$this->tenant->isSuperAdmin()) {
            throw $this->createAccessDeniedException();
        }

        if (!$company->hasLdap()) {
            return $this->json(['groups' => []]);
        }

        try {
            $adapter = new Adapter([
                'host'    => $company->getLdapHost(),
                'port'    => $company->getLdapPort() ?? 389,
                'options' => ['protocol_version' => 3, 'referrals' => false],
            ]);
            $ldap = new Ldap($adapter);
            $ldap->bind($company->getLdapBindDn(), $company->getLdapBindPassword());

            $groupBase = $company->getLdapGroupBaseDn() ?? $company->getLdapBaseDn();
            $results   = $ldap->query($groupBase, '(objectClass=groupOfNames)')->execute();

            $groups = [];
            foreach ($results as $group) {
                $groups[] = [
                    'dn'      => $group->getDn(),
                    'cn'      => $group->getAttribute('cn')[0] ?? '',
                    'members' => count($group->getAttribute('member') ?? []),
                ];
            }

            return $this->json(['groups' => $groups]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
