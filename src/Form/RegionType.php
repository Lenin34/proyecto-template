<?php

namespace App\Form;

use App\Entity\App\Region;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'NOMBRE *',
                'row_attr' => ['class' => 'col-md-12 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            /*->add('status', ChoiceType::class, [
                'label' => 'ESTADO *',
                'choices' => [
                    'Activo' => Status::ACTIVE,
                    'Inactivo' => Status::INACTIVE,
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Region::class,
        ]);
    }
}