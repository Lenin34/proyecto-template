<?php

namespace App\Form;

use App\Entity\App\Region;
use App\Entity\App\SocialMedia;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

class SocialMediaType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Título',
                'required' => true,
                'attr' => [
                    'maxlength' => 150,
                    'placeholder' => 'Máximo 150 caracteres'
                ],
                'constraints' => [
                    new Length([
                        'max' => 150,
                        'maxMessage' => 'El título no puede tener más de {{ limit }} caracteres.'
                    ])
                ]
            ])
            ->add('region', EntityType::class, [
                'class' => Region::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_label' => 'name',
                'label' => 'Región *',
                'required' => true,
                'placeholder' => 'Seleccionar región',
                'attr' => [
                    'class' => 'form-select',
                    'data-region-select' => 'true',
                ],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Region', 'r')
                        ->where('r.status = :status')
                        ->setParameter('status', Status::ACTIVE)
                        ->orderBy('r.name', 'ASC');
                },
            ])
            ->add('description', TextType::class, [
                'label' => 'Descripción',
                'required' => false,
                'attr' => [
                    'maxlength' => 500,
                    'placeholder' => 'Máximo 500 caracteres'
                ],
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'La descripción no puede tener más de {{ limit }} caracteres.'
                    ])
                ]
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => true,
            ])
            ->add('platform', TextType::class, [
                'label' => 'Plataforma',
                'required' => true,
                'attr' => [
                    'maxlength' => 50,
                    'placeholder' => 'Máximo 50 caracteres'
                ],
                'constraints' => [
                    new Length([
                        'max' => 50,
                        'maxMessage' => 'La plataforma no puede tener más de {{ limit }} caracteres.'
                    ])
                ]
            ])
            ->add('image', FileType::class, [
                'label' => 'Imagen destacada',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, WEBP)',
                    ])
                ],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
                'required' => true,
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Fecha de fin',
                'widget' => 'single_text',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SocialMedia::class,
        ]);
    }
}
