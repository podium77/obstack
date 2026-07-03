<?php

namespace App\Form\AI;

use App\Service\AI\AiConfigurationValidator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AIMode1ConfigurationType — Formulaire pour configurer une IA existante (Mode 1)
 *
 * Principes :
 * - Configuration MANUELLE (pas d'autodétection)
 * - Choix du modèle EXPLICITE
 * - Validation stricte
 */
class AIMode1ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ─── Nom de l'infrastructure ───────────────────────────────────
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'infrastructure IA',
                'help' => 'Exemple : Ollama Production, IA Entreprise, etc.',
                'attr' => [
                    'placeholder' => 'Nom descriptif',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length([
                        'min' => 3,
                        'minMessage' => 'Le nom doit contenir au moins 3 caractères',
                        'max' => 255,
                        'maxMessage' => 'Le nom ne doit pas dépasser 255 caractères',
                    ]),
                ],
            ])

            // ─── Fournisseur IA ────────────────────────────────────────────
            ->add('provider', ChoiceType::class, [
                'label' => 'Fournisseur IA',
                'help' => 'Sélectionnez le fournisseur IA à intégrer',
                'choices' => [
                    'Ollama' => 'ollama',
                    // Extensible : 'vLLM' => 'vllm', 'API Externe' => 'external', etc.
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le fournisseur est obligatoire']),
                    new Assert\Choice([
                        'choices' => AiConfigurationValidator::getSupportedProviders(),
                        'message' => 'Fournisseur non supporté',
                    ]),
                ],
            ])

            // ─── URL API ───────────────────────────────────────────────────
            ->add('api_url', TextType::class, [
                'label' => 'URL de l\'API IA',
                'help' => 'Exemple : http://ollama.company.local:11434 ou https://ai-api.example.com',
                'attr' => [
                    'placeholder' => 'http://localhost:11434',
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'URL est obligatoire']),
                    new Assert\Url(['message' => 'URL invalide']),
                ],
            ])

            // ─── Clé API (optionnelle) ────────────────────────────────────
            ->add('api_key', PasswordType::class, [
                'label' => 'Clé API (optionnelle)',
                'help' => 'Laissez vide si aucune authentification requise',
                'required' => false,
                'attr' => [
                    'placeholder' => '••••••••••••••••',
                    'class' => 'form-control',
                ],
            ])

            // ─── Modèle IA (choix EXPLICITE) ──────────────────────────────
            ->add('model', ChoiceType::class, [
                'label' => 'Modèle IA',
                'help' => 'Sélectionnez le modèle à utiliser. Choix explicite de l\'administrateur.',
                'choices' => [
                    'Qwen3 4B (4 milliards de paramètres - léger)' => 'qwen3:4b',
                    'Qwen3 8B (8 milliards de paramètres - précision accrue)' => 'qwen3:8b',
                    // Extensible : d'autres modèles peuvent être ajoutés ici
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le modèle est obligatoire']),
                    new Assert\Choice([
                        'choices' => AiConfigurationValidator::getSupportedModels(),
                        'message' => 'Modèle non supporté',
                    ]),
                ],
            ])

            // ─── Timeout ───────────────────────────────────────────────────
            ->add('timeout_seconds', IntegerType::class, [
                'label' => 'Timeout API (secondes)',
                'help' => 'Délai d\'attente maximum pour les appels API (par défaut : 30s)',
                'required' => false,
                'data' => 30,
                'attr' => [
                    'placeholder' => '30',
                    'class' => 'form-control',
                    'min' => '1',
                    'max' => '300',
                ],
                'constraints' => [
                    new Assert\GreaterThan([
                        'value' => 0,
                        'message' => 'Le timeout doit être positif',
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 300,
                        'message' => 'Le timeout ne doit pas dépasser 300 secondes',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'ai-configuration-form'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'ai_mode1_configuration';
    }
}
