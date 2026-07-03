<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * OllamaClient — Client HTTP pour communiquer avec l'API Ollama
 *
 * Principes :
 * - Communication UNIQUEMENT par API REST
 * - Support des modèles : qwen3:4b, qwen3:8b
 * - Test de connexion obligatoire
 * - Gestion des timeouts et des erreurs
 */
class OllamaClient
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiUrl;
    private ?string $apiKey;
    private int $timeoutSeconds;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $apiUrl,
        ?string $apiKey = null,
        int $timeoutSeconds = 30
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Tester la connexion à l'API Ollama
     *
     * @return bool true si l'API est accessible
     */
    public function testConnection(): bool
    {
        try {
            $this->logger->debug('Testing Ollama connection', ['url' => $this->apiUrl]);

            $response = $this->httpClient->request(
                'GET',
                $this->apiUrl . '/api/tags',
                ['timeout' => $this->timeoutSeconds]
            );

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('Ollama connection successful', ['url' => $this->apiUrl]);
                return true;
            }

            $this->logger->warning('Ollama connection failed', [
                'url' => $this->apiUrl,
                'status_code' => $statusCode,
            ]);

            return false;
        } catch (ExceptionInterface $e) {
            $this->logger->error('Ollama connection error', [
                'url' => $this->apiUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lister tous les modèles disponibles sur le serveur Ollama
     *
     * @return array Liste des modèles (ex: ['qwen3:4b', 'qwen3:8b', ...])
     * @throws OllamaException Si la requête échoue
     */
    public function listModels(): array
    {
        try {
            $this->logger->debug('Listing Ollama models', ['url' => $this->apiUrl]);

            $response = $this->httpClient->request(
                'GET',
                $this->apiUrl . '/api/tags',
                ['timeout' => $this->timeoutSeconds]
            );

            if ($response->getStatusCode() !== 200) {
                throw new OllamaException(
                    'Failed to list models: HTTP ' . $response->getStatusCode()
                );
            }

            $data = $response->toArray();
            $models = [];

            if (!empty($data['models']) && is_array($data['models'])) {
                foreach ($data['models'] as $model) {
                    if (isset($model['name'])) {
                        $models[] = $model['name'];
                    }
                }
            }

            $this->logger->debug('Models listed', ['count' => count($models), 'models' => $models]);

            return $models;
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to list Ollama models', [
                'url' => $this->apiUrl,
                'error' => $e->getMessage(),
            ]);
            throw new OllamaException('Failed to list models: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Vérifier si un modèle spécifique est disponible
     *
     * @param string $model Nom du modèle (ex: 'qwen3:8b')
     * @return bool true si le modèle est disponible
     */
    public function isModelAvailable(string $model): bool
    {
        try {
            $availableModels = $this->listModels();
            return in_array($model, $availableModels, true);
        } catch (OllamaException $e) {
            $this->logger->warning('Error checking model availability', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envoyer un prompt au modèle et obtenir une réponse
     *
     * @param string $prompt Le message à envoyer
     * @param string $model Le modèle à utiliser (ex: 'qwen3:8b')
     * @param array $options Options supplémentaires (temperature, top_p, etc.)
     * @return string La réponse générée
     * @throws OllamaException Si la requête échoue
     */
    public function chat(string $prompt, string $model = 'qwen3:8b', array $options = []): string
    {
        try {
            $this->logger->debug('Sending chat request to Ollama', [
                'model' => $model,
                'prompt_length' => strlen($prompt),
            ]);

            $payload = array_merge(
                [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                $options
            );

            $response = $this->httpClient->request(
                'POST',
                $this->apiUrl . '/api/generate',
                [
                    'json' => $payload,
                    'timeout' => $this->timeoutSeconds,
                    'headers' => $this->getHeaders(),
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new OllamaException(
                    'Chat request failed: HTTP ' . $response->getStatusCode()
                );
            }

            $data = $response->toArray();

            if (!isset($data['response'])) {
                throw new OllamaException('Invalid response format from Ollama');
            }

            $this->logger->debug('Chat response received', [
                'model' => $model,
                'response_length' => strlen($data['response']),
            ]);

            return $data['response'];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Chat request error', [
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            throw new OllamaException('Chat request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtenir les informations du serveur Ollama
     *
     * @return array Informations du serveur
     * @throws OllamaException Si la requête échoue
     */
    public function getServerInfo(): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->apiUrl . '/api/tags',
                ['timeout' => $this->timeoutSeconds]
            );

            if ($response->getStatusCode() !== 200) {
                throw new OllamaException('Failed to get server info');
            }

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new OllamaException('Failed to get server info: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtenir les headers HTTP nécessaires
     *
     * @return array Headers HTTP
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        return $headers;
    }

    /**
     * Getter - URL API
     */
    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * Getter - Timeout
     */
    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}

/**
 * Exception levée lors d'erreurs Ollama
 */
class OllamaException extends \Exception
{
}
