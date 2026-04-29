<?php
namespace App\Form;


use App\Entity\Model;
use App\Entity\Marque;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du modèle',
            ])
            ->add('marque', EntityType::class, [
                'class' => Marque::class,
                'choice_label' => 'name',
                'label' => 'Marque',
            ])
            ->add('famille', ChoiceType::class, [
                'label'       => 'Famille / Sous-catégorie',
                'required'    => false,
                'placeholder' => '— Aucune (affichage plat) —',
                'choices'     => [
                    'Samsung — Série A'      => 'Série A',
                    'Samsung — Série S'      => 'Série S',
                    'Samsung — Galaxy Z'     => 'Galaxy Z',
                    'Samsung — Galaxy Note'  => 'Galaxy Note',
                    'Xiaomi — Redmi'         => 'Redmi',
                    'Xiaomi — Mi'            => 'Mi',
                    'Xiaomi — Poco'          => 'Poco',
                ],
                'help'        => 'Optionnel — sert à grouper les modèles d\'une marque sur la page publique (ex: Samsung Série A vs Série S).',
            ])
            ->add('image', FileType::class, [
                'label' => 'Image du modèle',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide.',
                    ])
                ],
            ])
            ->add('hasPhone', CheckboxType::class, [
                'label'    => 'Téléphone',
                'required' => false,
            ])
            ->add('hasTablet', CheckboxType::class, [
                'label'    => 'Tablette',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Model::class,
        ]);
    }
}