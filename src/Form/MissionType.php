<?php

namespace App\Form;

use App\Entity\Mission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('serviceDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Service Date',
                'required' => true,
            ])
            ->add('productName', TextType::class, [
                'label' => 'Product Name',
                'required' => true,
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'required' => true,
                'attr' => [
                    'min' => 1
                ]
            ])
            ->add('destinationCountry', TextType::class, [
                'label' => 'Destination Country',
                'required' => true,
            ])
            ->add('vendorName', TextType::class, [
                'label' => 'Vendor Name',
                'required' => true,
            ])
            ->add('vendorEmail', EmailType::class, [
                'label' => 'Vendor Email',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mission::class,
        ]);
    }
}
