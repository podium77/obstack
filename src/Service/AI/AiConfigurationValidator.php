<?php

namespace App\Service\AI;

use Psr\Log\LoggerInterface;

/**
 * AiConfigurationValidator — Valide les configurations IA
 *
 * Principes de validation :
 * - Tous les paramètres sont fournis MANUELLEMENT par l'administrateur
 * - Aucune autodétection
 * - Choix de modèle EXPLICITE (qwen3:4b ou qwen3:8b)
 * - Test de connexion OBLIGATOIRE
 */
class AiConfigurationValidator
{
    private LoggerInterface $logger;

    // Modèles supportés (choix explicite de l'admin)
    private const SUPPORTED_MODELS = ['qwen3:4b', 'qwen3:8b'];

    // Modes supportés
    private const SUPPORTED_MODES = ['existing', 'deployed'];

    // Fournisseurs supportés
    private const SUPPORTED_PROVIDERS = ['ollama'];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Valider une configuration Mode 1 (IA Existante)
     *
     * @param array $config Configuration à valider
     * @return ValidationResult Résultat de la validation
     */
    public function validateMode1(array $config): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Vérifier présence des champs obligatoires
        if (empty($config['name'])) {
            $errors['name'] = 'Le nom de l\'infrastructure IA est obligatoire';
        }

        if (empty($config['provider'])) {
            $errors['provider'] = 'Le fournisseur IA est obligatoire';
        } elseif (!in_array($config['provider'], self::SUPPORTED_PROVIDERS)) {
            $errors['provider'] = 'Fournisseur non supporté. Supporté : ' .
                implode(', ', self::SUPPORTED_PROVIDERS);
        }

        if (empty($config['api_url'])) {
            $errors['api_url'] = 'L\'URL de l\'API IA est obligatoire';
        } else {
            // Valider le format URL
            if (!filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
                $errors['api_url'] = 'Format URL invalide';
            }
        }

        if (empty($config['model'])) {
            $errors['model'] = 'Le modèle IA est obligatoire';
        } elseif (!in_array($config['model'], self::SUPPORTED_MODELS)) {
            $errors['model'] = 'Modèle non supporté. Supportés : ' .
                implode(', ', self::SUPPORTED_MODELS);
        }

        // api_key est optionnel
        if (!empty($config['api_key']) && !is_string($config['api_key'])) {
            $errors['api_key'] = 'La clé API doit être une chaîne de caractères';
        }

        // timeout est optionnel
        if (!empty($config['timeout_seconds'])) {
            if (!is_int($config['timeout_seconds']) || $config['timeout_seconds'] <= 0) {
                $errors['timeout_seconds'] = 'Le timeout doit être un nombre positif';
            }
        }

        // Si des erreurs, retourner immédiatement
        if (!empty($errors)) {
            $this->logger->warning('AI Mode 1 validation failed', [
                'errors' => $errors,
            ]);
            return new ValidationResult(false, $errors, $warnings);
        }

        // Validation OK
        $this->logger->info('AI Mode 1 configuration validated', [
            'provider' => $config['provider'],
            'model' => $config['model'],
        ]);

        return new ValidationResult(true, [], $warnings);
    }

    /**
     * Obtenir la liste des modèles supportés
     *
     * @return array Liste des modèles
     */
    public static function getSupportedModels(): array
    {
        return self::SUPPORTED_MODELS;
    }

    /**
     * Obtenir la liste des modes supportés
     *
     * @return array Liste des modes
     */
    public static function getSupportedModes(): array
    {
        return self::SUPPORTED_MODES;
    }

    /**
     * Obtenir la liste des fournisseurs supportés
     *
     * @return array Liste des fournisseurs
     */
    public static function getSupportedProviders(): array
    {
        return self::SUPPORTED_PROVIDERS;
    }

    /**
     * Vérifier si un modèle est supporté
     *
     * @param string $model Nom du modèle
     * @return bool true si supporté
     */
    public static function isModelSupported(string $model): bool
    {
        return in_array($model, self::SUPPORTED_MODELS, true);
    }
}

/**
 * Résultat de validation
 */
class ValidationResult
{
    private bool $isValid;
    private array $errors;
    private array $warnings;

    public function __construct(bool $isValid, array $errors = [], array $warnings = [])
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->warnings = $warnings;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        $this->isValid = false;
        return $this;
    }

    public function addWarning(string $message): self
    {
        $this->warnings[] = $message;
        return $this;
    }
}
