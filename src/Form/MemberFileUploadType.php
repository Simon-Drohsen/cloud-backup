<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MemberFileUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Datei',
            'mapped' => false,
            // 'multiple' => true,
            'constraints' => [
                new Assert\NotNull(message: 'Bitte wähle eine Datei aus.'),
                new Assert\File(
                    maxSize: '50000M',
                    maxSizeMessage: 'Die Datei ist zu gross (maximal {{ limit }}).'
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
        ]);
    }
}
