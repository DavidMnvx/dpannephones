<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Votre prénom', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est requis.']),
                    new Length(['max' => 50, 'maxMessage' => '50 caractères max.']),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['placeholder' => 'Votre nom', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis.']),
                    new Length(['max' => 50, 'maxMessage' => '50 caractères max.']),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'attr'  => ['placeholder' => 'Ex : 06 12 34 56 78', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => 'Le numéro de téléphone est requis.']),
                    new Length(['max' => 20, 'maxMessage' => '20 caractères max.']),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse',
                'attr'  => ['placeholder' => 'Votre adresse', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => "L'adresse est requise."]),
                    new Length(['max' => 255]),
                ],
            ])
            ->add('codePostal', IntegerType::class, [
                'label' => 'Code postal',
                'attr'  => ['placeholder' => 'Ex : 75001', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est requis.']),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr'  => ['placeholder' => 'Votre ville', 'class' => 'profile-input'],
                'constraints' => [
                    new NotBlank(['message' => 'La ville est requise.']),
                    new Length(['max' => 100]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
