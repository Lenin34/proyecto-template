<?php

namespace App\Command;

use App\Entity\App\FormTemplateField;
use App\Service\FormFieldTypeResolver;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-form-field-types',
    description: 'Diagnostica y corrige inconsistencias en tipos de campos de formularios'
)]
class FixFormFieldTypesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private FormFieldTypeResolver $typeResolver;
    private TenantManager $tenantManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        FormFieldTypeResolver $typeResolver,
        TenantManager $tenantManager
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->typeResolver = $typeResolver;
        $this->tenantManager = $tenantManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', 't', InputOption::VALUE_REQUIRED, 'Tenant específico a analizar')
            ->addOption('form-id', 'f', InputOption::VALUE_OPTIONAL, 'ID de formulario específico')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Aplicar correcciones automáticamente')
            ->addOption('report', 'r', InputOption::VALUE_NONE, 'Generar reporte detallado')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $tenant = $input->getOption('tenant');
        $formId = $input->getOption('form-id');
        $shouldFix = $input->getOption('fix');
        $generateReport = $input->getOption('report');

        if (!$tenant) {
            $io->error('El parámetro --tenant es obligatorio');
            return Command::FAILURE;
        }

        try {
            $this->tenantManager->setCurrentTenant($tenant);
            $io->info("Analizando tenant: $tenant");
        } catch (\Exception $e) {
            $io->error("Error al configurar tenant: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title('Diagnóstico de Tipos de Campos de Formularios');

        // Obtener campos a analizar
        $fields = $this->getFieldsToAnalyze($formId);
        
        if (empty($fields)) {
            $io->warning('No se encontraron campos para analizar');
            return Command::SUCCESS;
        }

        $io->info("Analizando " . count($fields) . " campos...");

        // Analizar campos
        $analysis = $this->typeResolver->analyzeFormFields($fields);
        
        // Mostrar resumen
        $this->displaySummary($io, $analysis);

        // Mostrar campos inconsistentes
        if (!empty($analysis['inconsistent_fields'])) {
            $this->displayInconsistentFields($io, $analysis['inconsistent_fields']);

            if ($shouldFix) {
                $this->fixInconsistentFields($io, $analysis['inconsistent_fields']);
            } else {
                $io->note('Use --fix para aplicar correcciones automáticamente');
            }
        }

        // Generar reporte si se solicita
        if ($generateReport) {
            $this->generateDetailedReport($io, $fields);
        }

        return Command::SUCCESS;
    }

    private function getFieldsToAnalyze(?string $formId): array
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

    private function displaySummary(SymfonyStyle $io, array $analysis): void
    {
        $io->section('Resumen del Análisis');
        
        $io->table(
            ['Métrica', 'Valor'],
            [
                ['Total de campos', $analysis['total_fields']],
                ['Campos inconsistentes', count($analysis['inconsistent_fields'])],
                ['Tipos únicos encontrados', count($analysis['field_types'])]
            ]
        );

        if (!empty($analysis['field_types'])) {
            $io->section('Distribución de Tipos');
            $typeData = [];
            foreach ($analysis['field_types'] as $type => $count) {
                $typeData[] = [$type, $count];
            }
            $io->table(['Tipo', 'Cantidad'], $typeData);
        }

        if (!empty($analysis['issues_summary'])) {
            $io->section('Problemas Más Comunes');
            $issueData = [];
            arsort($analysis['issues_summary']);
            foreach ($analysis['issues_summary'] as $issue => $count) {
                $issueData[] = [$issue, $count];
            }
            $io->table(['Problema', 'Frecuencia'], $issueData);
        }
    }

    private function displayInconsistentFields(SymfonyStyle $io, array $inconsistentFields): void
    {
        $io->section('Campos con Inconsistencias');

        foreach ($inconsistentFields as $field) {
            $io->text("<fg=yellow>Campo:</> {$field['field_name']} (ID: {$field['field_id']})");
            $io->text("  <fg=red>Tipo actual:</> {$field['current_type']}");
            $io->text("  <fg=green>Tipo sugerido:</> {$field['resolved_type']}");
            
            if (!empty($field['issues'])) {
                $io->text("  <fg=red>Problemas:</>");
                foreach ($field['issues'] as $issue) {
                    $io->text("    - $issue");
                }
            }
            
            if (!empty($field['suggestions'])) {
                $io->text("  <fg=cyan>Sugerencias:</>");
                foreach ($field['suggestions'] as $suggestion) {
                    $io->text("    - $suggestion");
                }
            }
            
            $io->newLine();
        }
    }

    private function fixInconsistentFields(SymfonyStyle $io, array $inconsistentFields): void
    {
        $io->section('Aplicando Correcciones');

        $correctedCount = 0;
        $errorCount = 0;

        foreach ($inconsistentFields as $fieldData) {
            $field = $this->entityManager->getRepository(FormTemplateField::class)
                ->find($fieldData['field_id']);

            if (!$field) {
                $io->error("Campo con ID {$fieldData['field_id']} no encontrado");
                $errorCount++;
                continue;
            }

            try {
                $wasCorrected = $this->typeResolver->autoCorrectField($field);
                
                if ($wasCorrected) {
                    $this->entityManager->persist($field);
                    $correctedCount++;
                    $io->text("<fg=green>✓</> Corregido: {$field->getName()} ({$fieldData['current_type']} → {$fieldData['resolved_type']})");
                }
            } catch (\Exception $e) {
                $io->error("Error al corregir campo {$field->getName()}: " . $e->getMessage());
                $errorCount++;
            }
        }

        if ($correctedCount > 0) {
            try {
                $this->entityManager->flush();
                $io->success("Se corrigieron $correctedCount campos exitosamente");
            } catch (\Exception $e) {
                $io->error("Error al guardar cambios: " . $e->getMessage());
                return;
            }
        }

        if ($errorCount > 0) {
            $io->warning("Se encontraron $errorCount errores durante la corrección");
        }

        if ($correctedCount === 0 && $errorCount === 0) {
            $io->info('No se requirieron correcciones');
        }
    }

    private function generateDetailedReport(SymfonyStyle $io, array $fields): void
    {
        $io->section('Generando Reporte Detallado');

        $report = $this->typeResolver->generateInconsistencyReport($fields);
        
        $filename = 'form_fields_report_' . date('Y-m-d_H-i-s') . '.txt';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        if (file_put_contents($filepath, $report)) {
            $io->success("Reporte generado: $filepath");
            
            // Mostrar preview del reporte
            $io->section('Preview del Reporte');
            $lines = explode("\n", $report);
            foreach (array_slice($lines, 0, 20) as $line) {
                $io->text($line);
            }
            
            if (count($lines) > 20) {
                $io->text('... (ver archivo completo para más detalles)');
            }
        } else {
            $io->error("No se pudo generar el reporte en: $filepath");
        }
    }
}
