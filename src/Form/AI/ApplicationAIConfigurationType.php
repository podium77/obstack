<?php

namespace App\Form\AI;

use App\Entity\AIInfrastructure;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{
    ChoiceType,
    HiddenType,
    TextType,
    IntegerType,
    SubmitType
};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de configuration IA pour création d'Application
 * 
 * Permet de sélectionner :
 * - Pas d'IA (mode=none)
 * - IA existante (mode=existing + infrastructure ID)
 * - Nouveau déploiement (mode=deployed + config)
 */
class ApplicationAIConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ai_mode', ChoiceType::class, [
                'label' => 'Configuration de l\'IA',
                'choices' => [
                    'Ne pas utiliser d\'IA' => 'none',
                    'Utiliser un cluster IA existant' => 'existing',
                    'Déployer un nouveau cluster IA' => 'deployed',
                ],
                'expanded' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Vous devez sélectionner une option IA']),
                ],
                'help' => 'Choisissez le mode d\'intégration IA pour cette application',
            ]);

        // Listener pour afficher les champs conditionnels
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $this->addConditionalFields($form, null);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();
            $mode = $data['ai_mode'] ?? 'none';
            $this->addConditionalFields($form, $mode);
        });
    }

    private function addConditionalFields(FormBuilderInterface $builder, ?string $mode): void
    {
        // Mode: Cluster IA existant
        if ($mode === 'existing' || $mode === null) {
            $builder
                ->add('ai_existing_infrastructure', EntityType::class, [
                    'label' => 'Infrastructure IA existante',
                    'class' => AIInfrastructure::class,
                    'choice_label' => 'name',
                    'placeholder' => '-- Sélectionner une infrastructure --',
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\Expression([
                            'expression' => 'this.getParent().get("ai_mode").getData() !== "existing" or value !== null',
                            'message' => 'Vous devez sélectionner une infrastructure existante'
                        ])
                    ],
                    'query_builder' => function ($repo) {
                        return $repo->createQueryBuilder('i')
                            ->where('i.status = :status')
                            ->setParameter('status', 'active')
                            ->orderBy('i.name', 'ASC');
                    }
                ])
                ->add('ai_existing_model', ChoiceType::class, [
                    'label' => 'Modèle IA',
                    'choices' => [
                        'Qwen3 4B (Léger)' => 'qwen3:4b',
                        'Qwen3 8B (Puissant)' => 'qwen3:8b',
                    ],
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\Expression([
                            'expression' => 'this.getParent().get("ai_mode").getData() !== "existing" or value !== null',
                            'message' => 'Vous devez sélectionner un modèle IA'
                        ])
                    ],
                    'help' => 'Choix EXPLICITE du modèle — Aucun modèle n\'est installé automatiquement',
                ]);
        }

        // Mode: Nouveau déploiement
        if ($mode === 'deployed' || $mode === null) {
            $builder
                ->add('ai_deployed_model', ChoiceType::class, [
                    'label' => 'Modèle IA à déployer',
                    'choices' => [
                        'Qwen3 4B (Léger - Recommandé)' => 'qwen3:4b',
                        'Qwen3 8B (Puissant)' => 'qwen3:8b',
                    ],
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\Expression([
                            'expression' => 'this.getParent().get("ai_mode").getData() !== "deployed" or value !== null',
                            'message' => 'Vous devez sélectionner un modèle IA'
                        ])
                    ],
                    'help' => 'Choix EXPLICITE du modèle — Seul ce modèle sera installé',
                ])
                ->add('ai_deployed_vm_name', TextType::class, [
                    'label' => 'Nom de la machine virtuelle',
                    'placeholder' => 'ollama-ia-app-01',
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\Expression([
                            'expression' => 'this.getParent().get("ai_mode").getData() !== "deployed" or (value !== null and value !== "")',
                            'message' => 'Vous devez spécifier le nom de la VM'
                        ])
                    ],
                ])
                ->add('ai_deployed_vm_memory', IntegerType::class, [
                    'label' => 'Mémoire RAM (MB)',
                    'data' => 4096,
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\GreaterThanOrEqual([
                            'value' => 2048,
                            'message' => 'La RAM minimum est 2048 MB (2 GB)'
                        ]),
                    ],
                    'help' => 'Minimum recommandé: 4096 MB (4 GB)',
                ])
                ->add('ai_deployed_vm_cores', IntegerType::class, [
                    'label' => 'Nombre de CPUs',
                    'data' => 2,
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\GreaterThanOrEqual(['value' => 1]),
                        new Assert\LessThanOrEqual(['value' => 128]),
                    ],
                    'help' => 'Minimum recommandé: 2 cores',
                ])
                ->add('ai_deployed_vm_disk', IntegerType::class, [
                    'label' => 'Taille disque (GB)',
                    'data' => 50,
                    'mapped' => false,
                    'required' => false,
                    'constraints' => [
                        new Assert\GreaterThanOrEqual(['value' => 20]),
                    ],
                    'help' => 'Minimum: 20 GB, recommandé: 50+ GB',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'application_ai_config',
        ]);
    }
}
