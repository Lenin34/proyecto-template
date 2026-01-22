<?php

namespace App\Command;

use App\Service\TenantManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-beneficiary-photos',
    description: 'Migra las fotos de beneficiarios a la nueva estructura de carpetas',
)]
class MigrateBeneficiaryPhotosCommand extends Command
{
    private TenantManager $tenantManager;

    public function __construct(TenantManager $tenantManager)
    {
        $this->tenantManager = $tenantManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Nombre del tenant')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Solo mostrar qué se haría sin ejecutar cambios')
            ->setHelp('Este comando migra las fotos de beneficiarios a la nueva estructura: users/{userId}/beneficiaries/{beneficiaryId}/');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenant = $input->getArgument('tenant');
        $dryRun = $input->getOption('dry-run');

        try {
            $io->title('Migración de Fotos de Beneficiarios');
            
            if ($dryRun) {
                $io->note('Modo DRY-RUN: No se realizarán cambios reales');
            }
            
            // Set tenant
            $this->tenantManager->setCurrentTenant($tenant);
            $io->success("Tenant configurado: {$tenant}");
            
            // Get entity manager
            $em = $this->tenantManager->getEntityManager();
            
            // Buscar todos los beneficiarios con fotos
            $beneficiaries = $em->createQuery(
                'SELECT b, u FROM App\Entity\Beneficiary b 
                 JOIN b.user u 
                 WHERE b.status = :status 
                 AND b.photo IS NOT NULL 
                 AND b.photo != \'\''
            )
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->getResult();

            $io->section('📸 Beneficiarios con fotos encontrados:');
            
            if (empty($beneficiaries)) {
                $io->warning('No se encontraron beneficiarios con fotos para migrar');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Encontrados %d beneficiarios con fotos', count($beneficiaries)));

            $migratedCount = 0;
            $errorCount = 0;
            $uploadsDir = '/var/www/html/public/uploads';

            foreach ($beneficiaries as $beneficiary) {
                $user = $beneficiary->getUser();
                $currentPhotoPath = $beneficiary->getPhoto();
                
                $io->writeln('');
                $io->writeln("🔍 <info>Procesando:</info> {$beneficiary->getName()} {$beneficiary->getLastName()} (ID: {$beneficiary->getId()})");
                $io->writeln("👤 <info>Usuario:</info> {$user->getName()} (ID: {$user->getId()})");
                $io->writeln("📸 <info>Foto actual:</info> {$currentPhotoPath}");
                
                // Verificar si ya está en la nueva estructura
                if (strpos($currentPhotoPath, 'users/') === 0) {
                    $io->writeln("✅ <comment>Ya está en la nueva estructura, omitiendo</comment>");
                    continue;
                }
                
                // Construir nueva ruta
                $fileName = basename($currentPhotoPath);
                $newPhotoPath = "users/{$user->getId()}/beneficiaries/{$beneficiary->getId()}/{$fileName}";
                $oldFullPath = $uploadsDir . '/' . $currentPhotoPath;
                $newFullPath = $uploadsDir . '/' . $newPhotoPath;
                
                $io->writeln("📁 <info>Nueva ruta:</info> {$newPhotoPath}");
                
                // Verificar que el archivo original existe
                if (!file_exists($oldFullPath)) {
                    $io->writeln("❌ <error>Archivo original no existe: {$oldFullPath}</error>");
                    $errorCount++;
                    continue;
                }
                
                if (!$dryRun) {
                    try {
                        // Crear directorio de destino
                        $newDir = dirname($newFullPath);
                        if (!is_dir($newDir)) {
                            if (!mkdir($newDir, 0777, true)) {
                                throw new \Exception("No se pudo crear el directorio: {$newDir}");
                            }
                        }
                        
                        // Copiar archivo
                        if (!copy($oldFullPath, $newFullPath)) {
                            throw new \Exception("No se pudo copiar el archivo");
                        }
                        
                        // Actualizar base de datos
                        $beneficiary->setPhoto($newPhotoPath);
                        $beneficiary->setUpdatedAt(new \DateTimeImmutable());
                        
                        // Eliminar archivo original
                        if (file_exists($oldFullPath)) {
                            unlink($oldFullPath);
                        }
                        
                        $io->writeln("✅ <info>Migrado exitosamente</info>");
                        $migratedCount++;
                        
                    } catch (\Exception $e) {
                        $io->writeln("❌ <error>Error: {$e->getMessage()}</error>");
                        $errorCount++;
                    }
                } else {
                    $io->writeln("🔄 <comment>Se migraría de: {$oldFullPath}</comment>");
                    $io->writeln("🔄 <comment>              a: {$newFullPath}</comment>");
                    $migratedCount++;
                }
            }
            
            if (!$dryRun && $migratedCount > 0) {
                $em->flush();
                $io->writeln('');
                $io->success('Cambios guardados en la base de datos');
            }
            
            $io->writeln('');
            $io->section('📊 Resumen:');
            $io->writeln("✅ Archivos migrados: {$migratedCount}");
            $io->writeln("❌ Errores: {$errorCount}");
            
            if ($dryRun) {
                $io->note('Para ejecutar la migración real, ejecuta el comando sin --dry-run');
            } else {
                $io->success('Migración completada');
            }
            
        } catch (\Exception $e) {
            $io->error('Error durante la migración: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
