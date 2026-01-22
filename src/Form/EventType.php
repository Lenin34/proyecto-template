<?php

namespace App\Form;

use App\Entity\App\Event;
use App\Entity\App\Region;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Nombre del evento *',
                'row_attr' => ['class' => 'col-md-12 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('region', EntityType::class, [
                'class' => Region::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_label' => 'name',
                'label' => 'Región (opcional)',
                'required' => false,
                'placeholder' => 'Seleccionar región (opcional)',
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-select',
                    'data-region-select' => 'true',
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
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
            ->add('start_date', null, [
                'widget' => 'single_text',
                'label' => 'Día de inicio *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('end_date', null, [
                'widget' => 'single_text',
                'label' => 'Día de fin *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripción *',
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'rows' => 4,
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('image', FileType::class, [
                'label' => 'Añadir imagen *',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'accept' => 'image/*',
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
            ])
            /*->add('created_at', null, [
                'widget' => 'single_text',
            ])
            ->add('updated_at', null, [
                'widget' => 'single_text',
            ])
            ->add('status')*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
