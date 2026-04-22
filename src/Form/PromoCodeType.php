<?php

namespace App\Form;

use App\Entity\PromoCode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class PromoCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label'    => 'Code promo',
                'help'     => 'Ex: BIENVENUE10, NOEL2026 (saisi par le client tel quel — sera automatiquement mis en majuscules).',
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le code est obligatoire.'),
                    new Length(min: 3, max: 50, minMessage: 'Minimum 3 caractères.'),
                    new Regex(
                        pattern: '/^[A-Z0-9_-]+$/i',
                        message: 'Lettres, chiffres, tirets et underscores uniquement (pas d\'espaces ni d\'accents).',
                    ),
                ],
                'attr'     => [
                    'placeholder' => 'BIENVENUE10',
                    'style'       => 'text-transform:uppercase;',
                ],
            ])

            ->add('type', ChoiceType::class, [
                'label'       => 'Type de remise',
                'choices'     => [
                    'Pourcentage (ex: -10%)' => PromoCode::TYPE_PERCENTAGE,
                    'Montant fixe en € (ex: -10€)' => PromoCode::TYPE_FIXED,
                ],
                'expanded'    => true,
                'multiple'    => false,
                'required'    => true,
            ])

            ->add('value', NumberType::class, [
                'label'    => 'Valeur de la remise',
                'help'     => 'Pour un pourcentage : entrez 10 pour -10%. Pour un montant fixe : entrez 10 pour -10€.',
                'scale'    => 2,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'La valeur est obligatoire.'),
                    new GreaterThanOrEqual(value: 0.01, message: 'La valeur doit être supérieure à 0.'),
                ],
            ])

            ->add('isActive', CheckboxType::class, [
                'label'    => 'Code actif',
                'help'     => 'Décocher pour désactiver temporairement sans supprimer.',
                'required' => false,
            ])

            ->add('validFrom', DateType::class, [
                'label'      => 'Actif à partir du (optionnel)',
                'help'       => 'Laisser vide pour "actif immédiatement".',
                'required'   => false,
                'widget'     => 'single_text',
                'html5'      => true,
                'empty_data' => '',
            ])

            ->add('validUntil', DateType::class, [
                'label'      => 'Expire le (optionnel)',
                'help'       => 'Laisser vide pour "pas d\'expiration".',
                'required'   => false,
                'widget'     => 'single_text',
                'html5'      => true,
                'empty_data' => '',
            ])

            ->add('usageLimit', IntegerType::class, [
                'label'    => 'Nombre max d\'utilisations (optionnel)',
                'help'     => 'Ex: 100 = le code fonctionne pour les 100 premières commandes. Laisser vide pour "illimité".',
                'required' => false,
                'constraints' => [
                    new GreaterThanOrEqual(value: 1, message: 'Au moins 1.'),
                ],
            ])

            ->add('minCartAmount', MoneyType::class, [
                'label'    => 'Montant minimum du panier (optionnel)',
                'help'     => 'Ex: 50 = le code ne fonctionne que pour un panier ≥ 50€. Laisser vide si pas de minimum.',
                'required' => false,
                'currency' => 'EUR',
                'scale'    => 2,
            ])

            ->add('description', TextareaType::class, [
                'label'    => 'Note interne (optionnel)',
                'help'     => 'Pour votre usage — ex: "Campagne newsletter juin 2026". Non visible par le client.',
                'required' => false,
                'attr'     => ['rows' => 2],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PromoCode::class,
        ]);
    }
}
