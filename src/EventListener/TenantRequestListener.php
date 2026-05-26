<?php

namespace App\EventListener;

use App\Entity\CompanyUser;
use App\Entity\Environment;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Middleware qui détecte l'environnement courant depuis l'URL
 * et l'injecte dans le TenantContext pour toute la requête.
 * Initialise le contexte tenant pour chaque requête.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class TenantRequestListener
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();

        // Injecter l'utilisateur courant dans le TenantContext
        $user = $this->security->getUser();
        if ($user instanceof CompanyUser) {
            $this->tenant->setUser($user);
            if ($user->getCompany()) {
                $this->tenant->setTenantId(
                    (string)$user->getCompany()->getId()
                );
            }
        }

        // Détecter l'env depuis le paramètre de requête ?env=ID
        $envId = $request->query->getInt('env', 0);

        if (!$envId) {
            $envId = (int)$request->attributes->get('id', 0);
        }
        if ($envId && $this->tenant->getUser()) {
            $env = $this->em->find(Environment::class, $envId);
            if ($env && $this->tenant->canAccessEnvironment($env)) {
                $this->tenant->setCurrentEnvironment($env);
                $request->attributes->set('_current_env', $env);
            }
        }
    }
}
