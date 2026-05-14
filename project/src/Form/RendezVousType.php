<?php

namespace App\Form;

use App\Entity\RendezVous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('dateTime', DateTimeType::class, [
            'label' => 'Date et heure',
            'widget' => 'single_text',
            'input' => 'datetime',
            'required' => true,
            'html5' => true,
        
            // ✅ CORRECT
            'data' => new \DateTime('+1 day'),
        
            'attr' => [
                'class' => 'form-control'
            ]
        ])
            ->add('motif', TextType::class, [
                'label' => 'Motif',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
                    'csrf_protection' => false,

            'data_class' => RendezVous::class,
        ]);
    }
}
