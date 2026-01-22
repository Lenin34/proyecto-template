<?php

namespace App\Form;

use App\Entity\App\Beneficiary;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class BeneficiaryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photo', FileType::class, [
                'label' => 'AÑADIR FOTO *',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'accept' => 'image/*',
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
            ])
            ->add('name', TextType::class, [
                'label' => 'NOMBRE *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('last_name', TextType::class, [
                'label' => 'APELLIDOS *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('kinship', ChoiceType::class, [
                'label' => 'PARENTESCO *',
                'placeholder' => 'Selecciona un parentesco',
                'choices' => [
                    'Padre' => 'Padre',
                    'Madre' => 'Madre',
                    'Hijo/a' => 'Hijo/a',
                    'Hermano/a' => 'Hermano/a',
                    'Cónyuge' => 'Cónyuge',
                    'Abuelo/a' => 'Abuelo/a',
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
        
            ->add('birthday', DateType::class, [
                'widget' => 'single_text',
                'label' => 'FECHA DE NACIMIENTO *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])    
            ->add('curp', TextType::class, [
                'label' => 'CURP *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'GÉNERO *',
                'placeholder' => 'Selecciona un genero',
                'choices' => [
                    'Femenino' => 'Femenino',
                    'Masculino' => 'Masculino',
                    'Otro' => 'Otro',
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('education', ChoiceType::class, [
                'label' => 'NIVEL DE EDUCACIÓN *',
                'placeholder' => 'Selecciona un nivel de educación',
                'choices' => [
                    'Prescolar' => 'Prescolar',
                    'Primaria' => 'Primaria',
                    'Secundaria' => 'Secundaria',
                    'Preparatoria / Bachillerato' => 'Preparatoria',
                    'Universidad / Licenciatura' => 'Universidad',
                    'Posgrado' => 'Posgrado',
                    'Otro' => 'Otro',
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            /*->add('user_display', HiddenType::class, [
                'mapped' => false,
                'data' => $options['user_name'] ?? '',
            ])*/
            
            ->add('user_id', HiddenType::class, [
                'mapped' => false,
                'data' => $options['user_id'] ?? null,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Beneficiary::class,
            'user_name' => null,
            'user_id' => null,
        ]);
    }
}
