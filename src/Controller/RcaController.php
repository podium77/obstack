<?php
namespace App\Controller;

use App\Entity\Alert;
use App\Entity\RcaAnalysis;
use App\Form\RcaConfigType;
use App\Repository\AlertRepository;
use App\Repository\RcaAnalysisRepository;
use App\Service\RcaService;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rca', name: 'app_rca_')]
#[IsGranted('ROLE_OPERATOR')]
class RcaController extends AbstractController
{
    public function __construct(
        private readonly TenantContext          $tenant,
        private readonly AlertRepository        $alertRepo,
        private readonly RcaAnalysisRepository  $rcaRepo,
        private readonly RcaService             $rcaService,
        private readonly EntityManagerInterface  $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_SUPERADMIN')) {
            return $this->render('rca/index.html.twig', [
                'analyses'   => $this->rcaRepo->findAll(),
                'rcaEnabled' => true,
            ]);
        }
        $company = $this->tenant->getCompany();

        if (!$company && !$this->isGranted('ROLE_SUPERADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $analyses = $this->rcaRepo->findByCompany($company);

        return $this->render('rca/index.html.twig', [
            'analyses' => $analyses,
            'rcaEnabled' => $company->isRcaEnabled(),
        ]);
    }

    #[Route('/settings', name: 'settings')]
    #[IsGranted('ROLE_SUPERADMIN')]
    public function settings(Request $request): Response
    {
        // Le superadmin global n'a pas de company → rediriger vers admin
        $company = $this->tenant->getCompany();

        if (!$company) {
            $this->addFlash('warning', 'Aucune entreprise associée à ce compte superadmin.');
            return $this->redirectToRoute('app_rca_index');
        }

        $form = $this->createForm(RcaConfigType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer rcaConfig depuis le POST car c'est un champ JSON
            $data = $request->request->all();
            $rcaConfig = $company->getRcaConfig();

            $rcaConfig['backend']          = $data['rca_backend'] ?? $rcaConfig['backend'];
            $rcaConfig['api_url']          = $data['rca_api_url'] ?? $rcaConfig['api_url'];
            $rcaConfig['api_key']          = $data['rca_api_key'] ?? $rcaConfig['api_key'];
            $rcaConfig['model']            = $data['rca_model'] ?? $rcaConfig['model'];
            $rcaConfig['auto_analyze']     = isset($data['rca_auto_analyze']);
            $rcaConfig['severity_trigger'] = $data['rca_severity_trigger'] ?? $rcaConfig['severity_trigger'];

            $company->setRcaConfig($rcaConfig);
            $this->em->flush();

            $this->addFlash('success', 'Configuration RCA mise à jour.');
            return $this->redirectToRoute('app_rca_settings');
        }

        return $this->render('rca/settings.html.twig', [
            'form'    => $form->createView(),
            'company' => $company,
        ]);
    }

    #[Route('/analyze/{alertId}', name: 'analyze', requirements: ['alertId' => '\d+'])]
    public function analyze(int $alertId): Response
    {
        $alert = $this->alertRepo->find($alertId);
        if (!$alert || !$this->tenant->canAccessEnvironment($alert->getApplication()->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        $company = $this->tenant->getCompany();

        if (!$company && !$this->isGranted('ROLE_SUPERADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if (!$company->isRcaEnabled()) {
            $this->addFlash('error', 'RCA est désactivé pour cette entreprise.');
            return $this->redirectToRoute('app_rca_index');
        }

        // Appeler le service RCA
        $analysis = $this->rcaService->analyzeAlert($alert);

        return $this->render('rca/analysis.html.twig', [
            'alert' => $alert,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/{id}/report', name: 'report', requirements: ['id' => '\d+'])]
    public function report(RcaAnalysis $analysis): Response
    {
        if (!$this->tenant->canAccessEnvironment($analysis->getAlert()->getApplication()->getEnvironment())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('rca/report.html.twig', [
            'analysis' => $analysis,
        ]);
    }
}
