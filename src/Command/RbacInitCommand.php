<?php

namespace App\Command;

use App\Entity\Permission;
use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande pour initialiser le système RBAC avec les rôles et permissions par défaut.
 */
#[AsCommand(
    name: 'app:rbac:init',
    description: 'Initialiser le système RBAC avec les rôles et permissions par défaut',
)]
class RbacInitCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Initialisation du système RBAC...</info>');

        try {
            // Créer les permissions
            $this->createPermissions($output);

            // Créer les rôles
            $this->createRoles($output);

            $output->writeln('<info>✓ Initialisation RBAC terminée avec succès!</info>');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Erreur: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function createPermissions(OutputInterface $output): void
    {
        $permissions = [
            // Permissions globales
            [
                'code' => Permission::ADMIN_ACCESS_CONSOLE,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Accès à la console d\'administration système',
            ],
            [
                'code' => Permission::ADMIN_MANAGE_COMPANIES,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Gestion des entreprises',
            ],
            [
                'code' => Permission::ADMIN_MANAGE_USERS,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Gestion des utilisateurs',
            ],
            [
                'code' => Permission::ADMIN_MANAGE_DATABASE_CONNECTIONS,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Gestion des connexions de bases de données',
            ],
            [
                'code' => Permission::ADMIN_EXECUTE_QUERIES,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Exécution de requêtes personnalisées',
            ],
            [
                'code' => Permission::ADMIN_VIEW_AUDIT,
                'scope' => 'global',
                'category' => 'admin',
                'description' => 'Consultation de l\'historique d\'audit',
            ],

            // Permissions au niveau entreprise
            [
                'code' => Permission::COMPANY_MANAGE_USERS,
                'scope' => 'company',
                'category' => 'company',
                'description' => 'Gestion des utilisateurs de l\'entreprise',
            ],
            [
                'code' => Permission::COMPANY_MANAGE_ENVIRONMENTS,
                'scope' => 'company',
                'category' => 'company',
                'description' => 'Gestion des environnements',
            ],
            [
                'code' => Permission::COMPANY_MANAGE_APPLICATIONS,
                'scope' => 'company',
                'category' => 'company',
                'description' => 'Gestion des applications',
            ],
            [
                'code' => Permission::COMPANY_VIEW_ANALYTICS,
                'scope' => 'company',
                'category' => 'company',
                'description' => 'Consultation des analytiques',
            ],

            // Permissions au niveau environnement
            [
                'code' => Permission::ENVIRONMENT_MANAGE_AGENTS,
                'scope' => 'environment',
                'category' => 'environment',
                'description' => 'Gestion des agents',
            ],
            [
                'code' => Permission::ENVIRONMENT_VIEW_APPLICATIONS,
                'scope' => 'environment',
                'category' => 'environment',
                'description' => 'Consultation des applications',
            ],
            [
                'code' => Permission::ENVIRONMENT_MANAGE_USERS,
                'scope' => 'environment',
                'category' => 'environment',
                'description' => 'Gestion des utilisateurs de l\'environnement',
            ],

            // Permissions au niveau ressource
            [
                'code' => Permission::RESOURCE_CREATE_APPLICATION,
                'scope' => 'resource',
                'category' => 'application',
                'description' => 'Créer une application',
            ],
            [
                'code' => Permission::RESOURCE_MODIFY_APPLICATION,
                'scope' => 'resource',
                'category' => 'application',
                'description' => 'Modifier sa propre application',
            ],
            [
                'code' => Permission::RESOURCE_DELETE_APPLICATION,
                'scope' => 'resource',
                'category' => 'application',
                'description' => 'Supprimer sa propre application',
            ],
        ];

        foreach ($permissions as $data) {
            if (!$this->permissionRepository->findByCode($data['code'])) {
                $permission = new Permission();
                $permission->setCode($data['code']);
                $permission->setScope($data['scope']);
                $permission->setCategory($data['category']);
                $permission->setDescription($data['description']);

                $this->em->persist($permission);
                $output->writeln('<comment>  + Permission créée: ' . $data['code'] . '</comment>');
            }
        }

        $this->em->flush();
    }

    private function createRoles(OutputInterface $output): void
    {
        // Rôle USER
        if (!$this->roleRepository->findByName(Role::USER)) {
            $userRole = new Role();
            $userRole->setName(Role::USER);
            $userRole->setScope('resource');
            $userRole->setDescription('Utilisateur standard avec accès limité');

            // Assigner les permissions
            foreach ([
                Permission::RESOURCE_CREATE_APPLICATION,
                Permission::RESOURCE_MODIFY_APPLICATION,
                Permission::RESOURCE_DELETE_APPLICATION,
            ] as $code) {
                $permission = $this->permissionRepository->findByCode($code);
                if ($permission) {
                    $userRole->addPermission($permission);
                }
            }

            $this->em->persist($userRole);
            $output->writeln('<comment>  + Rôle créé: ' . Role::USER . '</comment>');
        }

        // Rôle COMPANY_ADMIN
        if (!$this->roleRepository->findByName(Role::COMPANY_ADMIN)) {
            $companyAdminRole = new Role();
            $companyAdminRole->setName(Role::COMPANY_ADMIN);
            $companyAdminRole->setScope('company');
            $companyAdminRole->setDescription('Administrateur d\'entreprise');

            // Assigner les permissions
            foreach ([
                Permission::COMPANY_MANAGE_USERS,
                Permission::COMPANY_MANAGE_ENVIRONMENTS,
                Permission::COMPANY_MANAGE_APPLICATIONS,
                Permission::COMPANY_VIEW_ANALYTICS,
            ] as $code) {
                $permission = $this->permissionRepository->findByCode($code);
                if ($permission) {
                    $companyAdminRole->addPermission($permission);
                }
            }

            // Hériter du rôle USER
            $userRole = $this->roleRepository->findByName(Role::USER);
            if ($userRole) {
                $companyAdminRole->addInheritedRole($userRole);
            }

            $this->em->persist($companyAdminRole);
            $output->writeln('<comment>  + Rôle créé: ' . Role::COMPANY_ADMIN . '</comment>');
        }

        // Rôle GLOBAL_ADMIN
        if (!$this->roleRepository->findByName(Role::GLOBAL_ADMIN)) {
            $globalAdminRole = new Role();
            $globalAdminRole->setName(Role::GLOBAL_ADMIN);
            $globalAdminRole->setScope('global');
            $globalAdminRole->setDescription('Administrateur global du système');

            // Assigner toutes les permissions globales
            foreach ($this->permissionRepository->findGlobalPermissions() as $permission) {
                $globalAdminRole->addPermission($permission);
            }

            // Hériter du rôle COMPANY_ADMIN
            $companyAdminRole = $this->roleRepository->findByName(Role::COMPANY_ADMIN);
            if ($companyAdminRole) {
                $globalAdminRole->addInheritedRole($companyAdminRole);
            }

            $this->em->persist($globalAdminRole);
            $output->writeln('<comment>  + Rôle créé: ' . Role::GLOBAL_ADMIN . '</comment>');
        }

        $this->em->flush();
    }
}
