<?php

namespace App\Form;

use App\Entity\Consultation;
use App\Entity\RendezVous;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConsultationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Consultation|null $currentConsultation */
        $currentConsultation = $options['consultation'];

        $currentRdvId = $currentConsultation?->getRendezVous()?->getId();

        $builder
            ->add('diagnostic', TextareaType::class, [
                'label' => 'Diagnostic',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'ac-input',
                    'placeholder' => 'Saisir le diagnostic...',
                ],
            ])

            ->add('prescription', TextareaType::class, [
                'label' => 'Prescription',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'ac-input',
                    'placeholder' => 'Saisir la prescription...',
                ],
            ])

            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'class' => 'ac-input',
                    'placeholder' => 'Notes supplémentaires...',
                ],
            ])

            ->add('rendezVous', EntityType::class, [
                'class' => RendezVous::class,
                'label' => 'Rendez-vous approuvés',
                'placeholder' => 'Choisir un rendez-vous validé',

                'choice_label' => static function (RendezVous $rdv): string {
                    $patient = $rdv->getPatient();

                    $name = null;

                    if ($patient && $patient->getProfile()) {
                        $name = trim(
                            ($patient->getProfile()->getFirstName() ?? '') . ' ' .
                            ($patient->getProfile()->getLastName() ?? '')
                        );
                    }

                    if (!$name) {
                        $name = $patient?->getEmail() ?? 'Patient';
                    }

                    $date = $rdv->getDateTime()
                        ? $rdv->getDateTime()->format('d/m/Y H:i')
                        : 'Sans date';

                    return $name . ' - ' . $date;
                },

                'query_builder' => function (
                    EntityRepository $er
                ) use ($currentRdvId) {
                    $qb = $er->createQueryBuilder('r')
                        ->leftJoin('r.consultation', 'c')
                        ->andWhere('r.status = :status')
                        ->setParameter('status', 'VALIDE')
                        ->orderBy('r.dateTime', 'DESC');

                    if ($currentRdvId) {
                        $qb->andWhere('c.id IS NULL OR r.id = :currentId')
                           ->setParameter('currentId', $currentRdvId);
                    } else {
                        $qb->andWhere('c.id IS NULL');
                    }

                    return $qb;
                },

                'attr' => [
                    'class' => 'ac-input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'   => Consultation::class,
            'consultation' => null,
        ]);
    }
}
