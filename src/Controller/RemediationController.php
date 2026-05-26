<?php
namespace App\Controller;

use App\Entity\Application;
use App\Entity\RemediationPolicy;
use App\Enum\RemediationAction;
use App\Enum\TriggerMetric;
use App\Message\RemediationJobMessage;
use App\Repository\ApplicationRepository;
use App\Repository\RemediationLogRepository;
use App\Repository\RemediationPolicyRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/remediation', name: 'remediation_')]
#[IsGranted('ROLE_USER')]
class RemediationController extends AbstractController
{
    public function __construct(
        private readonly TenantContext               $tenant,
        private readonly RemediationLogRepository    $logRepo,
        private readonly RemediationPolicyRepository $policyRepo,
        private readonly ApplicationRepository       $appRepo,
        private readonly EntityManagerInterface      $em,
        private readonly MessageBusInterface         $bus,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        // Logs filtrés par environnements accessibles
        $environments = $this->tenant->getAccessibleEnvironments();
        $envIds       = array_map(fn($e) => $e->getId(), $environments);

        $logs = $this->em->createQueryBuilder()
            ->select('r')
            ->from(\App\Entity\RemediationLog::class, 'r')
            ->join('r.application', 'a')
            ->join('a.environment', 'e')
            ->where('e.id IN (:envIds)')
            ->setParameter('envIds', $envIds ?: [0])
            ->orderBy('r.executedAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        return $this->render('remediation/index.html.twig', [
            'recentLogs' => $logs,
            'actions'    => RemediationAction::cases(),
        ]);
    }

    #[Route('/log/{id}', name: 'log_show', requirements: ['id' => '\d+'])]
    public function logShow(\App\Entity\RemediationLog $log): Response
    {
        if (!$this->tenant->canAccessEnvironment($log->getApplication()->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }
        return $this->render('remediation/log_show.html.twig', ['log' => $log]);
    }

    /** Déclencher une remédiation manuelle */
    #[Route('/execute', name: 'execute', methods: ['POST'])]
    public function execute(Request $request): JsonResponse
    {
        $appId  = (int)$request->request->get('app_id');
        $action = $request->request->get('action');
        $app    = $this->appRepo->find($appId);

        if (!$app) {
            return $this->json(['error' => 'Application introuvable.'], 404);
        }

        // Vérifier accès à l'environnement ET rôle opérateur minimum
        if (!$this->tenant->canAccessEnvironment($app->getEnvironment())) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if (!$this->tenant->canOperate($app->getEnvironment())) {
            return $this->json(['error' => 'Rôle Opérateur requis pour déclencher des remédiations.'], 403);
        }

        try {
            $remAction = RemediationAction::from($action);
        } catch (\ValueError) {
            return $this->json(['error' => "Action inconnue: {$action}"], 400);
        }

        // Vérification CSRF pour actions destructives
        if ($remAction->requiresConfirmation()) {
            $token = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid("remediation_{$appId}_{$action}", $token)) {
                return $this->json(['error' => 'Token CSRF invalide.'], 403);
            }
        }

        $user        = $this->tenant->getUser();
        $triggeredBy = $user->getDisplayName() ?? $user->getUsername();

        $this->bus->dispatch(new RemediationJobMessage(
            applicationId: $appId,
            action:        $remAction,
            triggeredBy:   $triggeredBy,
        ));

        return $this->json([
            'success' => true,
            'message' => "\"{$remAction->getLabel()}\" mis en file d'attente pour {$app->getName()}.",
        ]);
    }

    // ─── Politiques de remédiation ────────────────────────────────────

    #[Route('/policy/new/{appId}', name: 'policy_new', requirements: ['appId' => '\d+'])]
    public function policyNew(int $appId, Request $request): Response
    {
        $app = $this->appRepo->find($appId);
        if (!$app || !$this->tenant->canAdmin($app->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        $policy = new RemediationPolicy();
        $policy->setApplication($app);
        return $this->policyForm($request, $policy, $app, true);
    }

    #[Route('/policy/{id}/edit', name: 'policy_edit', requirements: ['id' => '\d+'])]
    public function policyEdit(RemediationPolicy $policy, Request $request): Response
    {
        if (!$this->tenant->canAdmin($policy->getApplication()->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }
        return $this->policyForm($request, $policy, $policy->getApplication(), false);
    }

    #[Route('/policy/{id}/toggle', name: 'policy_toggle', methods: ['POST'])]
    public function policyToggle(RemediationPolicy $policy): JsonResponse
    {
        if (!$this->tenant->canAdmin($policy->getApplication()->getEnvironment())) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }
        $policy->setEnabled(!$policy->isEnabled());
        $this->em->flush();
        return $this->json(['enabled' => $policy->isEnabled()]);
    }

    #[Route('/policy/{id}/delete', name: 'policy_delete', methods: ['POST'])]
    public function policyDelete(RemediationPolicy $policy, Request $request): Response
    {
        if (!$this->tenant->canAdmin($policy->getApplication()->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }
        $appId = $policy->getApplication()->getId();
        if ($this->isCsrfTokenValid('delete_policy_' . $policy->getId(), $request->request->get('_token'))) {
            $this->policyRepo->remove($policy, true);
            $this->addFlash('success', "Politique supprimée.");
        }
        return $this->redirectToRoute('app_application_show', ['id' => $appId]);
    }

    private function policyForm(Request $request, RemediationPolicy $policy, Application $app, bool $isNew): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $policy->setName($data['name'] ?? '');
            $policy->setDescription($data['description'] ?? null);
            $policy->setTriggerMetric(TriggerMetric::from($data['trigger_metric']));
            $policy->setThreshold((float)($data['threshold'] ?? 80));
            $policy->setOperator($data['operator'] ?? 'gte');
            $policy->setAction(RemediationAction::from($data['action']));
            $policy->setAutoExecute(isset($data['auto_execute']));
            $policy->setCooldownMinutes((int)($data['cooldown'] ?? 30));
            $policy->setPriority((int)($data['priority'] ?? 100));
            $policy->setEnabled(isset($data['enabled']));
            $policy->setMaxConsecutiveExecutions((int)($data['max_consecutive'] ?? 3));
            $this->em->persist($policy);
            $this->em->flush();
            $this->addFlash('success', $isNew ? "Politique créée." : "Politique mise à jour.");
            return $this->redirectToRoute('app_application_show', ['id' => $app->getId()]);
        }

        return $this->render('remediation/policy_form.html.twig', [
            'policy'         => $policy,
            'application'    => $app,
            'isNew'          => $isNew,
            'actions'        => RemediationAction::cases(),
            'triggerMetrics' => TriggerMetric::cases(),
            'operators'      => [
                'gte' => '≥ supérieur ou égal',
                'gt'  => '> strictement supérieur',
                'lte' => '≤ inférieur ou égal',
                'lt'  => '< strictement inférieur',
                'eq'  => '= égal',
            ],
        ]);
    }
}
