<?php

namespace App\Form;

use App\Entity\GoogleReview;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GoogleReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authorName', TextType::class, [
                'label' => 'Nom du client',
                'attr'  => ['placeholder' => 'Ex. Marie Laurent'],
            ])
            ->add('rating', ChoiceType::class, [
                'label'   => 'Note (étoiles)',
                'choices' => [
                    '⭐⭐⭐⭐⭐ (5 étoiles)' => 5,
                    '⭐⭐⭐⭐ (4 étoiles)'   => 4,
                    '⭐⭐⭐ (3 étoiles)'     => 3,
                    '⭐⭐ (2 étoiles)'       => 2,
                    '⭐ (1 étoile)'          => 1,
                ],
            ])
            ->add('relativeTime', TextType::class, [
                'label'    => 'Date (texte libre)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. il y a 2 semaines'],
                'help'     => 'Copiez exactement ce que Google affiche (ex. "il y a 3 mois").',
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Commentaire',
                'attr'  => ['rows' => 5, 'placeholder' => 'Texte de l\'avis laissé par le client sur Google'],
            ])
            ->add('profilePhotoUrl', UrlType::class, [
                'label'    => 'URL photo de profil',
                'required' => false,
                'attr'     => ['placeholder' => 'https://…'],
                'help'     => 'Optionnel. Laissez vide pour générer des initiales colorées automatiquement.',
            ])
            ->add('sortOrder', IntegerType::class, [
                'label'    => 'Ordre d\'affichage',
                'required' => false,
                'attr'     => ['placeholder' => '0'],
                'help'     => 'Plus petit = affiché en premier.',
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Actif (visible sur la page d\'accueil)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GoogleReview::class,
        ]);
    }
}
