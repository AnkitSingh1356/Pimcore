<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('dateOfBirth', DateType::class)
            ->add('gender', ChoiceType::class, [
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female'
                ]
            ])
            ->add('address', TextareaType::class)
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive'
                ]
            ])
            ->add('city', TextType::class, ['required' => false])
            ->add('country', TextType::class, ['required' => false])
            ->add('channel', ChoiceType::class, [
                'required' => false,
                'placeholder' => '-- Select channel --',
                'choices' => [
                    'Web' => 'web', 'Mobile' => 'mobile', 'Retail' => 'retail',
                    'Wholesale' => 'wholesale', 'Partner' => 'partner', 'Other' => 'other',
                ],
            ])
            ->add('customerType', ChoiceType::class, [
                'required' => false,
                'label' => 'Customer type',
                'placeholder' => '-- Select type --',
                'choices' => [
                    'Individual' => 'individual', 'Business' => 'business',
                    'VIP' => 'vip', 'Partner' => 'partner',
                ],
            ])
            ->add('preferredSport', TextType::class, [
                'required' => false,
                'label' => 'Preferred sport(s)',
                'attr' => ['placeholder' => 'e.g. football,cricket'],
            ])
            ->add('newsletterOptin', CheckboxType::class, [
                'required' => false,
                'label' => 'Newsletter opt-in',
            ]);
    }
}
