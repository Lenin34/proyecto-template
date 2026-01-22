<?php

namespace App\Command;

use App\Entity\App\FormEntry;
use App\Entity\App\FormEntryValue;
use App\Entity\App\FormTemplate;
use App\Entity\App\User;
use App\Enum\Status;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-form-submission',
    description: 'Prueba el envío de formularios para verificar que funciona correctamente'
)]
class TestFormSubmissionCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private TenantManager $tenantManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        TenantManager $tenantManager
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->tenantManager = $tenantManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', 't', InputOption::VALUE_REQUIRED, 'Tenant específico')
            ->addOption('form-id', 'f', InputOption::VALUE_REQUIRED, 'ID del formulario a probar')
            ->addOption('user-email', 'u', InputOption::VALUE_REQUIRED, 'Email del usuario para la prueba')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo simular sin guardar')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $tenant = $input->getOption('tenant');
        $formId = $input->getOption('form-id');
        $userEmail = $input->getOption('user-email');
        $dryRun = $input->getOption('dry-run');

        if (!$tenant || !$formId || !$userEmail) {
            $io->error('Los parámetros --tenant, --form-id y --user-email son obligatorios');
            return Command::FAILURE;
        }

        try {
            $this->tenantManager->setCurrentTenant($tenant);
            $em = $this->tenantManager->getEntityManager();
            $io->info("Configurado tenant: $tenant");
        } catch (\Exception $e) {
            $io->error("Error al configurar tenant: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Prueba de Envío de Formulario');

        if ($dryRun) {
            $io->note('Modo DRY-RUN: No se guardarán cambios');
        }

        // Buscar formulario
        $formTemplate = $em->getRepository(FormTemplate::class)->find($formId);
        if (!$formTemplate) {
            $io->error("Formulario con ID $formId no encontrado");
            return Command::FAILURE;
        }

        $io->info("Formulario encontrado: {$formTemplate->getName()}");

        // Buscar usuario
        $user = $em->getRepository(User::class)->findOneBy(['email' => $userEmail]);
        if (!$user) {
            $io->error("Usuario con email $userEmail no encontrado");
            return Command::FAILURE;
        }

        $io->info("Usuario encontrado: {$user->getEmail()}");

        // Verificar campos del formulario
        $fields = $formTemplate->getFormTemplateFields();
        $activeFields = [];
        
        foreach ($fields as $field) {
            if ($field->getStatus() === Status::ACTIVE) {
                $activeFields[] = $field;
            }
        }

        if (empty($activeFields)) {
            $io->warning('El formulario no tiene campos activos');
            return Command::SUCCESS;
        }

        $io->section('Campos del Formulario');
        $fieldData = [];
        foreach ($activeFields as $field) {
            $fieldData[] = [
                $field->getId(),
                $field->getName(),
                $field->getType(),
                $field->isRequired() ? 'Sí' : 'No'
            ];
        }
        $io->table(['ID', 'Nombre', 'Tipo', 'Requerido'], $fieldData);

        // Verificar si ya existe una submisión
        $existingEntry = $em->getRepository(FormEntry::class)
            ->findOneBy([
                'formTemplate' => $formTemplate,
                'user' => $user,
                'status' => Status::ACTIVE
            ]);

        if ($existingEntry) {
            $io->warning("El usuario ya tiene una submisión para este formulario (ID: {$existingEntry->getId()})");
            
            if (!$io->confirm('¿Desea continuar de todos modos? (creará una submisión duplicada)')) {
                return Command::SUCCESS;
            }
        }

        // Simular datos de prueba
        $testData = $this->generateTestData($activeFields);
        
        $io->section('Datos de Prueba Generados');
        foreach ($testData as $fieldId => $value) {
            $field = $this->findFieldById($activeFields, $fieldId);
            $io->text("Campo {$field->getName()} (ID: $fieldId): " . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : ''));
        }

        if (!$dryRun) {
            $result = $this->submitForm($em, $formTemplate, $user, $testData);
            
            if ($result['success']) {
                $io->success("Formulario enviado exitosamente. ID de entrada: {$result['entry_id']}");
            } else {
                $io->error("Error al enviar formulario: {$result['error']}");
                return Command::FAILURE;
            }
        } else {
            $io->info('Simulación completada. El formulario se enviaría correctamente.');
        }

        return Command::SUCCESS;
    }

    private function generateTestData(array $fields): array
    {
        $testData = [];
        
        foreach ($fields as $field) {
            $value = match($field->getType()) {
                'text' => 'Texto de prueba para ' . $field->getName(),
                'textarea' => "Este es un texto largo de prueba para el campo {$field->getName()}.\nIncluye múltiples líneas\ny caracteres especiales: áéíóú ñ",
                'email' => 'test@example.com',
                'number' => '123',
                'date' => '2024-01-15',
                'select' => $this->getFirstOption($field->getOptions()),
                'radio' => $this->getFirstOption($field->getOptions()),
                'checkbox' => $field->isMultiple() ? [$this->getFirstOption($field->getOptions())] : $this->getFirstOption($field->getOptions()),
                default => 'Valor de prueba'
            };
            
            $testData[$field->getId()] = $value;
        }
        
        return $testData;
    }

    private function getFirstOption(?string $options): string
    {
        if (empty($options)) {
            return 'Opción de prueba';
        }
        
        $optionsList = array_filter(array_map('trim', explode(',', $options)));
        return $optionsList[0] ?? 'Opción de prueba';
    }

    private function findFieldById(array $fields, int $fieldId): ?object
    {
        foreach ($fields as $field) {
            if ($field->getId() === $fieldId) {
                return $field;
            }
        }
        return null;
    }

    private function submitForm(EntityManagerInterface $em, $formTemplate, $user, array $testData): array
    {
        try {
            $em->beginTransaction();

            // Asegurar que las entidades estén en el contexto correcto
            $formTemplate = $em->find(FormTemplate::class, $formTemplate->getId());
            $user = $em->find(User::class, $user->getId());

            // Crear entrada del formulario
            $formEntry = new FormEntry();
            $formEntry->setFormTemplate($formTemplate);
            $formEntry->setUser($user);
            $formEntry->setCreatedAt(new \DateTime());
            $formEntry->setUpdatedAt(new \DateTime());
            $formEntry->setStatus(Status::ACTIVE);

            $em->persist($formEntry);
            $em->flush(); // Para obtener el ID

            // Crear valores de los campos
            foreach ($testData as $fieldId => $value) {
                $field = $em->getRepository(\App\Entity\App\FormTemplateField::class)->find($fieldId);

                if (!$field) {
                    continue;
                }

                // Asegurar que el campo esté en el contexto correcto
                $field = $em->find(\App\Entity\App\FormTemplateField::class, $field->getId());

                $formEntryValue = new FormEntryValue();
                $formEntryValue->setFormEntry($formEntry);
                $formEntryValue->setFormTemplateField($field);
                $formEntryValue->setValue(is_array($value) ? json_encode($value) : (string)$value);
                $formEntryValue->setStatus(Status::ACTIVE);

                $em->persist($formEntryValue);
            }

            $em->flush();
            $em->commit();

            return [
                'success' => true,
                'entry_id' => $formEntry->getId()
            ];

        } catch (\Exception $e) {
            $em->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
