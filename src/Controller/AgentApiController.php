<?php
namespace App\Controller;

use App\Entity\Application;
use App\Entity\MetricSnapshot;
use App\Enum\AlertSeverity;
use App\Enum\MachineType;
use App\Enum\OsType;
use App\Repository\AgentTokenRepository;
use App\Repository\ApplicationRepository;
use App\Service\AgentInstallScriptGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API REST consommée par les agents installés sur les machines supervisées.
 * Authentification: Bearer token (token agent).
 */
#[Route('/api/v1/agent', name: 'api_agent_')]
class AgentApiController extends AbstractController
{
    public function __construct(
        private readonly AgentTokenRepository  $tokenRepo,
        private readonly ApplicationRepository $appRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
        private readonly AgentInstallScriptGenerator $scriptGenerator,
    ) {}

    /**
     * Endpoint de téléchargement du script d'installation.
     * URL publique: /api/v1/agent/install/{token}
     */
    #[Route('/install/{token}', name: 'install_script', methods: ['GET'])]
    public function installScript(string $token): Response
    {
        $agentToken = $this->tokenRepo->findByToken($token);

        if (!$agentToken || !$agentToken->isValid()) {
            return new Response("# Token invalide ou expiré.\n", 403, [
                'Content-Type' => 'text/plain',
            ]);
        }

        $script = $agentToken->getInstallScript();

        if (!$script) {
            $script = $this->scriptGenerator->generateForToken($agentToken);
            $agentToken->setInstallScript($script);
            $this->em->persist($agentToken);
            $this->em->flush();
        }
        $env     = $agentToken->getEnvironment();
        $company = $env->getCompany();
        $filename = "install-obs-{$company->getSlug()}-{$env->getSlug()}.sh";

        return new Response($script, 200, [
            'Content-Type'        => 'application/x-sh',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Enregistrement initial d'un agent (auto-découverte).
     * L'agent envoie ses informations matérielles et les technologies détectées.
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $agentToken = $this->authenticateToken($request);
        if (!$agentToken) {
            return $this->json(['error' => 'Token invalide ou expiré.'], 401);
        }

        $data     = $request->toArray();
        $env      = $agentToken->getEnvironment();
        $ip       = $request->getClientIp();
        $hostname = $data['hostname'] ?? 'unknown';

        // Mettre à jour le heartbeat du token
        $agentToken->recordHeartbeat($ip, $hostname);

        // Trouver ou créer l'application associée au token
        $app = $agentToken->getApplication()
            ?? $this->appRepo->findByTokenAndEnv($agentToken, $env)
            ?? $this->createApplicationFromRegistration($data, $env, $agentToken);

        // Mettre à jour les informations détectées
        $this->updateApplicationFromRegistration($app, $data);

        // Lier le token à l'application
        $agentToken->setApplication($app);

        $this->em->flush();

        $this->logger->info("Agent enregistré: {$hostname} ({$ip}) — {$app->getName()}");

        return $this->json([
            'success'        => true,
            'application_id' => $app->getId(),
            'application_name' => $app->getName(),
            'environment'    => $env->getName(),
            'company'        => $env->getCompany()->getName(),
            'collect_interval' => 60,
            'heartbeat_interval' => 30,
        ]);
    }

    /**
     * Réception des métriques collectées par l'agent.
     */
    #[Route('/metrics', name: 'metrics', methods: ['POST'])]
    public function receiveMetrics(Request $request): JsonResponse
    {
        $agentToken = $this->authenticateToken($request);
        if (!$agentToken) {
            return $this->json(['error' => 'Token invalide.'], 401);
        }

        $data = $request->toArray();
        $app  = $agentToken->getApplication();

        if (!$app) {
            return $this->json(['error' => 'Agent non enregistré. Appelez /register d\'abord.'], 400);
        }

        // Mettre à jour le heartbeat
        $agentToken->recordHeartbeat(
            $request->getClientIp(),
            $agentToken->getDetectedHostname() ?? 'unknown'
        );

        // Créer le snapshot de métriques
        $snapshot = $this->buildSnapshotFromPayload($app, $data);
        $this->em->persist($snapshot);
        $this->em->flush();

        return $this->json([
            'success'     => true,
            'snapshot_id' => $snapshot->getId(),
            'severity'    => $snapshot->getSeverity()->value,
        ]);
    }

    /**
     * Heartbeat périodique de l'agent (statut alive).
     */
    #[Route('/heartbeat', name: 'heartbeat', methods: ['POST'])]
    public function heartbeat(Request $request): JsonResponse
    {
        $agentToken = $this->authenticateToken($request);
        if (!$agentToken) {
            return $this->json(['error' => 'Token invalide.'], 401);
        }

        $agentToken->recordHeartbeat(
            $request->getClientIp(),
            $request->toArray()['hostname'] ?? $agentToken->getDetectedHostname() ?? 'unknown'
        );
        $this->em->flush();

        return $this->json(['status' => 'alive', 'timestamp' => time()]);
    }

    // ----------------------------------------------------------------
    // Méthodes privées
    // ----------------------------------------------------------------

    private function authenticateToken(Request $request): ?\App\Entity\AgentToken
    {
        $authorization = $request->headers->get('Authorization', '');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $tokenValue = substr($authorization, 7);
        $token      = $this->tokenRepo->findByToken($tokenValue);

        if (!$token || !$token->isValid()) {
            return null;
        }

        return $token;
    }

    private function createApplicationFromRegistration(
        array $data,
        \App\Entity\Environment $env,
        \App\Entity\AgentToken $agentToken
    ): Application {
        $app = new Application();
        $app->setEnvironment($env);
        $app->setName($data['hostname_short'] ?? $data['hostname'] ?? 'Serveur inconnu');
        $app->setHostAddress($data['hostname'] ?? '');
        $app->setActive(true);

        // Type de machine
        $machineType = MachineType::tryFrom($data['machine_type'] ?? '') ?? MachineType::UNKNOWN;
        $app->setMachineType($machineType);

        // OS
        $osId  = strtolower($data['os_id'] ?? '');
        $osMap = [
            'debian'   => OsType::DEBIAN,
            'ubuntu'   => OsType::UBUNTU,
            'rhel'     => OsType::REDHAT,
            'centos'   => OsType::CENTOS,
            'rocky'    => OsType::ROCKYLINUX,
            'almalinux'=> OsType::ROCKYLINUX,
        ];
        $app->setOsType($osMap[$osId] ?? OsType::DEBIAN);

        // K8s
        $app->setIsKubernetesNode($data['is_k8s_node'] === true || $data['is_k8s_node'] === 'true');

        $this->em->persist($app);
        return $app;
    }

    private function updateApplicationFromRegistration(Application $app, array $data): void
    {
        if (!empty($data['technologies'])) {
            $app->setDetectedTechnologies($data['technologies']);
            $app->setLastDetectionAt(new \DateTimeImmutable());
        }

        $hwInfo = $app->getHardwareInfo();
        $app->setHardwareInfo(array_merge($hwInfo, array_filter([
            'cpu_model'          => $data['cpu_model'] ?? null,
            'cpu_cores'          => $data['cpu_cores'] ?? null,
            'cpu_threads'        => $data['cpu_threads'] ?? null,
            'total_ram_gb'       => $data['ram_gb'] ?? null,
            'serial_number'      => $data['serial'] ?? null,
            'manufacturer'       => $data['manufacturer'] ?? null,
            'product_name'       => $data['product'] ?? null,
            'bios_version'       => $data['product'] ?? null,
            'hypervisor'         => $data['hypervisor'] ?? null,
            'vm_uuid'            => $data['vm_uuid'] ?? null,
            'network_interfaces' => $data['network_interfaces'] ?? null,
            'disks'              => $data['disks'] ?? null,
        ])));
    }

    private function buildSnapshotFromPayload(Application $app, array $data): MetricSnapshot
    {
        $snapshot = new MetricSnapshot();
        $snapshot->setApplication($app);
        $snapshot->setSeverity(AlertSeverity::WARNING);
        $snapshot->setData($data);
        $snapshot->setCreatedAt(new \DateTimeImmutable());
        return $snapshot;
    }
}
