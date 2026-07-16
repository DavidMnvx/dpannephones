<?php

namespace App\Form;

use App\Entity\Horaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HoraireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $timeOpts = [
            'required' => false,
            'widget'   => 'single_text',
            'input'    => 'datetime',
        ];

        $builder
            ->add('matinOuverture', TimeType::class, ['label' => 'Matin - ouverture'] + $timeOpts)
            ->add('matinFermeture', TimeType::class, ['label' => 'Matin - fermeture'] + $timeOpts)
            ->add('apresmidiOuverture', TimeType::class, ['label' => 'Après-midi - ouverture'] + $timeOpts)
            ->add('apresmidiFermeture', TimeType::class, ['label' => 'Après-midi - fermeture'] + $timeOpts)
            ->add('ferme', CheckboxType::class, [
                'label'    => 'Fermé toute la journée',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Horaire::class,
        ]);
    }
}
