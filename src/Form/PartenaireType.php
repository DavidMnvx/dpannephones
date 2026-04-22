<?php

namespace App\Form;

use App\Entity\Partenaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PartenaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du partenaire',
                'attr'  => ['placeholder' => 'Ex. Boulangerie du Village, Garage Martin…'],
            ])
            ->add('type', ChoiceType::class, [
                'label'   => 'Type d\'affichage',
                'choices' => [
                    'Premium — card complète avec description et bouton'  => Partenaire::TYPE_PREMIUM,
                    'Normal — image cliquable avec titre dessous'         => Partenaire::TYPE_NORMAL,
                ],
                'help'    => 'Premium = article de présentation. Normal = logo cliquable.',
            ])
            ->add('image', FileType::class, [
                'label'       => 'Image / Logo',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'   => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/svg+xml',
                        ],
                        'mimeTypesMessage' => 'Formats acceptés : JPEG, PNG, WEBP, SVG (max 4 Mo).',
                    ])
                ],
                'help' => 'Conseil : logo carré ou paysage, fond transparent de préférence.',
            ])
            ->add('link', UrlType::class, [
                'label'    => 'Lien (URL)',
                'required' => false,
                'attr'     => ['placeholder' => 'https://…  ou  https://g.co/kgs/…'],
                'help'     => 'Site internet, page Google Business, Facebook, Instagram…',
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description (Premium uniquement)',
                'required' => false,
                'attr'     => ['rows' => 5, 'placeholder' => 'Présentation du partenaire, ses services, son histoire…'],
                'help'     => 'Ce texte n\'apparaît que pour les partenaires Premium.',
            ])
            ->add('category', TextType::class, [
                'label'    => 'Catégorie',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex. Commerce local, Garage, Restaurant…'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label'    => 'Ordre d\'affichage',
                'required' => false,
                'attr'     => ['placeholder' => '0'],
                'help'     => 'Plus petit = affiché en premier.',
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Actif (visible sur le site)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Partenaire::class,
        ]);
    }
}
