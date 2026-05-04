<?php

namespace App\Form\Sleep;

use App\Entity\Sleep\Sommeil;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SommeilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateNuit', DateType::class, [
                'label' => 'Date de la nuit',
                'widget' => 'single_text',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'max' => (new \DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('heureCoucher', TimeType::class, [
                'label' => 'Heure de coucher',
                'widget' => 'single_text',
                'input' => 'string',
                'required' => true,
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('heureReveil', TimeType::class, [
                'label' => 'Heure de réveil',
                'widget' => 'single_text',
                'input' => 'string',
                'required' => true,
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('qualite', ChoiceType::class, [
                'label' => 'Qualité du sommeil',
                'required' => true,
                'placeholder' => 'Choisir une qualité',
                'choices' => [
                    'Excellente' => 'Excellente',
                    'Bonne'      => 'Bonne',
                    'Moyenne'    => 'Moyenne',
                    'Mauvaise'   => 'Mauvaise',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'maxlength' => 1000,
                ],
            ])
            ->add('dureeSommeil', NumberType::class, [
                'label' => 'Durée (heures)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.5',
                    'min' => '0',
                    'max' => '24',
                ],
            ])
            ->add('interruptions', IntegerType::class, [
                'label' => "Nombre d'interruptions",
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                ],
            ])
            ->add('humeurReveil', ChoiceType::class, [
                'label' => 'Humeur au réveil',
                'required' => false,
                'placeholder' => 'Choisir une humeur',
                'choices' => [
                    '😌 Reposé'   => '😌 Reposé',
                    '😄 Joyeux'   => '😄 Joyeux',
                    '😐 Neutre'   => '😐 Neutre',
                    '😴 Fatigué'  => '😴 Fatigué',
                    '⚡ Énergisé' => '⚡ Énergisé',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('environnement', ChoiceType::class, [
                'label' => 'Environnement',
                'required' => false,
                'placeholder' => 'Choisir un environnement',
                'choices' => [
                    '🏠 Normal'       => '🏠 Normal',
                    '🌿 Calme'        => '🌿 Calme',
                    '😊 Confortable'  => '😊 Confortable',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Température (°C)',
                'required' => false,
                'scale' => 1,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.1',
                    'min' => '10',
                    'max' => '40',
                ],
            ])
            ->add('bruitNiveau', ChoiceType::class, [
                'label' => 'Niveau de bruit',
                'required' => false,
                'placeholder' => 'Choisir un niveau',
                'choices' => [
                    '🔇 Silencieux' => '🔇 Silencieux',
                    '🔉 Léger'      => '🔉 Léger',
                    '🔉 Modéré'     => '🔉 Modéré',
                    '🔊 Fort'       => '🔊 Fort',
                ],
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sommeil::class,
        ]);
    }
}