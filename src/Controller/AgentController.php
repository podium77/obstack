<?php
namespace App\Controller;

use App\Entity\AgentToken;
use App\Entity\Environment;
use App\Form\AgentTokenType;
use App\Repository\AgentTokenRepository;
use App\Repository\EnvironmentRepository;
use App\Service\AgentInstallScriptGenerator;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent', name: 'app_agent_')]
#[IsGranted('ROLE_OPERATOR')]
class AgentController extends AbstractController
{
    public function __construct(
        private readonly TenantContext               $tenant,
        private readonly AgentTokenRepository        $tokenRepo,
        private readonly EnvironmentRepository       $envRepo,
        private readonly AgentInstallScriptGenerator $scriptGenerator,
        private readonly EntityManagerInterface      $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $environments = $this->tenant->getAccessibleEnvironments();
        $tokens = [];

        foreach ($environments as $env) {
            $tokens = array_merge($tokens, $this->tokenRepo->findByEnvironment($env));
        }

        return $this->render('agent/index.html.twig', [
            'tokens' => $tokens,
            'environments' => $environments,
        ]);
    }

    #[Route('/new/{envId}', name: 'new', requirements: ['envId' => '\d+'])]
    public function newToken(Request $request, int $envId): Response
    {
        $env = $this->envRepo->find($envId);
        if (!$env || !$this->tenant->canAccessEnvironment($env)) {
            throw $this->createAccessDeniedException();
        }

        $token = new AgentToken();
        $token->setEnvironment($env);
        $token->setName('Nouveau token - ' . $env->getName());
        $token->regenerateToken();

        $form = $this->createForm(AgentTokenType::class, $token, [
            'environments' => [$env],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer le script d'installation
            $script = $this->scriptGenerator->generateForToken($token);
            $token->setInstallScript($script);

            $this->em->persist($token);
            $this->em->flush();

            $this->addFlash('success', 'Token créé avec succès.');
            return $this->redirectToRoute('app_agent_index');
        }

        return $this->render('agent/form.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
            'environment' => $env,
        ]);
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeToken(int $id, Request $request): Response
    {
        $token = $this->tokenRepo->find($id);
        if (!$token || !$this->tenant->canAccessEnvironment($token->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('revoke_token_' . $token->getId(), $request->request->get('_token'))) {
            $token->revoke();
            $this->em->flush();
            $this->addFlash('success', 'Token révoqué.');
        }

        return $this->redirectToRoute('app_agent_index');
    }

    #[Route('/{id}/regenerate-script', name: 'regenerate_script', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function regenerateScript(int $id, Request $request): Response
    {
        $token = $this->tokenRepo->find($id);
        if (!$token || !$this->tenant->canAccessEnvironment($token->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('regenerate_script_' . $token->getId(), $request->request->get('_token'))) {
            $script = $this->scriptGenerator->generateForToken($token);
            $token->setInstallScript($script);
            $this->em->flush();
            $this->addFlash('success', 'Script régénéré.');
        }

        return $this->redirectToRoute('app_agent_index');
    }
}
