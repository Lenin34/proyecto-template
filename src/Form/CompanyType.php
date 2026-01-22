<?php

namespace App\Form;

use App\Entity\App\Company;
use App\Entity\App\Region;
use App\Enum\Status;
use App\Service\TenantManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nombre de la empresa *',
                'row_attr' => ['class' => 'col-md-12 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('region', EntityType::class, [
                'label' => 'Región *',
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'row_attr' => ['class' => 'col-md-12 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'class' => Region::class,
                'choice_label' => 'name',
                'placeholder' => 'Seleccione una región',
                'required' => false,
                'em' => $this->tenantManager->getEntityManager(),
                'query_builder' => function (EntityRepository $er) {
                    // Asegurar que usamos el mismo EntityManager del TenantManager
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Region', 'r')
                        ->where('r.status = :status')
                        ->setParameter('status', \App\Enum\Status::ACTIVE)
                        ->orderBy('r.name', 'ASC');
                },
            ])
            /*->add('status')
            ->add('updated_at', null, [
                'widget' => 'single_text',
            ])
            ->add('created_at', null, [
                'widget' => 'single_text',
            ])*/
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
