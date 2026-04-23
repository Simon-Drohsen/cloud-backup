<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('userName', TextType::class, [
                'label' => 'Benutzername',
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte einen Benutzernamen eingeben.'),
                    new Assert\Length(max: 190),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail',
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte eine E-Mail-Adresse eingeben.'),
                    new Assert\Email(message: 'Bitte eine gültige E-Mail-Adresse eingeben.'),
                    new Assert\Length(max: 190),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Passwort',
                'constraints' => [
                    new Assert\NotBlank(message: 'Bitte ein Passwort eingeben.'),
                    new Assert\Length(min: 8, minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen lang sein.'),
                ],
            ])
            ->add('acceptTerms', CheckboxType::class, [
                'label' => 'Ich akzeptiere die Nutzungsbedingungen',
                'constraints' => [
                    new Assert\IsTrue(message: 'Bitte die Nutzungsbedingungen akzeptieren.'),
                ],
            ])
            ->add('createCompany', CheckboxType::class, [
                'label' => 'Firma mit anlegen',
                'required' => false,
            ])
            ->add('companyName', TextType::class, [
                'label' => 'Firmenname',
                'required' => false,
                'empty_data' => '',
                'constraints' => [
                    new Assert\Length(max: 190),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [
                new Assert\Callback([$this, 'validateCompanyData']),
            ],
        ]);
    }

    public function validateCompanyData(mixed $data, ExecutionContextInterface $context): void
    {
        if (! is_array($data)) {
            return;
        }

        $createCompany = (bool) ($data['createCompany'] ?? false);
        $companyName = trim((string) ($data['companyName'] ?? ''));

        if ($createCompany && $companyName === '') {
            $context->buildViolation('Bitte einen Firmennamen eingeben, wenn eine Firma erstellt werden soll.')
                ->atPath('[companyName]')
                ->addViolation();
        }
    }
}
