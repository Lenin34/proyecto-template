<?php

namespace App\Command;

use App\Entity\App\FormTemplateField;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-form-field-properties',
    description: 'Limpia propiedades inconsistentes en campos de formularios'
)]
class CleanFormFieldPropertiesCommand extends Command
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
            ->addOption('tenant', 't', InputOption::VALUE_REQUIRED, 'Tenant específico a limpiar')
            ->addOption('form-id', 'f', InputOption::VALUE_OPTIONAL, 'ID de formulario específico')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo mostrar qué se limpiaría sin aplicar cambios')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $tenant = $input->getOption('tenant');
        $formId = $input->getOption('form-id');
        $dryRun = $input->getOption('dry-run');

        if (!$tenant) {
            $io->error('El parámetro --tenant es obligatorio');
            return Command::FAILURE;
        }

        try {
            $this->tenantManager->setCurrentTenant($tenant);
            $io->info("Limpiando propiedades en tenant: $tenant");
        } catch (\Exception $e) {
            $io->error("Error al configurar tenant: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Limpieza de Propiedades de Campos de Formularios');

        if ($dryRun) {
            $io->note('Modo DRY-RUN: No se aplicarán cambios');
        }

        // Obtener campos a limpiar
        $fields = $this->getFieldsToClean($formId);
        
        if (empty($fields)) {
            $io->success('No se encontraron campos que requieran limpieza');
            return Command::SUCCESS;
        }

        $io->info("Analizando " . count($fields) . " campos...");

        $cleaningResults = $this->analyzeAndCleanFields($fields, $dryRun);
        
        $this->displayResults($io, $cleaningResults, $dryRun);

        if (!$dryRun && $cleaningResults['cleaned_count'] > 0) {
            try {
                $this->entityManager->flush();
                $io->success("Se limpiaron {$cleaningResults['cleaned_count']} campos exitosamente");
            } catch (\Exception $e) {
                $io->error("Error al guardar cambios: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function getFieldsToClean(?string $formId): array
    {
        $repository = $this->entityManager->getRepository(FormTemplateField::class);
        
        if ($formId) {
            return $repository->createQueryBuilder('f')
                ->where('f.formTemplate = :formId')
                ->setParameter('formId', $formId)
                ->getQuery()
                ->getResult();
        }

        return $repository->findAll();
    }

    private function analyzeAndCleanFields(array $fields, bool $dryRun): array
    {
        $results = [
            'total_fields' => count($fields),
            'cleaned_count' => 0,
            'cleaning_actions' => []
        ];

        foreach ($fields as $field) {
            if (!$field instanceof FormTemplateField) {
                continue;
            }

            $actions = $this->getCleaningActions($field);
            
            if (!empty($actions)) {
                $results['cleaning_actions'][] = [
                    'field' => $field,
                    'actions' => $actions
                ];

                if (!$dryRun) {
                    $this->applyCleaningActions($field, $actions);
                    $this->entityManager->persist($field);
                }

                $results['cleaned_count']++;
            }
        }

        return $results;
    }

    private function getCleaningActions(FormTemplateField $field): array
    {
        $actions = [];
        $type = $field->getType();

        // Limpiar textarea_cols en campos que no son textarea
        if ($type !== 'textarea' && !empty($field->getTextareaCols())) {
            $actions[] = [
                'property' => 'textarea_cols',
                'current_value' => $field->getTextareaCols(),
                'new_value' => null,
                'reason' => 'Campo no es textarea'
            ];
        }

        // Limpiar options en campos que no las usan
        if (!in_array($type, ['select', 'radio', 'checkbox']) && !empty($field->getOptions())) {
            $actions[] = [
                'property' => 'options',
                'current_value' => substr($field->getOptions(), 0, 50) . '...',
                'new_value' => null,
                'reason' => 'Campo no usa opciones'
            ];
        }

        // Limpiar multiple en campos que no lo soportan
        if (!in_array($type, ['select', 'checkbox', 'file']) && $field->isMultiple()) {
            $actions[] = [
                'property' => 'multiple',
                'current_value' => 'true',
                'new_value' => 'false',
                'reason' => 'Campo no soporta múltiples valores'
            ];
        }

        // Agregar textarea_cols a campos textarea que no lo tienen
        if ($type === 'textarea' && (empty($field->getTextareaCols()) || $field->getTextareaCols() <= 0)) {
            $actions[] = [
                'property' => 'textarea_cols',
                'current_value' => $field->getTextareaCols() ?: 'null',
                'new_value' => '3',
                'reason' => 'Textarea necesita configuración de filas'
            ];
        }

        return $actions;
    }

    private function applyCleaningActions(FormTemplateField $field, array $actions): void
    {
        foreach ($actions as $action) {
            switch ($action['property']) {
                case 'textarea_cols':
                    $field->setTextareaCols($action['new_value']);
                    break;
                case 'options':
                    $field->setOptions($action['new_value']);
                    break;
                case 'multiple':
                    $field->setMultiple($action['new_value'] === 'true');
                    break;
            }
        }
    }

    private function displayResults(SymfonyStyle $io, array $results, bool $dryRun): void
    {
        $io->section('Resultados de la Limpieza');

        $io->table(
            ['Métrica', 'Valor'],
            [
                ['Total de campos analizados', $results['total_fields']],
                ['Campos que requieren limpieza', count($results['cleaning_actions'])],
                ['Acciones de limpieza', $results['cleaned_count']]
            ]
        );

        if (!empty($results['cleaning_actions'])) {
            $io->section($dryRun ? 'Cambios Propuestos' : 'Cambios Aplicados');

            foreach ($results['cleaning_actions'] as $fieldAction) {
                $field = $fieldAction['field'];
                $actions = $fieldAction['actions'];

                $io->text("<fg=yellow>Campo:</> {$field->getName()} (ID: {$field->getId()}, Tipo: {$field->getType()})");

                foreach ($actions as $action) {
                    $currentValue = $action['current_value'] ?: 'null';
                    $newValue = $action['new_value'] ?: 'null';
                    
                    $io->text("  <fg=cyan>{$action['property']}:</> $currentValue → $newValue");
                    $io->text("    <fg=gray>Razón:</> {$action['reason']}");
                }

                $io->newLine();
            }
        } else {
            $io->info('No se encontraron propiedades que requieran limpieza');
        }
    }
}
