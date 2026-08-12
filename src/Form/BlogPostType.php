<?php

namespace App\Form;

use App\Entity\BlogPost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class BlogPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => "Titre de l'article",
                'attr'  => ['placeholder' => 'Ex : Réparer ou remplacer son téléphone : le vrai calcul'],
            ])
            ->add('topic', ChoiceType::class, [
                'label'       => 'Thème',
                'required'    => false,
                'placeholder' => '— Aucun thème —',
                'choices'     => array_flip(BlogPost::TOPICS),
            ])
            ->add('excerpt', TextareaType::class, [
                'label'    => 'Chapo (accroche affichée dans la liste)',
                'required' => false,
                'attr'     => ['rows' => 2, 'placeholder' => "1 à 2 phrases. À défaut, le début de l'article est utilisé."],
            ])
            ->add('content', TextareaType::class, [
                'label' => "Contenu de l'article",
                'attr'  => ['rows' => 18],
                'help'  => "Mise en forme : ligne vide = nouveau paragraphe · « ## Mon sous-titre » = sous-titre · « * élément » = liste à puces · « [texte](https://…) » = lien (produits, catégories, services…).",
            ])
            ->add('imageFile', FileType::class, [
                'label'       => 'Image de couverture',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new File(maxSize: '8M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPG, PNG, WebP'),
                ],
            ])
            ->add('slug', TextType::class, [
                'label'    => 'URL personnalisée (slug)',
                'required' => false,
                'help'     => "L'article sera visible sur /conseils/<slug>. Laissez vide pour le générer depuis le titre.",
                'attr'     => ['placeholder' => 'reparer-ou-remplacer-son-telephone'],
            ])
            ->add('seoTitle', TextType::class, [
                'label'    => 'Titre SEO (balise <title>)',
                'required' => false,
                'help'     => "60 caractères conseillés. À défaut, le titre de l'article est utilisé.",
            ])
            ->add('metaDescription', TextareaType::class, [
                'label'    => 'Méta-description',
                'required' => false,
                'attr'     => ['rows' => 2, 'maxlength' => 300],
                'help'     => '150 à 160 caractères conseillés — le texte affiché sous le lien dans Google.',
            ])
            ->add('isPublished', ChoiceType::class, [
                'label'    => 'Statut',
                'choices'  => ['Brouillon (invisible sur le site)' => false, 'Publié' => true],
                'expanded' => true,
                'multiple' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogPost::class,
        ]);
    }
}
