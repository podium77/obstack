<?php
namespace App\Controller;

use App\Entity\Alert;
use App\Repository\AlertRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/alerts', name: 'alert_')]
#[IsGranted('ROLE_USER')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly AlertRepository       $alertRepo,
        private readonly TenantContext         $tenant,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $severity     = $request->query->get('severity');
        $showResolved = $request->query->get('resolved', '0') === '1';
        $environments = $this->tenant->getAccessibleEnvironments();
        $envIds       = array_map(fn($e) => $e->getId(), $environments);

        // Construire la requête filtrée par environnements accessibles
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Alert::class, 'a')
            ->join('a.application', 'app')
            ->join('app.environment', 'e')
            ->where('e.id IN (:envIds)')
            ->setParameter('envIds', $envIds ?: [0])
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(200);

        if (!$showResolved) {
            $qb->andWhere('a.resolved = false');
        }

        if ($severity) {
            $qb->andWhere('a.severity = :severity')
               ->setParameter('severity', $severity);
        }

        $alerts      = $qb->getQuery()->getResult();
        $alertsBySev = $this->computeAlertStats($environments);

        return $this->render('alert/index.html.twig', [
            'alerts'         => $alerts,
            'filterSeverity' => $severity,
            'filterResolved' => $showResolved,
            'alertsBySev'    => $alertsBySev,
        ]);
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(Alert $alert): JsonResponse
    {
        if (!$this->tenant->canAccessEnvironment($alert->getApplication()->getEnvironment())) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if (!$this->tenant->canOperate($alert->getApplication()->getEnvironment())) {
            return $this->json(['error' => 'Rôle Opérateur requis.'], 403);
        }

        if (!$alert->isResolved()) {
            $alert->resolve();
            $this->em->flush();
        }

        return $this->json(['success' => true, 'message' => 'Alerte résolue.']);
    }

    #[Route('/resolve-all', name: 'resolve_all', methods: ['POST'])]
    public function resolveAll(Request $request): JsonResponse
    {
        $environments = $this->tenant->getAccessibleEnvironments();
        $envIds       = array_map(fn($e) => $e->getId(), $environments);

        // Vérifier que l'utilisateur peut admin au moins un env
        $canAdmin = false;
        foreach ($environments as $env) {
            if ($this->tenant->canAdmin($env)) {
                $canAdmin = true;
                break;
            }
        }

        if (!$canAdmin) {
            return $this->json(['error' => 'Rôle Admin requis.'], 403);
        }

        $alerts = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Alert::class, 'a')
            ->join('a.application', 'app')
            ->join('app.environment', 'e')
            ->where('e.id IN (:envIds)')
            ->andWhere('a.resolved = false')
            ->setParameter('envIds', $envIds ?: [0])
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($alerts as $alert) {
            $alert->resolve();
            $count++;
        }
        $this->em->flush();

        return $this->json(['success' => true, 'resolved' => $count]);
    }

    private function computeAlertStats(array $environments): array
    {
        if (empty($environments)) return [];

        $envIds = array_map(fn($e) => $e->getId(), $environments);
        $rows   = $this->em->createQueryBuilder()
            ->select('a.severity, COUNT(a.id) as cnt')
            ->from(Alert::class, 'a')
            ->join('a.application', 'app')
            ->join('app.environment', 'e')
            ->where('e.id IN (:envIds)')
            ->andWhere('a.resolved = false')
            ->groupBy('a.severity')
            ->setParameter('envIds', $envIds)
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['severity']->value] = (int)$row['cnt'];
        }
        return $stats;
    }
}
