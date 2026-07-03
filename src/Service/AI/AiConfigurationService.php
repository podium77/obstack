<?php

namespace App\Service\AI;

use App\Entity\AIInfrastructure;
use App\Entity\LocalUser;
use App\Entity\Company;
use App\Repository\AIInfrastructureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AiConfigurationService — Service d'orchestration pour la configuration IA
 *
 * Responsabilités :
 * - Enregistrer une infrastructure IA existante (Mode 1)
 * - Tester la connexion à l'infrastructure IA
 * - Récupérer l'infrastructure IA active
 * - Gérer les erreurs et les logs
 *
 * Principes :
 * - Communication UNIQUEMENT par API
 * - Validation stricte des entrées
 * - Pas d'autodétection
 */
class AiConfigurationService
{
    private AIInfrastructureRepository $repository;
    private EntityManagerInterface $entityManager;
    private AiConfigurationValidator $validator;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        AIInfrastructureRepository $repository,
        EntityManagerInterface $entityManager,
        AiConfigurationValidator $validator,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Enregistrer une infrastructure IA existante (Mode 1)
     *
     * @param array $config Configuration à enregistrer
     * @param LocalUser $createdBy Utilisateur qui crée la configuration
     * @param Company|null $company Entreprise (optionnel si globale)
     * @return AIInfrastructure L'infrastructure créée
     * @throws AiConfigurationException Si la validation échoue
     * @throws OllamaException Si le test de connexion échoue
     */
    public function registerMode1(
        array $config,
        LocalUser $createdBy,
        ?Company $company = null
    ): AIInfrastructure {
        // Étape 1 : Valider la configuration
        $validationResult = $this->validator->validateMode1($config);
        if (!$validationResult->isValid()) {
            throw new AiConfigurationException(
                'Configuration IA invalide : ' . json_encode($validationResult->getErrors())
            );
        }

        // Étape 2 : Tester la connexion
        $timeout = $config['timeout_seconds'] ?? 30;
        $client = new OllamaClient(
            $this->httpClient,
            $this->logger,
            $config['api_url'],
            $config['api_key'] ?? null,
            $timeout
        );

        if (!$client->testConnection()) {
            $this->logger->error('AI configuration test failed', [
                'api_url' => $config['api_url'],
            ]);
            throw new OllamaException(
                'Impossible de se connecter à l\'API IA : ' . $config['api_url']
            );
        }

        // Étape 3 : Vérifier que le modèle est disponible
        $availableModels = $client->listModels();
        if (!in_array($config['model'], $availableModels)) {
            $this->logger->error('Model not available', [
                'requested_model' => $config['model'],
                'available_models' => $availableModels,
            ]);
            throw new OllamaException(
                'Modèle ' . $config['model'] . ' non trouvé sur le serveur'
            );
        }

        // Étape 4 : Créer l'entity
        $infrastructure = new AIInfrastructure(
            $config['name'],
            'existing',
            $config['provider'],
            $config['api_url'],
            $config['model'],
            'active'
        );

        $infrastructure->setApiKey($config['api_key'] ?? null);
        $infrastructure->setTimeoutSeconds($timeout);
        $infrastructure->setLastHealthCheckAt(new \DateTimeImmutable());
        $infrastructure->setCreatedBy($createdBy);
        if ($company) {
            $infrastructure->setCompany($company);
        }

        // Étape 5 : Persister en base de données
        $this->entityManager->persist($infrastructure);
        $this->entityManager->flush();

        $this->logger->info('AI infrastructure registered', [
            'id' => $infrastructure->getId(),
            'name' => $infrastructure->getName(),
            'provider' => $infrastructure->getProvider(),
            'model' => $infrastructure->getModel(),
            'mode' => 'existing',
            'company_id' => $company?->getId(),
        ]);

        return $infrastructure;
    }

    /**
     * Tester la connexion à une infrastructure IA existante
     *
     * @param AIInfrastructure $infrastructure Infrastructure à tester
     * @return bool true si la connexion est OK
     */
    public function testConnection(AIInfrastructure $infrastructure): bool
    {
        try {
            $client = new OllamaClient(
                $this->httpClient,
                $this->logger,
                $infrastructure->getApiUrl(),
                $infrastructure->getApiKey(),
                $infrastructure->getTimeoutSeconds()
            );

            $isConnected = $client->testConnection();

            if ($isConnected) {
                // Mettre à jour la date du dernier health check
                $infrastructure->setLastHealthCheckAt(new \DateTimeImmutable());
                $infrastructure->setErrorMessage(null);
                $this->entityManager->flush();
                $this->logger->info('Health check successful', [
                    'infrastructure_id' => $infrastructure->getId(),
                ]);
            } else {
                $infrastructure->setErrorMessage('Health check failed');
                $this->entityManager->flush();
                $this->logger->warning('Health check failed', [
                    'infrastructure_id' => $infrastructure->getId(),
                ]);
            }

            return $isConnected;
        } catch (OllamaException $e) {
            $infrastructure->setErrorMessage($e->getMessage());
            $this->entityManager->flush();
            $this->logger->error('Health check error', [
                'infrastructure_id' => $infrastructure->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Récupérer l'infrastructure IA active pour une entreprise
     *
     * Priorité :
     * 1. IA spécifique à l'entreprise
     * 2. IA globale (partagée)
     *
     * @param Company|null $company Entreprise (optionnel)
     * @return AIInfrastructure|null L'infrastructure active ou null
     */
    public function getActiveInfrastructure(?Company $company = null): ?AIInfrastructure
    {
        return $this->repository->findActive($company);
    }

    /**
     * Lister toutes les infrastructures IA actives
     *
     * @return AIInfrastructure[] Liste des infrastructures
     */
    public function listActiveInfrastructures(): array
    {
        return $this->repository->findAllActive();
    }

    /**
     * Désactiver une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure Infrastructure à désactiver
     */
    public function deactivate(AIInfrastructure $infrastructure): void
    {
        $infrastructure->setIsActive(false);
        $infrastructure->setStatus('inactive');
        $this->entityManager->flush();

        $this->logger->info('AI infrastructure deactivated', [
            'infrastructure_id' => $infrastructure->getId(),
        ]);
    }

    /**
     * Activer une infrastructure IA
     *
     * @param AIInfrastructure $infrastructure Infrastructure à activer
     * @throws OllamaException Si le test de connexion échoue
     */
    public function activate(AIInfrastructure $infrastructure): void
    {
        // Tester la connexion avant d'activer
        if (!$this->testConnection($infrastructure)) {
            throw new OllamaException(
                'Cannot activate infrastructure: connection test failed'
            );
        }

        $infrastructure->setIsActive(true);
        $infrastructure->setStatus('active');
        $this->entityManager->flush();

        $this->logger->info('AI infrastructure activated', [
            'infrastructure_id' => $infrastructure->getId(),
        ]);
    }
}

/**
 * Exception levée lors d'erreurs de configuration IA
 */
class AiConfigurationException extends \Exception
{
}
