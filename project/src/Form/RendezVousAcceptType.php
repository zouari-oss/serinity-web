<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezVousAcceptType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $rendezVous = $options['rendez_vous'];



        $builder
            ->add('proposedDateTime', DateTimeType::class, [
                'label' => 'Nouvelle date proposée',
                'widget' => 'single_text',
                 'data' => $rendezVous ? $rendezVous->getDateTime() : null,
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('doctorNote', TextareaType::class, [
                'label' => 'Note du médecin',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
            'rendez_vous' => null, // 👈 option personnalisée

        ]);
    }
}
