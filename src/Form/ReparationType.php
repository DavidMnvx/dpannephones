<?php


namespace App\Form;

use App\Entity\Marque;
use App\Entity\Model;
use App\Entity\Reparation;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ReparationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('model', EntityType::class, [
                'class' => Model::class,
                'choice_label' => function (Model $m) {
                    return ($m->getMarque() ? $m->getMarque()->getName() . ' — ' : '') . $m->getName();
                },
                'group_by' => function (Model $m) {
                    return $m->getMarque() ? $m->getMarque()->getName() : 'Sans marque';
                },
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('m')
                        ->leftJoin('m.marque', 'mq')
                        ->orderBy('mq.name', 'ASC')
                        ->addOrderBy('m.name', 'ASC');
                },
                'label' => 'Modèle concerné',
                'placeholder' => '— Choisir un modèle —',
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de la réparation',
                'attr'  => ['placeholder' => 'Ex: Écran, Batterie, Vitre arrière…'],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description (optionnelle)',
                'required' => false,
                'attr'  => ['rows' => 2, 'placeholder' => 'Détails techniques, garantie, durée…'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'help'  => 'Laisse 0 (ou vide) pour placer à la fin automatiquement. Sinon, plus petit = affiché en premier.',
                'required' => false,
                'empty_data' => '0',
                'attr' => ['min' => 0, 'max' => 9999, 'placeholder' => '0 = auto'],
            ])
            ->add('hasPhone', CheckboxType::class, [
                'label'    => 'Disponible pour téléphone',
                'required' => false,
            ])
            ->add('hasTablet', CheckboxType::class, [
                'label'    => 'Disponible pour tablette',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Reparation::class,
        ]);
    }
}