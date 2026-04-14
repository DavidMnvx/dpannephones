<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ── Base fields ──────────────────────────────────────────────────
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['rows' => 3],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix (€)',
                'scale' => 2,
            ])
            ->add('grade', ChoiceType::class, [
                'label'       => 'Grade',
                'choices'     => [
                    'A+ (Comme neuf)'  => 'A+',
                    'A (Très bon état)' => 'A',
                    'B (Bon état)'      => 'B',
                    'C (État correct)'  => 'C',
                ],
                'placeholder' => 'Choisir un grade',
            ])
            ->add('categorie', ChoiceType::class, [
                'label'       => 'Catégorie',
                'required'    => false,
                'placeholder' => 'Choisir une catégorie',
                'choices'     => [
                    'PC Gamer'       => 'pc_gamer',
                    'PC Bureautique' => 'pc_bureautique',
                    'PC Portable'    => 'pc_portable',
                    'Accessoires'    => 'accessoires',
                    'Film Hydrogel'  => 'film_hydrogel',
                    'Téléphones'     => 'telephone',
                ],
            ])
            ->add('isNew', CheckboxType::class, [
                'label'    => 'Article neuf',
                'required' => false,
            ])
            ->add('imageFilename', FileType::class, [
                'label'       => "Image de l'article",
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '2M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPEG, PNG ou WEBP).',
                    ])
                ],
            ])

            // ── PC Gamer specs ────────────────────────────────────────────────
            ->add('gamer_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_gpu', TextType::class, [
                'label'    => 'Carte graphique',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_ram', TextType::class, [
                'label'    => 'RAM (ex: 32 Go DDR5)',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_storage', TextType::class, [
                'label'    => 'Stockage (ex: 1 To SSD NVMe)',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_motherboard', TextType::class, [
                'label'    => 'Carte mère',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_psu', TextType::class, [
                'label'    => 'Alimentation',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_case', TextType::class, [
                'label'    => 'Boîtier',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_cooling', TextType::class, [
                'label'    => 'Refroidissement',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('gamer_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
            ])

            // ── PC Bureautique specs ──────────────────────────────────────────
            ->add('bureau_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('bureau_ram', TextType::class, [
                'label'    => 'RAM (ex: 16 Go DDR4)',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('bureau_storage', TextType::class, [
                'label'    => 'Stockage',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('bureau_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
            ])
            ->add('bureau_screen', TextType::class, [
                'label'    => 'Taille écran',
                'mapped'   => false,
                'required' => false,
            ])

            // ── PC Portable specs ─────────────────────────────────────────────
            ->add('portable_cpu', TextType::class, [
                'label'    => 'Processeur',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_gpu', TextType::class, [
                'label'    => 'Carte graphique (si dédié)',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_ram', TextType::class, [
                'label'    => 'RAM',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_storage', TextType::class, [
                'label'    => 'Stockage',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_os', TextType::class, [
                'label'    => "Système d'exploitation",
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_screen', TextType::class, [
                'label'    => 'Taille écran',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('portable_battery', TextType::class, [
                'label'    => 'Autonomie batterie',
                'mapped'   => false,
                'required' => false,
            ])

            // ── Film Hydrogel specs ───────────────────────────────────────────
            ->add('hydrogel_models', TextareaType::class, [
                'label'    => 'Modèles compatibles (un par ligne)',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'rows'        => 6,
                    'placeholder' => "iPhone 15\niPhone 15 Pro\nSamsung Galaxy S24\n...",
                ],
            ])

            // ── Téléphone specs ───────────────────────────────────────────────
            ->add('tel_storage', TextType::class, [
                'label'    => 'Capacité stockage',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('tel_color', TextType::class, [
                'label'    => 'Couleur',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('tel_network', TextType::class, [
                'label'    => 'Réseau (4G / 5G)',
                'mapped'   => false,
                'required' => false,
            ])
            ->add('tel_battery', TextType::class, [
                'label'    => 'Santé batterie (%)',
                'mapped'   => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
