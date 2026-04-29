<?php
// src/Form/ContactType.php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName', TextType::class, [
                'label'    => 'Prénom',
                'required' => true,
            ])
            ->add('lastName', TextType::class, [
                'label'    => 'Nom',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label'    => 'Email',
                'required' => true,
            ])
            ->add('phone', TelType::class, [
                'label'    => 'Téléphone',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label'    => 'Message',
                'required' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer le message',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
