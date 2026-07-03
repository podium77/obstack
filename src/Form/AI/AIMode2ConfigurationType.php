<?php

namespace App\Form\AI;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\{
    TextType,
    PasswordType,
    IntegerType,
    ChoiceType,
    SubmitType,
    HiddenType
};
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire Mode 2 — Déploiement Automatisé
 * 
 * Wizard multi-étapes pour :
 * - Step 1: Choix hyperviseur
 * - Step 2: Configuration hyperviseur (credentials)
 * - Step 3: Configuration VM (CPU, RAM, Disk)
 * - Step 4: Choix modèle IA
 * - Step 5: Résumé et confirmation
 */
class AIMode2ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $step = $options['step'] ?? 1;

        // Step 1: Hypervisor Selection
        if ($step === 1 || $step === 'all') {
            $builder
                ->add('hypervisor_provider', ChoiceType::class, [
                    'label' => 'Fournisseur Hyperviseur',
                    'choices' => [
                        'Proxmox' => 'proxmox',
                        'VMware vSphere' => 'vmware',
                        'Nutanix Prism' => 'nutanix',
                    ],
                    'expanded' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Choice(choices: ['proxmox', 'vmware', 'nutanix']),
                    ]
                ]);
        }

        // Step 2: Hypervisor Credentials
        if ($step === 2 || $step === 'all') {
            $builder
                ->add('hypervisor_host', TextType::class, [
                    'label' => 'URL/Host Hyperviseur',
                    'placeholder' => 'https://proxmox.example.com:8006',
                    'constraints' => [new Assert\NotBlank(), new Assert\Url()],
                    'required' => false,
                ])
                ->add('hypervisor_username', TextType::class, [
                    'label' => 'Identifiant Hyperviseur',
                    'placeholder' => 'root@pam ou administrateur',
                    'constraints' => [new Assert\NotBlank()],
                    'required' => false,
                ])
                ->add('hypervisor_password', PasswordType::class, [
                    'label' => 'Mot de passe Hyperviseur',
                    'constraints' => [new Assert\NotBlank()],
                    'required' => false,
                ]);

            // Proxmox specific
            $builder->add('proxmox_node', TextType::class, [
                'label' => 'Nœud Proxmox',
                'placeholder' => 'pve',
                'required' => false,
            ]);

            // VMware specific
            $builder->add('vmware_datacenter', TextType::class, [
                'label' => 'Datacenter VMware',
                'placeholder' => 'ha-datacenter',
                'required' => false,
            ]);

            // Nutanix specific
            $builder->add('nutanix_cluster', TextType::class, [
                'label' => 'Cluster Nutanix',
                'placeholder' => 'default',
                'required' => false,
            ]);
        }

        // Step 3: VM Configuration
        if ($step === 3 || $step === 'all') {
            $builder
                ->add('vm_name', TextType::class, [
                    'label' => 'Nom de la Machine Virtuelle',
                    'placeholder' => 'ollama-ia-01',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 3, max: 255),
                    ],
                    'required' => false,
                ])
                ->add('vm_memory', IntegerType::class, [
                    'label' => 'Mémoire RAM (MB)',
                    'placeholder' => '4096',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\GreaterThanOrEqual(value: 2048),
                        new Assert\LessThanOrEqual(value: 1048576),
                    ],
                    'required' => false,
                    'help' => 'Minimum recommandé: 4096 MB (4 GB)',
                ])
                ->add('vm_cores', IntegerType::class, [
                    'label' => 'Nombre de CPU',
                    'placeholder' => '2',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\GreaterThanOrEqual(value: 1),
                        new Assert\LessThanOrEqual(value: 128),
                    ],
                    'required' => false,
                    'help' => 'Minimum recommandé: 2 cores',
                ])
                ->add('vm_disk', IntegerType::class, [
                    'label' => 'Taille Disque (GB)',
                    'placeholder' => '50',
                    'constraints' => [
                        new Assert\GreaterThanOrEqual(value: 10),
                        new Assert\LessThanOrEqual(value: 1000),
                    ],
                    'required' => false,
                    'help' => 'Défaut: 50 GB',
                ])
                ->add('vm_storage', TextType::class, [
                    'label' => 'Storage Container (optionnel)',
                    'placeholder' => 'Laisser vide pour défaut',
                    'required' => false,
                ]);
        }

        // Step 4: AI Model Selection (EXPLICIT)
        if ($step === 4 || $step === 'all') {
            $builder
                ->add('ai_model', ChoiceType::class, [
                    'label' => 'Modèle IA',
                    'choices' => [
                        'Qwen3 4B (Léger - Recommandé pour déploiement auto)' => 'qwen3:4b',
                        'Qwen3 8B (Puissant - Nécessite plus de ressources)' => 'qwen3:8b',
                    ],
                    'expanded' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Choice(choices: ['qwen3:4b', 'qwen3:8b']),
                    ],
                    'required' => false,
                    'help' => 'Choix EXPLICITE — Pas d\'autodétection',
                ]);
        }

        // Step 5: Summary & Submit
        if ($step === 5 || $step === 'all') {
            $builder
                ->add('deployment_name', TextType::class, [
                    'label' => 'Nom du Déploiement',
                    'placeholder' => 'Mode 2 - Ollama Production',
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length(min: 3, max: 255),
                    ],
                    'required' => false,
                ]);
        }

        // Hidden field for step tracking
        $builder->add('step', HiddenType::class, [
            'data' => $step,
        ]);

        // Submit button
        if ($step < 5) {
            $builder->add('next', SubmitType::class, [
                'label' => 'Suivant →',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
        } else {
            $builder->add('deploy', SubmitType::class, [
                'label' => 'Déployer',
                'attr' => ['class' => 'btn btn-success'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'step' => 1,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'ai_mode2_config',
        ]);
    }
}
