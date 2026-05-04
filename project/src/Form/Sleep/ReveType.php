<?php

namespace App\Form\Sleep;

use App\Entity\Sleep\Reves;
use App\Entity\Sleep\Sommeil;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReveType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sommeil', EntityType::class, [
                'label' => 'Nuit associée',
                'class' => Sommeil::class,
                'required' => true,
                'placeholder' => 'Choisir une nuit',
                'choice_label' => function (Sommeil $sommeil): string {
                    return ($sommeil->getDateNuit()?->format('d/m/Y') ?? 'Date inconnue')
                        . ' — ' . ($sommeil->getHeureCoucher() ?? '?')
                        . ' → ' . ($sommeil->getHeureReveil() ?? '?')
                        . ' (' . ($sommeil->getQualite() ?? '?') . ')';
                },
                'attr' => ['class' => 'form-control'],
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre du rêve',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Vol au-dessus des nuages...',
                    'maxlength' => 200,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                ],
            ])
            ->add('humeur', ChoiceType::class, [
                'label' => 'Humeur',
                'required' => false,
                'placeholder' => 'Choisir une humeur',
                'choices' => [
                    '😄 Joyeux'  => '😄 Joyeux',
                    '😢 Triste'  => '😢 Triste',
                    '😨 Effrayé' => '😨 Effrayé',
                    '😌 Serein'  => '😌 Serein',
                    '😐 Neutre'  => '😐 Neutre',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('typeReve', ChoiceType::class, [
                'label' => 'Type de rêve',
                'required' => true,
                'placeholder' => 'Choisir un type',
                'choices' => [
                    'Normal'    => 'Normal',
                    'Lucide'    => 'Lucide',
                    'Cauchemar' => 'Cauchemar',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('intensite', IntegerType::class, [
                'label' => 'Intensité (1-10)',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'max' => 10,
                ],
            ])
            ->add('couleur', CheckboxType::class, [
                'label' => 'Rêve en couleur ?',
                'required' => false,
            ])
            ->add('emotions', TextType::class, [
                'label' => 'Émotions ressenties',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: joie, peur, surprise...',
                    'maxlength' => 200,
                ],
            ])
            ->add('symboles', TextareaType::class, [
                'label' => 'Symboles',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 2,
                ],
            ])
            ->add('recurrent', CheckboxType::class, [
                'label' => 'Rêve récurrent ?',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reves::class,
        ]);
    }
}