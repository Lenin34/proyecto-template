<?php

namespace App\Form;

use App\Entity\App\Company;
use App\Entity\App\FormTemplate;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormTemplateType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Asegurar que el tenant este configurado
        if (isset($options['dominio'])) {
            $this->tenantManager->setCurrentTenant($options['dominio']);
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del Formulario *',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ej. Encuesta de Satisfaccion',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripcion',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Describe el proposito de este formulario...',
                ],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('companies', EntityType::class, [
                'class' => Company::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_value' => 'id',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Empresas Autorizadas',
                'attr' => [
                    'class' => 'form-select select2-companies',
                    'data-placeholder' => 'Seleccionar empresas (vacio = todas)',
                ],
                'label_attr' => ['class' => 'form-label'],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('c')
                        ->from('App\Entity\App\Company', 'c')
                        ->where('c.status = :status')
                        ->setParameter('status', Status::ACTIVE)
                        ->orderBy('c.name', 'ASC');
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormTemplate::class,
        ]);

        $resolver->setDefined(['dominio']);
        $resolver->setAllowedTypes('dominio', ['string', 'null']);
    }
}

