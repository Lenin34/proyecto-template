<?php

namespace App\Form;

use App\Entity\App\Company;
use App\Entity\App\Region;
use App\Entity\App\Role;
use App\Entity\App\User;
use App\Enum\Status;
use App\Repository\RoleRepository;
use App\Service\TenantManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Tenant is already set by the controller
        $builder
            ->add('photo', FileType::class, [
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
            ->add('name', TextType::class, [
                'label' => 'Nombre *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Apellidos *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('phone_number', TextType::class, [
                'label' => 'Teléfono *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('birthday', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Fecha de Nacimiento *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])    
            ->add('email', TextType::class, [
                'label' => 'Correo Electrónico *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                ])
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Contraseña',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'help' => 'Dejar en blanco para mantener la contraseña actual',
            ])

            ->add('curp', TextType::class, [
                'label' => 'CURP *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('employee_number', TextType::class, [
                'label' => 'N° de Empleado',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Género',
                'placeholder' => 'Selecciona un genero',
                'required' => false,
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
                'label' => 'Nivel de Educación',
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
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'name',
                'placeholder' => 'Selecciona un rol',
                'label' => 'Rol *',
                'required' => false, // Opcional - se asigna ROLE_USER automáticamente si no se envía
                'em' => $this->tenantManager->getEntityManager(),
                'query_builder' => function (\Doctrine\ORM\EntityRepository $repo) {
                    // Asegurar que usamos el mismo EntityManager del TenantManager
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Role', 'r')
                        ->where('r.name NOT IN (:excludedRoles)')
                        ->setParameter('excludedRoles', ['ROLE_ADMIN', 'ROLE_LIDER']);
                },
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('regions', EntityType::class, [
                'class' => Region::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Regiones *',
                'required' => false, // Opcional - no se usa en el modal de nuevo usuario
                'em' => $this->tenantManager->getEntityManager(),
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg form-check-label'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'choice_attr' => function ($choice, $key, $value) {
                    return ['class' => 'form-check-input'];
                },
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'allow_extra_fields' => true, // Permitir campos extra como 'company' que se procesan manualmente
        ]);

        // Definir la opción dominio como opcional
        $resolver->setDefined(['dominio']);
        $resolver->setAllowedTypes('dominio', ['string', 'null']);
    }
}
