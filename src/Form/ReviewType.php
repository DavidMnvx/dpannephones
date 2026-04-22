<?php

namespace App\Form;

use App\Entity\Article;
use App\Entity\Review;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $displayChoices = $options['display_choices'] ?? [
            'Mon prénom complet (ex. Thomas)'        => Review::DISPLAY_FIRSTNAME,
            'Mon prénom + initiale (ex. Thomas G.)'  => Review::DISPLAY_INITIAL,
            'Mon nom complet (ex. Thomas Girard)'    => Review::DISPLAY_FULL,
            'Anonyme (Client vérifié)'               => Review::DISPLAY_ANONYMOUS,
        ];

        // Champ article : visible uniquement si on a au moins un article disponible.
        // Le client choisit entre un article précis ou "Avis général" (null).
        $commandeArticles   = $options['commande_articles'];
        $reviewedArticleIds = $options['reviewed_article_ids'];
        $hasGeneralReview   = $options['has_general_review'];

        if (!empty($commandeArticles)) {
            $builder->add('article', EntityType::class, [
                'class'        => Article::class,
                'choices'      => $commandeArticles,
                'required'     => false,        // null = avis général
                'placeholder'  => false,         // pas d'option vide automatique (on gère "général" via un bouton)
                'expanded'     => true,
                'label'        => 'Sur quel article souhaitez-vous laisser un avis ?',
                'choice_label' => fn(Article $a) => $a->getName(),
                'choice_attr'  => function (Article $article) use ($reviewedArticleIds) {
                    return [
                        'data-reviewed'     => in_array($article->getId(), $reviewedArticleIds, true) ? '1' : '0',
                        'data-article-id'   => $article->getId(),
                    ];
                },
                // Désactiver les options déjà notées
                'disabled'     => false,
            ]);
        }

        $builder
            ->add('rating', ChoiceType::class, [
                'label'    => 'Votre note',
                'expanded' => true,
                'choices'  => [
                    '⭐⭐⭐⭐⭐ Excellent'  => 5,
                    '⭐⭐⭐⭐ Très bien'    => 4,
                    '⭐⭐⭐ Bien'           => 3,
                    '⭐⭐ Moyen'            => 2,
                    '⭐ Décevant'          => 1,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de sélectionner une note.']),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre de votre avis',
                'attr'  => [
                    'placeholder' => 'Ex. Service rapide et professionnel',
                    'maxlength'   => 100,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de donner un titre à votre avis.']),
                    new Assert\Length([
                        'min' => 5, 'max' => 100,
                        'minMessage' => 'Le titre doit faire au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le titre ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Votre commentaire',
                'attr'  => [
                    'rows'        => 6,
                    'placeholder' => 'Décrivez votre expérience : accueil, service, produit, délais…',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Merci de décrire votre expérience.']),
                    new Assert\Length([
                        'min' => 20, 'max' => 2000,
                        'minMessage' => 'Votre commentaire doit faire au moins {{ limit }} caractères.',
                        'maxMessage' => 'Votre commentaire ne doit pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('displayNameChoice', ChoiceType::class, [
                'label'    => 'Comment souhaitez-vous apparaître ?',
                'choices'  => $displayChoices,
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'           => Review::class,
            'display_choices'      => null,
            'commande_articles'    => [],
            'reviewed_article_ids' => [],
            'has_general_review'   => false,
        ]);
    }
}
