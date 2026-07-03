<?php

namespace App\Controller\Admin;

use App\Entity\AIDeployment;
use App\Form\AI\AIMode2ConfigurationType;
use App\Message\AIDeploymentMessage;
use App\Repository\AIDeploymentRepository;
use App\Service\AI\AIDeploymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AIMode2Controller
 * 
 * Gère les routes pour Mode 2 (Déploiement Automatisé)
 */
#[Route('/admin/ai/mode2', name: 'admin_ai_mode2_')]
#[IsGranted('ROLE_GLOBAL_ADMIN')]
class AIMode2Controller extends AbstractController
{
    public function __construct(
        private AIDeploymentService $deploymentService,
        private AIDeploymentRepository $repository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {}

    /**
     * GET /admin/ai/mode2/wizard
     * Wizard multi-étapes pour configuration Mode 2
     */
    #[Route('/wizard', name: 'wizard', methods: ['GET', 'POST'])]
    public function wizard(Request $request): Response
    {
        // Récupérer ou initialiser la session wizard
        $session = $request->getSession();
        $wizardData = $session->get('ai_mode2_wizard', []);
        $currentStep = (int)$request->query->get('step', 1);

        if ($request->isMethod('POST')) {
            $currentStep = (int)$request->request->get('step', 1) + 1;

            // Sauvegarder les données du formulaire
            $wizardData = array_merge($wizardData, $request->request->all());
            $session->set('ai_mode2_wizard', $wizardData);

            if ($currentStep <= 5) {
                // Aller à l'étape suivante
                return $this->redirectToRoute('admin_ai_mode2_wizard', ['step' => $currentStep]);
            } else {
                // Déploiement terminé, passer à la confirmation
                return $this->redirectToRoute('admin_ai_mode2_confirm');
            }
        }

        // Créer le formulaire pour l'étape actuelle
        $form = $this->createForm(AIMode2ConfigurationType::class, $wizardData, [
            'step' => $currentStep
        ]);

        return $this->render('admin/ai/mode2_wizard.html.twig', [
            'form' => $form->createView(),
            'currentStep' => $currentStep,
            'wizardData' => $wizardData,
        ]);
    }

    /**
     * GET /admin/ai/mode2/confirm
     * Confirmation et résumé avant déploiement
     */
    #[Route('/confirm', name: 'confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $session = $request->getSession();
        $wizardData = $session->get('ai_mode2_wizard', []);

        if (empty($wizardData)) {
            return $this->redirectToRoute('admin_ai_mode2_wizard');
        }

        if ($request->isMethod('POST')) {
            // Valider la configuration
            $validation = $this->deploymentService->validateMode2Config($wizardData);

            if (!$validation['valid']) {
                $this->addFlash('error', 'Configuration invalide: ' . implode(', ', $validation['errors']));
                return $this->redirectToRoute('admin_ai_mode2_wizard');
            }

            try {
                // Créer l'entité de déploiement
                $deployment = $this->deploymentService->startDeployment(
                    $wizardData,
                    $this->getUser(),
                    $this->getUser()->getCompany()
                );

                // Dispatcher le message de déploiement (asynchrone)
                $this->messageBus->dispatch(new AIDeploymentMessage($deployment->getId()));

                // Nettoyer la session
                $session->remove('ai_mode2_wizard');

                $this->addFlash('success', 'Déploiement lancé ! Suivez la progression...');
                return $this->redirectToRoute('admin_ai_mode2_status', ['id' => $deployment->getId()]);

            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors du déploiement: ' . $e->getMessage());
                return $this->redirectToRoute('admin_ai_mode2_wizard');
            }
        }

        return $this->render('admin/ai/mode2_confirm.html.twig', [
            'wizardData' => $wizardData,
        ]);
    }

    /**
     * GET /admin/ai/mode2/status/{id}
     * Afficher l'état du déploiement en temps réel
     */
    #[Route('/status/{id}', name: 'status', methods: ['GET'])]
    public function status(AIDeployment $deployment): Response
    {
        $this->denyAccessUnlessGranted('view', $deployment);

        return $this->render('admin/ai/mode2_status.html.twig', [
            'deployment' => $deployment,
        ]);
    }

    /**
     * GET /admin/ai/mode2/status/{id}/json
     * Récupérer l'état du déploiement en JSON (pour polling AJAX)
     */
    #[Route('/status/{id}/json', name: 'status_json', methods: ['GET'])]
    public function statusJson(AIDeployment $deployment): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $deployment);

        return $this->json([
            'id' => $deployment->getId(),
            'name' => $deployment->getName(),
            'status' => $deployment->getStatus(),
            'vm_ip' => $deployment->getVmIp(),
            'log' => $deployment->getDeploymentLog(),
            'error' => $deployment->getErrorMessage(),
            'progress' => $this->calculateProgress($deployment),
        ]);
    }

    /**
     * POST /admin/ai/mode2/{id}/cancel
     * Annuler un déploiement en cours
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(AIDeployment $deployment): Response
    {
        $this->denyAccessUnlessGranted('delete', $deployment);

        try {
            $this->deploymentService->stopDeployment($deployment);
            $this->addFlash('success', 'Déploiement annulé');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'annulation: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_ai_mode2_status', ['id' => $deployment->getId()]);
    }

    /**
     * POST /admin/ai/mode2/{id}/delete
     * Supprimer un déploiement (cleanup)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(AIDeployment $deployment): Response
    {
        $this->denyAccessUnlessGranted('delete', $deployment);

        try {
            $this->deploymentService->deleteDeployment($deployment);
            $this->addFlash('success', 'Déploiement supprimé');
            return $this->redirectToRoute('admin_ai_mode2_list');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
            return $this->redirectToRoute('admin_ai_mode2_status', ['id' => $deployment->getId()]);
        }
    }

    /**
     * GET /admin/ai/mode2
     * Lister tous les déploiements Mode 2
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): Response
    {
        $deployments = $this->repository->findAll();

        return $this->render('admin/ai/mode2_list.html.twig', [
            'deployments' => $deployments,
        ]);
    }

    // Helper method
    private function calculateProgress(AIDeployment $deployment): int
    {
        return match ($deployment->getStatus()) {
            'pending' => 10,
            'deploying' => 40,
            'configuring' => 75,
            'deployed' => 100,
            'failed', 'stopped' => 0,
            default => 0,
        };
    }
}
