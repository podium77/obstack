<?php
namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RcaConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rcaEnabled', CheckboxType::class, [
                'label'    => 'Activer l\'analyse RCA',
                'required' => false,
            ])
            ->add('rca_backend', ChoiceType::class, [
                'label'    => 'Backend RCA',
                'mapped'   => false,
                'choices'  => [
                    'PyRCA'       => 'pyrca',
                    'Personnalisé' => 'custom',
                ],
            ])
            ->add('rca_api_url', TextType::class, [
                'label'    => 'URL de l\'API RCA',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('rca_api_key', TextType::class, [
                'label'    => 'Clé API RCA',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('rca_model', ChoiceType::class, [
                'label'   => 'Modèle d\'analyse',
                'mapped'  => false,
                'choices' => [
                    'Bayésien' => 'bayesian',
                    'Causal'   => 'causal',
                    'ML'       => 'ml',
                ],
            ])
            ->add('rca_auto_analyze', CheckboxType::class, [
                'label'    => 'Analyse automatique',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('rca_severity_trigger', ChoiceType::class, [
                'label'   => 'Sévérité déclenchante',
                'mapped'  => false,
                'choices' => [
                    'Critique'      => 'critical',
                    'Erreur'        => 'error',
                    'Avertissement' => 'warning',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}