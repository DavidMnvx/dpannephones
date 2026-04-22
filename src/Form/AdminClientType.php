<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label'       => 'Prénom',
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est requis.']),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label'       => 'Nom',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis.']),
                    new Length(['max' => 50]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label'       => 'Email',
                'constraints' => [
                    new NotBlank(['message' => "L'email est requis."]),
                    new Email(['message' => 'Adresse email invalide.']),
                ],
            ])
            ->add('phone', TextType::class, [
                'label'       => 'Téléphone',
                'constraints' => [
                    new NotBlank(['message' => 'Le téléphone est requis.']),
                    new Length(['max' => 20]),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label'       => 'Adresse',
                'constraints' => [
                    new NotBlank(['message' => "L'adresse est requise."]),
                    new Length(['max' => 255]),
                ],
            ])
            ->add('codePostal', IntegerType::class, [
                'label'       => 'Code postal',
                'constraints' => [
                    new NotBlank(['message' => 'Le code postal est requis.']),
                ],
            ])
            ->add('ville', TextType::class, [
                'label'       => 'Ville',
                'constraints' => [
                    new NotBlank(['message' => 'La ville est requise.']),
                    new Length(['max' => 100]),
                ],
            ])
            ->add('isVerified', CheckboxType::class, [
                'label'    => 'Compte vérifié',
                'required' => false,
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
