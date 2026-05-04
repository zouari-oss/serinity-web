<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\ForumThread;
use App\Enum\ThreadType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ForumThreadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('content', TextareaType::class)
            ->add('category', EntityType::class, [
                'class' => Category::class,
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Discussion' => ThreadType::DISCUSSION,
                    'Question' => ThreadType::QUESTION,
                    'Announcement' => ThreadType::ANNOUNCEMENT,
                ],
            ])
            ->add('isPinned', CheckboxType::class, ['required' => false])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumThread::class,
        ]);
    }
}
