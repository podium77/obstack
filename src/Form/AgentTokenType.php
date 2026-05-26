<?php
namespace App\Form;

use App\Entity\AgentToken;
use App\Entity\Environment;
use App\Repository\EnvironmentRepository;
use App\Service\TenantContext;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AgentTokenType extends AbstractType
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly EnvironmentRepository $envRepo,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $environments = $options['environments'] ?? $this->tenant->getAccessibleEnvironments();

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du token',
                'attr' => [
                    'placeholder' => 'ex: Token pour le serveur CRM',
                    'class' => 'form-control',
                ],
                'required' => true,
            ])
            ->add('environment', EntityType::class, [
                'label' => 'Environnement',
                'class' => Environment::class,
                'choices' => $environments,
                'choice_label' => 'name',
                'choice_value' => 'id',
                'placeholder' => 'Sélectionnez un environnement',
                'required' => true,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Token actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);

        $builder->add('modules', ChoiceType::class, [
            'label' => 'Modules à activer',
            'choices' => [
                'Prometheus' => 'prometheus',
                'OpenTelemetry + eBPF' => 'opentelemetry',
                'Loki (logs)' => 'loki',
                'Jaeger (tracing)' => 'jaeger',
            ],
            'expanded' => true,
            'multiple' => true,
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AgentToken::class,
            'environments' => null, // Peut être surchargé pour limiter les choix
        ]);
    }
}
