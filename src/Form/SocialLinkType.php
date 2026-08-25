<?php

namespace App\Form;

use App\Entity\SocialLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class SocialLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Construction du tableau de choix depuis les métadonnées de l'entity
        $platformChoices = [];
        foreach (SocialLink::PLATFORMS as $key => $meta) {
            $platformChoices[$meta['label']] = $key;
        }

        $builder
            ->add('platform', ChoiceType::class, [
                'label'    => 'Plateforme',
                'choices'  => $platformChoices,
                'required' => true,
                'help'     => 'Choisissez "Autre" si la plateforme n\'est pas listée.',
            ])

            ->add('url', UrlType::class, [
                'label'       => 'URL complète',
                'help'        => 'Ex: https://www.facebook.com/dpannephones',
                'required'    => true,
                'default_protocol' => 'https',
                'constraints' => [
                    new NotBlank(message: 'L\'URL est obligatoire.'),
                    new Url(message: 'L\'URL n\'est pas valide. Format attendu : https://…'),
                ],
                'attr' => ['placeholder' => 'https://www.facebook.com/…'],
            ])

            ->add('label', TextType::class, [
                'label'    => 'Libellé affiché (optionnel)',
                'help'     => 'Par défaut : nom de la plateforme. Utile pour "Autre" ou pour afficher "D\'Panne Phones" au lieu de "Facebook".',
                'required' => false,
            ])

            ->add('sortOrder', IntegerType::class, [
                'label'    => 'Ordre d\'affichage',
                'help'     => 'Plus petit = affiché en premier. Laissez 0 si vous ne voulez pas trier.',
                'required' => false,
                'empty_data' => '0',
            ])

            ->add('isActive', CheckboxType::class, [
                'label'    => 'Lien actif',
                'help'     => 'Décocher pour masquer temporairement le lien sans le supprimer.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SocialLink::class,
        ]);
    }
}
