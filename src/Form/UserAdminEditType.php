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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UserAdminEditType extends AbstractType
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Asegurar que el tenant esté configurado
        if (isset($options['dominio'])) {
            $this->tenantManager->setCurrentTenant($options['dominio']);
        }

        $builder
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
            ])/*
            ->add('curp', TextType::class, [
                'label' => 'CURP *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
            ->add('phone_number', TextType::class, [
                'label' => 'TELÉFONO *',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('email', TextType::class, [
                'label' => 'CORREO ELECTRÓNICO *',
                'constraints' => [
                    new Email(['message' => 'El correo electrónico no es válido.']),
                    new Callback([$this, 'validateUniqueEmail'])
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('role', EntityType::class, [
                'class' => Role::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_value' => 'id',
                'choice_label' => 'name',
                'placeholder' => 'Selecciona un rol',
                'label' => 'ROL *',
                'query_builder' => function (RoleRepository $repo) {
                    // Asegurar que usamos el mismo EntityManager del TenantManager
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('r')
                        ->from('App\Entity\App\Role', 'r')
                        ->where('r.name != :excludedRole')
                        ->setParameter('excludedRole', 'ROLE_USER');
                },
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'CONTRASEÑA',
                'required' => false,
                'mapped' => false,
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'placeholder' => 'Dejar vacío para mantener la actual'
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'help' => 'Dejar vacío para mantener la contraseña actual',
                'help_attr' => ['class' => 'form-text text-muted small'],
            ])
            ->add('regions', EntityType::class, [
                'class' => Region::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_value' => 'id',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false, // Cambiado a false para usar Select2
                'label' => 'REGIONES',
                'required' => false,
                'row_attr' => ['class' => 'col-md-12 margin-form-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'attr' => [
                    'class' => 'form-select select2-regions',
                    'data-placeholder' => 'Selecciona una o más regiones',
                ],
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
            /*->add('employee_number', TextType::class, [
                'label' => 'NÚMERO DE EMPLEADO',
                'required' => false,
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
            ->add('company', EntityType::class, [
                'class' => Company::class,
                'em' => $this->tenantManager->getEntityManager(),
                'choice_label' => 'name',
                'placeholder' => 'Selecciona una empresa',
                'label' => 'EMPRESA',
                'required' => false,
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg select2-company',
                    'data-placeholder' => 'Selecciona una empresa'
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    $em = $this->tenantManager->getEntityManager();
                    return $em->createQueryBuilder()
                        ->select('c')
                        ->from('App\Entity\App\Company', 'c')
                        ->where('c.status = :status')
                        ->setParameter('status', \App\Enum\Status::ACTIVE)
                        ->orderBy('c.name', 'ASC');
                },
            ])
            /*->add('birthday', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'label' => 'FECHA DE NACIMIENTO',
                'required' => false,
                'widget' => 'single_text',
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
/*            ->add('gender', ChoiceType::class, [
                'label' => 'GÉNERO',
                'placeholder' => 'Selecciona un género',
                'choices' => [
                    'Femenino' => 'Femenino',
                    'Masculino' => 'Masculino',
                    'Otro' => 'Otro',
                ],
                'required' => false,
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
/*            ->add('education', ChoiceType::class, [
                'label' => 'NIVEL DE EDUCACIÓN',
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
                'required' => false,
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => ['class' => 'form-control form-inpunt-sntiasg'],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
            ])*/
            ->add('photo', FileType::class, [
                'label' => 'FOTO DE PERFIL',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Por favor sube una imagen válida (JPEG, PNG, GIF)',
                        'maxSizeMessage' => 'El archivo es demasiado grande ({{ size }} {{ suffix }}). El tamaño máximo permitido es {{ limit }} {{ suffix }}.',
                    ])
                ],
                'row_attr' => ['class' => 'col-md-6 col-movil margin-form-sntiasg'],
                'attr' => [
                    'class' => 'form-control form-inpunt-sntiasg',
                    'accept' => 'image/*'
                ],
                'label_attr' => ['class' => 'modal-text-sntiasg'],
                'help' => 'Formatos permitidos: JPEG, PNG, GIF. Tamaño máximo: 5MB',
                'help_attr' => ['class' => 'form-text text-muted small'],
            ])
        ;
    }

    public function validateUniqueEmail($email, ExecutionContextInterface $context): void
    {
        if (empty($email)) {
            return; // No validar si el email está vacío
        }

        $form = $context->getRoot();
        $user = $form->getData();

        // CRÍTICO: Si es una edición y el email NO ha cambiado, no validar
        if ($user && $user->getId()) {
            // Obtener el email original del usuario desde la base de datos
            $em = $this->tenantManager->getEntityManager();
            $originalUser = $em->find(User::class, $user->getId());

            if ($originalUser && $originalUser->getEmail() === $email) {
                // El email no ha cambiado, no hay necesidad de validar
                return;
            }
        }

        // Verificar si el email ya existe en otro usuario
        $em = $this->tenantManager->getEntityManager();
        $existingUser = $em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            // Si es una edición y el email pertenece al mismo usuario, está bien
            if ($user && $user->getId() && $existingUser->getId() === $user->getId()) {
                return; // El email pertenece al mismo usuario, no hay problema
            }

            // El email ya existe en otro usuario diferente
            $context->buildViolation('Este correo ya está registrado.')
                ->addViolation();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class
        ]);

        $resolver->setDefined(['dominio']);
        $resolver->setAllowedTypes('dominio', ['string', 'null']);
    }
}
