<?php

namespace App\Form;

use App\Entity\Master\Tenant;
use App\Enum\Features;
use App\Enum\Status;
use App\Service\TenantManager;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TenantType extends AbstractType
{

    public function __construct(
        private readonly TenantManager $tenantManager,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dominio', TextType::class, [
                'label' => 'Dominio',
                'required' => true,
                'attr' => [
                    'class' => 'input-glass',
                ],
                'label_attr' => ['for' => 'dominio'],
                'constraints' => [
                    new Callback([$this, 'validateUniqueDomain']),
                ],
            ])
            ->add('databaseName', TextType::class, [
                'label' => 'Database',
                'required' => true,
                'attr' => [
                    'class' => 'input-glass',
                ],
                'label_attr' => ['for' => 'databaseName'],
            ])
            ->add('aviso', FileType::class, [
                'label' => 'Archivo de aviso de privacidad',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'file-input', 'id' => 'aviso'],
                'label_attr' => ['for' => 'aviso'],
            ])
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'file-input', 'id' => 'logo'],
                'label_attr' => ['for' => 'logo'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tenant::class,
            'currentTenant' => null,
        ]);

        $resolver->setAllowedTypes('currentTenant', ['null', Tenant::class]);
        $resolver->setDefined(['dominio']);
    }

    public function validateUniqueDomain($domain, ExecutionContextInterface $context): void
    {
        if (empty($domain)) {
            return;
        }

        $this->tenantManager->setCurrentTenant('Master');
        $em = $this->tenantManager->getEntityManager();

        $existingTenant = $em->getRepository(Tenant::class)->findOneBy([
            'dominio' => $domain,
            'status' => Status::ACTIVE,
        ]);

        /** @var Tenant|null $currentTenant */
        $currentTenant = $context->getRoot()->getData(); // obtiene el objeto actual del formulario

        if ($existingTenant && $currentTenant && $existingTenant->getId() !== $currentTenant->getId()) {
            $context->buildViolation('Este dominio ya está registrado.')
                ->addViolation();
        }
    }


}
