<?php

namespace App\Controller\Admin;

use App\Entity\AIInfrastructure;
use App\Entity\LocalCompany;
use App\Form\AI\AIMode1ConfigurationType;
use App\Repository\AIInfrastructureRepository;
use App\Service\AI\AiConfigurationService;
use App\Service\AI\AiConfigurationException;
use App\Service\AI\OllamaException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * AIConfigurationController — Gestion de l'intégration IA centralisée
 *
 * Permissions :
 * - ROLE_GLOBAL_ADMIN : accès complet (configuration globale)
 * - ROLE_ADMIN : accès limité (configuration par entreprise)
 *
 * Principes :
 * - Configuration MANUELLE uniquement (pas d'autodétection)
 * - Validation stricte des entrées
 * - Logs détaillés de toutes les opérations
 */
#[Route('/admin/ai', name: 'admin_ai_')]
#[IsGranted('ROLE_GLOBAL_ADMIN')]
class AIConfigurationController extends AbstractController
{
    public function __construct(
        private AiConfigurationService $aiConfigService,
        private AIInfrastructureRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Affiche le tableau de bord de configuration IA
     *
     * @return Response
     */
    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $infrastructures = $this->repository->findAllActive();

        return $this->render('admin/ai/dashboard.html.twig', [
            'infrastructures' => $infrastructures,
        ]);
    }

    /**
     * Affiche le formulaire Mode 1 (IA Existante)
     *
     * @param Request $request
     * @return Response
     */
    #[Route('/mode1/configure', name: 'mode1_configure')]
    public function configureMode1(Request $request): Response
    {
        $form = $this->createForm(AIMode1ConfigurationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();

                // Enregistrer la configuration IA
                $config = [
                    'name' => $data['name'],
                    'provider' => $data['provider'],
                    'api_url' => $data['api_url'],
                    'api_key' => $data['api_key'] ?? null,
                    'model' => $data['model'],
                    'timeout_seconds' => $data['timeout_seconds'] ?? 30,
                ];

                $user = $this->getUser();
                $infrastructure = $this->aiConfigService->registerMode1($config, $user);

                $this->addFlash('success', sprintf(
                    'Infrastructure IA "%s" configurée avec succès (Modèle : %s)',
                    $infrastructure->getName(),
                    $infrastructure->getModel()
                ));

                return $this->redirectToRoute('admin_ai_dashboard');
            } catch (AiConfigurationException $e) {
                $this->addFlash('error', 'Erreur de configuration : ' . $e->getMessage());
            } catch (OllamaException $e) {
                $this->addFlash('error', 'Erreur de connexion à l\'API IA : ' . $e->getMessage());
            }
        }

        return $this->render('admin/ai/mode1_configure.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Endpoint API pour tester la connexion lors du remplissage du formulaire
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/mode1/test-connection', name: 'api_mode1_test', methods: ['POST'])]
    public function testConnectionMode1(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['api_url'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'URL API requise',
                ], 400);
            }

            if (empty($data['model'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Modèle requis',
                ], 400);
            }

            // Créer un client temporaire pour tester
            $config = [
                'name' => 'Test',
                'provider' => 'ollama',
                'api_url' => $data['api_url'],
                'api_key' => $data['api_key'] ?? null,
                'model' => $data['model'],
                'timeout_seconds' => $data['timeout_seconds'] ?? 30,
            ];

            $validator = new \App\Service\AI\AiConfigurationValidator($this->logger);
            $validationResult = $validator->validateMode1($config);

            if (!$validationResult->isValid()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Configuration invalide',
                    'errors' => $validationResult->getErrors(),
                ], 400);
            }

            // Test de connexion
            $client = new \App\Service\AI\OllamaClient(
                $this->container->get('http_client'),
                $this->logger,
                $data['api_url'],
                $data['api_key'] ?? null,
                $data['timeout_seconds'] ?? 30
            );

            if (!$client->testConnection()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de se connecter à l\'API IA',
                ]);
            }

            // Vérifier que le modèle est disponible
            $availableModels = $client->listModels();
            if (!in_array($data['model'], $availableModels)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Modèle non disponible sur le serveur',
                    'available_models' => $availableModels,
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'available_models' => $availableModels,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Affiche les détails d'une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure
     * @return Response
     */
    #[Route('/{id}', name: 'show')]
    public function show(AIInfrastructure $infrastructure): Response
    {
        return $this->render('admin/ai/show.html.twig', [
            'infrastructure' => $infrastructure,
        ]);
    }

    /**
     * Tester la connexion à une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure
     * @return JsonResponse
     */
    #[Route('/{id}/test-connection', name: 'test_connection', methods: ['POST'])]
    public function testConnection(AIInfrastructure $infrastructure): JsonResponse
    {
        try {
            $isConnected = $this->aiConfigService->testConnection($infrastructure);

            return $this->json([
                'success' => true,
                'connected' => $isConnected,
                'message' => $isConnected ? 'Connexion réussie' : 'Connexion échouée',
                'last_health_check' => $infrastructure->getLastHealthCheckAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure
     * @return Response
     */
    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    public function activate(AIInfrastructure $infrastructure): Response
    {
        try {
            $this->aiConfigService->activate($infrastructure);
            $this->addFlash('success', 'Infrastructure IA activée');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_ai_show', ['id' => $infrastructure->getId()]);
    }

    /**
     * Désactiver une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure
     * @return Response
     */
    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(AIInfrastructure $infrastructure): Response
    {
        $this->aiConfigService->deactivate($infrastructure);
        $this->addFlash('success', 'Infrastructure IA désactivée');

        return $this->redirectToRoute('admin_ai_show', ['id' => $infrastructure->getId()]);
    }

    /**
     * Supprimer une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure
     * @return Response
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(AIInfrastructure $infrastructure): Response
    {
        $this->entityManager->remove($infrastructure);
        $this->entityManager->flush();

        $this->addFlash('success', 'Infrastructure IA supprimée');

        return $this->redirectToRoute('admin_ai_dashboard');
    }
}
