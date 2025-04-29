<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MissionFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('productName', TextType::class, [
                'required' => false,
                'label' => 'Product Name',
                'attr' => ['placeholder' => 'Search by product name...']
            ])
            ->add('destinationCountry', TextType::class, [
                'required' => false,
                'label' => 'Destination Country',
                'attr' => ['placeholder' => 'Filter by country...']
            ])
            ->add('serviceDate', DateType::class, [
                'required' => false,
                'label' => 'Service Date',
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'method' => 'GET',
        ]);
    }
}
