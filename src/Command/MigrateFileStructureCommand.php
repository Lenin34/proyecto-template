<?php

namespace App\Command;

use App\Service\FileUploadService;
use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-file-structure',
    description: 'Migra archivos de la estructura antigua a la nueva: uploads/{userId}/{formTemplateId}/',
)]
class MigrateFileStructureCommand extends Command
{
    private TenantManager $tenantManager;
    private FileUploadService $fileUploadService;

    public function __construct(TenantManager $tenantManager, FileUploadService $fileUploadService)
    {
        $this->tenantManager = $tenantManager;
        $this->fileUploadService = $fileUploadService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', 't', InputOption::VALUE_REQUIRED, 'Tenant to migrate (ts or SNT)', 'ts')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without actually doing it')
            ->addOption('fix-android-uris', null, InputOption::VALUE_NONE, 'Fix Android content:// URIs in database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getOption('tenant');
        $dryRun = $input->getOption('dry-run');
        $fixAndroidUris = $input->getOption('fix-android-uris');

        try {
            $io->title('Migración de Estructura de Archivos');
            
            // Set tenant
            $this->tenantManager->setCurrentTenant($tenant);
            $io->success("Tenant configurado: {$tenant}");
            
            // Get entity manager
            $em = $this->tenantManager->getEntityManager();
            
            if ($fixAndroidUris) {
                $io->section('🔧 Reparando Android content:// URIs');
                $this->fixAndroidContentUris($em, $io, $dryRun);
            }
            
            $io->section('📁 Migrando estructura de archivos');
            
            // Buscar todos los FormEntryValue que contienen archivos
            $fileEntryValues = $em->createQuery(
                'SELECT fev, fe, ft FROM App\Entity\FormEntryValue fev 
                 JOIN fev.formEntry fe 
                 JOIN fe.formTemplate ft 
                 JOIN fev.formTemplateField ftf 
                 WHERE ftf.type = :fileType 
                 AND fev.status = :status'
            )
            ->setParameter('fileType', 'file')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->getResult();

            $io->info(sprintf('Encontrados %d valores de archivo para migrar', count($fileEntryValues)));

            $migrated = 0;
            $errors = 0;
            $skipped = 0;

            foreach ($fileEntryValues as $entryValue) {
                $value = $entryValue->getValue();
                $formEntry = $entryValue->getFormEntry();
                $userId = $formEntry->getUser()->getId();
                $formTemplateId = $formEntry->getFormTemplate()->getId();

                $io->text("Procesando valor: {$value}");

                // Verificar si es un content:// URI de Android
                if (strpos($value, 'content://') === 0) {
                    $io->warning("⚠️ Android content URI detectado: {$value}");
                    $skipped++;
                    continue;
                }

                // Intentar decodificar como JSON
                $decoded = json_decode($value, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['file_path'])) {
                    // Ya es formato nuevo
                    $filePath = $decoded['file_path'];
                    
                    // Verificar si ya está en la nueva estructura
                    if (preg_match('/^\/\d+\/\d+\//', $filePath)) {
                        $io->text("✅ Ya en nueva estructura: {$filePath}");
                        $skipped++;
                        continue;
                    }
                    
                    // Migrar archivo
                    if (!$dryRun) {
                        $newPath = $this->fileUploadService->migrateFile($filePath, $userId, $formTemplateId);
                        
                        if ($newPath) {
                            // Actualizar información del archivo
                            $decoded['file_path'] = $newPath;
                            $decoded['user_id'] = $userId;
                            $decoded['form_template_id'] = $formTemplateId;
                            $decoded['migrated_at'] = date('Y-m-d H:i:s');
                            
                            $entryValue->setValue(json_encode($decoded));
                            $em->persist($entryValue);
                            
                            $io->text("✅ Migrado: {$filePath} -> {$newPath}");
                            $migrated++;
                        } else {
                            $io->error("❌ Error migrando: {$filePath}");
                            $errors++;
                        }
                    } else {
                        $io->text("🔄 [DRY-RUN] Migraría: {$filePath} -> /{$userId}/{$formTemplateId}/");
                        $migrated++;
                    }
                    
                } elseif (strpos($value, '/uploads/forms/') === 0) {
                    // Formato antiguo simple (solo ruta)
                    if (!$dryRun) {
                        $newPath = $this->fileUploadService->migrateFile($value, $userId, $formTemplateId);
                        
                        if ($newPath) {
                            // Crear nuevo formato JSON
                            $fileInfo = [
                                'file_path' => $newPath,
                                'original_name' => basename($value),
                                'file_name' => basename($value),
                                'user_id' => $userId,
                                'form_template_id' => $formTemplateId,
                                'migrated_at' => date('Y-m-d H:i:s'),
                                'legacy_format' => true
                            ];
                            
                            $entryValue->setValue(json_encode($fileInfo));
                            $em->persist($entryValue);
                            
                            $io->text("✅ Migrado: {$value} -> {$newPath}");
                            $migrated++;
                        } else {
                            $io->error("❌ Error migrando: {$value}");
                            $errors++;
                        }
                    } else {
                        $io->text("🔄 [DRY-RUN] Migraría: {$value} -> /{$userId}/{$formTemplateId}/");
                        $migrated++;
                    }
                } else {
                    $io->warning("⚠️ Formato no reconocido: {$value}");
                    $skipped++;
                }
            }

            if (!$dryRun && $migrated > 0) {
                $em->flush();
                $io->success("Cambios guardados en la base de datos");
            }

            $io->section('📊 Resumen de migración');
            $io->table(
                ['Estado', 'Cantidad'],
                [
                    ['Migrados', $migrated],
                    ['Errores', $errors],
                    ['Omitidos', $skipped],
                    ['Total', $migrated + $errors + $skipped]
                ]
            );

            if ($dryRun) {
                $io->note('Esto fue una ejecución de prueba. Ejecuta sin --dry-run para aplicar los cambios.');
            }

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            $io->error('Trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function fixAndroidContentUris($em, SymfonyStyle $io, bool $dryRun): void
    {
        // Buscar valores que contengan content:// URIs
        $androidUris = $em->createQuery(
            'SELECT fev FROM App\Entity\FormEntryValue fev 
             WHERE fev.value LIKE :contentUri 
             AND fev.status = :status'
        )
        ->setParameter('contentUri', 'content://%')
        ->setParameter('status', \App\Enum\Status::ACTIVE)
        ->getResult();

        $io->info(sprintf('Encontrados %d Android content URIs para reparar', count($androidUris)));

        foreach ($androidUris as $entryValue) {
            $value = $entryValue->getValue();
            $io->text("Android URI encontrado: {$value}");
            
            if (!$dryRun) {
                // Marcar como error para que el usuario sepa que debe volver a subir el archivo
                $errorInfo = [
                    'error' => 'android_content_uri',
                    'message' => 'Este archivo debe ser subido nuevamente desde la aplicación móvil',
                    'original_uri' => $value,
                    'detected_at' => date('Y-m-d H:i:s')
                ];
                
                $entryValue->setValue(json_encode($errorInfo));
                $em->persist($entryValue);
                
                $io->text("✅ Marcado para re-subida: {$value}");
            } else {
                $io->text("🔄 [DRY-RUN] Marcaría para re-subida: {$value}");
            }
        }
    }
}
