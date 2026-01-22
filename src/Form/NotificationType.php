<?php

namespace App\Form;

use App\Entity\App\Company;
use App\Entity\App\Notification;
use App\Entity\App\Region;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NotificationType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Tenant is already set by the controller before form creation
        // No need to set it again here as it may cause EM reset

        // Obtener el EntityManager específico del tenant ACTUAL
        $entityManager = $this->tenantManager->getEntityManager();

        $builder
            ->add('title', null, [
                'label' => 'Asunto *',
                'row_attr' => ['class' => 'col-md-12 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Mensaje *',
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'rows' => 4,
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('regions', EntityType::class, [
                'class' => Region::class,
                'em' => $entityManager,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Regiones (opcional)',
                'required' => false,
                'placeholder' => 'Seleccionar regiones (opcional)',
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-select select2-modal',
                    'data-placeholder' => 'Seleccionar regiones',
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) use ($entityManager) {
                    return $entityManager->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Region', 'r')
                        ->where('r.status = :status')
                        ->setParameter('status', Status::ACTIVE)
                        ->orderBy('r.name', 'ASC');
                },
            ])
            ->add('companies', EntityType::class, [
                'class' => Company::class,
                'em' => $entityManager,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Empresas',
                'required' => false,
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'attr' => [
                    'class' => 'form-select select2-modal',
                    'data-placeholder' => 'Seleccionar empresas',
                ],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) use ($entityManager) {
                    return $entityManager->createQueryBuilder()
                        ->select('c')
                        ->from('App\Entity\App\Company', 'c')
                        ->where('c.status = :status')
                        ->setParameter('status', Status::ACTIVE)
                        ->orderBy('c.name', 'ASC');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notification::class,
        ]);

        $resolver->setDefined(['dominio']);
        $resolver->setAllowedTypes('dominio', ['string', 'null']);
    }
}
